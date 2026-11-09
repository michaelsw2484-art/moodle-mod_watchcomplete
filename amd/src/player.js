// This file is part of Moodle - http://moodle.org/
// GNU GPL v3 or later. See <http://www.gnu.org/licenses/>.

/**
 * Student video player and watch tracker for mod_watchcomplete.
 *
 * Time is sampled every POLL_MS. A stretch counts as watched only when the media clock moved forward
 * at a rate consistent with real elapsed time, so paused time and seeks are not counted. The server
 * re-checks everything on save, so this client code is a convenience, not a security boundary.
 *
 * @module     mod_watchcomplete/player
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const POLL_MS = 250;
const SAVE_MS = 15000;
const TOLERANCE = 2;          // Seconds of forward movement allowed past the furthest point watched.
const MAX_RATE = 1.5;         // Largest playback rate the tracker counts as real-time playback.
const SLACK = 0.75;           // Seconds of allowance for timer jitter.

const loadedScripts = {};

/**
 * Load an external script once.
 *
 * @param {string} src
 * @returns {Promise<void>}
 */
const loadScript = (src) => {
    if (!loadedScripts[src]) {
        loadedScripts[src] = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Could not load ' + src));
            document.head.appendChild(script);
        });
    }
    return loadedScripts[src];
};

/**
 * Resolve when the YouTube IFrame API is ready.
 *
 * @returns {Promise<void>}
 */
const youtubeReady = () => {
    if (window.YT && window.YT.Player) {
        return Promise.resolve();
    }
    return new Promise((resolve) => {
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            if (typeof previous === 'function') {
                previous();
            }
            resolve();
        };
        loadScript('https://www.youtube.com/iframe_api');
    });
};

/**
 * Load the Vimeo player API once.
 *
 * @returns {Promise<void>}
 */
const loadVimeoApi = () => {
    if (window.Vimeo && window.Vimeo.Player) {
        return Promise.resolve();
    }
    // Moodle's RequireJS would capture the Vimeo script's anonymous define() call and reject it, so
    // hide define while the script loads and restore it afterwards.
    return new Promise((resolve, reject) => {
        const amdDefine = window.define;
        const restore = () => {
            window.define = amdDefine;
        };
        window.define = undefined;
        const script = document.createElement('script');
        script.src = 'https://player.vimeo.com/api/player.js';
        script.async = true;
        script.onload = () => {
            restore();
            resolve();
        };
        script.onerror = () => {
            restore();
            reject(new Error('Could not load the Vimeo player.'));
        };
        document.head.appendChild(script);
    });
};

/**
 * Build a player adapter for the chosen source. Every adapter returns promises for the same methods.
 *
 * @param {Object} cfg Configuration from PHP.
 * @param {HTMLElement} mount The video element or placeholder div.
 * @returns {Promise<Object>}
 */
const createAdapter = async (cfg, mount) => {
    if (cfg.source === 'mp4') {
        return {
            getTime: async () => mount.currentTime,
            getDuration: async () => (Number.isFinite(mount.duration) ? mount.duration : 0),
            isPlaying: async () => !mount.paused && !mount.ended,
            seek: async (time) => {
                mount.currentTime = time;
            },
            play: () => mount.play().catch(() => {}),
        };
    }

    if (cfg.source === 'youtube') {
        await youtubeReady();
        const player = await new Promise((resolve) => {
            const instance = new window.YT.Player(mount, {
                videoId: cfg.videoid,
                playerVars: {rel: 0, modestbranding: 1, playsinline: 1},
                events: {onReady: () => resolve(instance)},
            });
        });
        return {
            getTime: async () => player.getCurrentTime(),
            getDuration: async () => player.getDuration(),
            isPlaying: async () => player.getPlayerState() === window.YT.PlayerState.PLAYING,
            seek: async (time) => {
                // seekTo is ignored once a video has ended, so cue it at the new position instead.
                if (player.getPlayerState() === window.YT.PlayerState.ENDED) {
                    player.cueVideoById({videoId: cfg.videoid, startSeconds: time});
                } else {
                    player.seekTo(time, true);
                }
            },
            play: async () => player.playVideo(),
        };
    }

    // Vimeo.
    await loadVimeoApi();
    const vimeo = new window.Vimeo.Player(mount, {id: Number(cfg.videoid), responsive: true});
    await vimeo.ready();
    return {
        getTime: () => vimeo.getCurrentTime(),
        getDuration: () => vimeo.getDuration(),
        isPlaying: async () => !(await vimeo.getPaused()) && !(await vimeo.getEnded()),
        seek: (time) => vimeo.setCurrentTime(time),
        play: () => vimeo.play().catch(() => {}),
    };
};

/**
 * Parse "start-end,start-end" into objects.
 *
 * @param {string} encoded
 * @returns {Array<{start: number, end: number}>}
 */
const decode = (encoded) => (encoded || '')
    .split(',')
    .filter((pair) => pair.includes('-'))
    .map((pair) => {
        const [start, end] = pair.split('-').map(Number);
        return {start, end};
    })
    .filter((seg) => Number.isFinite(seg.start) && Number.isFinite(seg.end) && seg.end > seg.start);

/**
 * Sort and merge overlapping segments.
 *
 * @param {Array<{start: number, end: number}>} list
 * @returns {Array<{start: number, end: number}>}
 */
const mergeSegments = (list) => {
    const sorted = list.slice().sort((a, b) => a.start - b.start);
    const out = [];
    sorted.forEach((seg) => {
        const last = out[out.length - 1];
        if (last && seg.start <= last.end) {
            last.end = Math.max(last.end, seg.end);
        } else {
            out.push({start: seg.start, end: seg.end});
        }
    });
    return out;
};

/**
 * Initialise the player and tracker.
 *
 * @param {Object} cfg
 */
export const init = async (cfg) => {
    const root = document.getElementById('watchcomplete-player');
    const mount = document.getElementById('wc-video');
    const bar = document.getElementById('wc-progress-bar');
    const percentLabel = document.getElementById('wc-percent');
    const completeBox = document.getElementById('wc-complete');
    const notice = document.getElementById('wc-notice');
    if (!root || !mount) {
        return;
    }

    const state = {
        merged: decode(cfg.segments),
        pending: [],     // Closed segments not yet confirmed by the server.
        open: null,      // Segment being played right now.
        duration: cfg.duration || 0,
        percent: cfg.percent || 0,
        completed: !!cfg.completed,
        lastTime: null,
        lastWall: null,
        wasPlaying: false,
        saving: false,
    };

    const render = () => {
        bar.style.width = state.percent + '%';
        percentLabel.textContent = String(state.percent);
        root.querySelector('.wc-progress').setAttribute('aria-valuenow', String(state.percent));
        if (state.completed) {
            completeBox.hidden = false;
        }
        // Keyboard and screen reader users reach the live status via the progressbar's accessible name.
        root.querySelector('.wc-progress').setAttribute('aria-valuetext', state.percent + '% watched');
    };

    const allSegments = () => mergeSegments(
        state.merged.concat(state.pending, state.open ? [state.open] : [])
    );
    const reach = () => allSegments().reduce((max, seg) => Math.max(max, seg.end), 0);

    // Live estimate from local segments, so the bar moves as the video plays. The server's figure
    // replaces it on every save.
    const localPercent = () => {
        if (state.duration <= 0) {
            return null;
        }
        const covered = allSegments().reduce(
            (sum, seg) => sum + Math.max(0, Math.min(seg.end, state.duration) - Math.max(seg.start, 0)),
            0
        );
        return Math.min(100, Math.floor(Math.min(covered, state.duration) * 100 / state.duration));
    };

    const closeOpen = () => {
        if (state.open) {
            state.pending.push(state.open);
            state.open = null;
        }
    };

    const save = async () => {
        if (!cfg.canSubmit || state.saving || state.duration <= 0) {
            return;
        }
        if (!state.pending.length && !state.open) {
            return;
        }
        const sent = state.pending.slice();
        const segments = sent.concat(state.open ? [{...state.open}] : []);
        state.saving = true;
        try {
            const result = await Ajax.call([{
                methodname: 'mod_watchcomplete_update_progress',
                args: {
                    cmid: cfg.cmid,
                    segments,
                    position: Math.floor(state.lastTime || 0),
                    duration: Math.ceil(state.duration),
                },
            }])[0];
            state.merged = decode(result.segments);
            state.pending = state.pending.filter((seg) => !sent.includes(seg));
            state.percent = result.percent;
            state.completed = state.completed || result.completed;
            state.duration = result.duration;
            render();
        } catch (error) {
            Notification.exception(error);
        } finally {
            state.saving = false;
        }
    };

    let player;
    try {
        player = await createAdapter(cfg, mount);
    } catch (error) {
        Notification.exception(error);
        return;
    }

    render();

    // Offer a choice rather than moving the student silently. The prompt is only rendered when there
    // is a saved position to return to.
    const resumeBox = document.getElementById('wc-resume');
    const hideResume = () => {
        if (resumeBox) {
            resumeBox.hidden = true;
        }
    };
    if (resumeBox) {
        document.getElementById('wc-resume-btn').addEventListener('click', async () => {
            hideResume();
            await player.seek(cfg.position);
            await player.play();
        });
        document.getElementById('wc-restart-btn').addEventListener('click', async () => {
            hideResume();
            await player.seek(0);
        });
    }

    const tick = async () => {
        if (!cfg.canSubmit) {
            return;
        }
        const time = await player.getTime();
        const wall = performance.now() / 1000;
        const playing = await player.isPlaying();
        if (!Number.isFinite(time)) {
            return;
        }

        if (state.duration <= 0) {
            const reported = Math.round(await player.getDuration());
            if (reported > 0) {
                state.duration = reported;
            }
        }

        // Anti-skipping: if the player has moved beyond what has been watched, return to the furthest point.
        if (cfg.preventskipping) {
            const limit = reach();
            if (time > limit + TOLERANCE) {
                closeOpen();
                await player.seek(limit);
                if (notice && !notice.dataset.shown) {
                    notice.textContent = cfg.skipMessage;
                    notice.dataset.shown = '1';
                    setTimeout(() => {
                        notice.textContent = '';
                        delete notice.dataset.shown;
                    }, 5000);
                }
                state.lastTime = limit;
                state.lastWall = wall;
                return;
            }
        }

        const prevTime = state.lastTime;
        const prevWall = state.lastWall;
        state.lastTime = time;
        state.lastWall = wall;

        if (!playing) {
            if (state.wasPlaying) {
                closeOpen();
                state.wasPlaying = false;
                save();
            }
            return;
        }
        if (!state.wasPlaying) {
            // Playback has just started. Nothing to count until the next sample.
            state.wasPlaying = true;
            hideResume();
            return;
        }

        const media = time - prevTime;
        const elapsed = wall - prevWall;
        if (media > 0 && media <= elapsed * MAX_RATE + SLACK) {
            if (!state.open) {
                state.open = {start: Math.floor(prevTime), end: Math.ceil(time)};
            } else {
                state.open.end = Math.ceil(time);
            }
            const live = localPercent();
            if (live !== null && live > state.percent) {
                state.percent = live;
                render();
            }
        } else {
            // Seek or stall: end the current stretch.
            closeOpen();
        }
    };

    setInterval(() => {
        tick().catch(() => {});
    }, POLL_MS);
    setInterval(() => {
        save();
    }, SAVE_MS);

    window.addEventListener('pagehide', () => {
        closeOpen();
        save();
    });
};
