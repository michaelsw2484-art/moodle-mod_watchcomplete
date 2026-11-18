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
const TOLERANCE = 2; // Seconds of forward movement allowed past the furthest point watched.
const MAX_RATE = 1.5; // Largest playback rate the tracker counts as real-time playback.
const SLACK = 0.75; // Seconds of allowance for timer jitter.
const SETTLE = 1; // Seconds to let a seek back take effect before sending the player back again.

const BUILD = '0.1.4';
const loadedScripts = {};

/**
 * Log to the browser console, only when Moodle developer debugging is on.
 *
 * @param {string} message
 * @param {Object} data
 */
const debug = (message, data) => {
    if (window.M && window.M.cfg && window.M.cfg.developerdebug) {
        window.console.log('[mod_watchcomplete] ' + message, data);
    }
};

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
const createAdapter = async(cfg, mount) => {
    if (cfg.source === 'mp4') {
        return {
            getTime: async() => mount.currentTime,
            getDuration: async() => (Number.isFinite(mount.duration) ? mount.duration : 0),
            isPlaying: async() => !mount.paused && !mount.ended,
            seek: async(time) => {
                mount.currentTime = time;
            },
            play: () => mount.play().catch(() => null),
            onChange: (callback) => ['seeking', 'seeked', 'ended'].forEach(
                (name) => mount.addEventListener(name, callback)
            ),
        };
    }

    if (cfg.source === 'youtube') {
        await youtubeReady();
        const listeners = [];
        const player = await new Promise((resolve) => {
            const instance = new window.YT.Player(mount, {
                videoId: cfg.videoid,
                playerVars: {rel: 0, modestbranding: 1, playsinline: 1},
                events: {
                    onReady: () => resolve(instance),
                    // A seek shows up as a state change (buffering, ended), so check straight away rather
                    // than waiting for the next sample.
                    onStateChange: () => listeners.forEach((callback) => callback()),
                },
            });
        });
        return {
            // When a seek lands on the end, YouTube switches straight to ENDED but getCurrentTime() keeps
            // returning the position from before the seek. Without this the tracker would believe the
            // student was still inside the watched part and never send them back.
            getTime: async() => (player.getPlayerState() === window.YT.PlayerState.ENDED
                ? player.getDuration()
                : player.getCurrentTime()),
            getDuration: async() => player.getDuration(),
            isPlaying: async() => player.getPlayerState() === window.YT.PlayerState.PLAYING,
            seek: async(time, attempt = 0) => {
                const ended = player.getPlayerState() === window.YT.PlayerState.ENDED;
                if (!ended && attempt === 0) {
                    player.seekTo(time, true);
                    return 'seekTo';
                }
                // Once a video has ended, YouTube can leave its end screen up and ignore a plain seek, and
                // re-cueing the same video is ignored too. Step up each time the previous try has not
                // moved the player: seek and play, then reload the video from the new position, then stop
                // it completely and reload.
                if (attempt === 0) {
                    player.seekTo(time, true);
                    player.playVideo();
                    return 'seekTo+play';
                }
                if (attempt === 1) {
                    player.loadVideoById({videoId: cfg.videoid, startSeconds: time});
                    return 'loadVideoById';
                }
                player.stopVideo();
                player.loadVideoById({videoId: cfg.videoid, startSeconds: time});
                return 'stop+loadVideoById';
            },
            play: async() => player.playVideo(),
            onChange: (callback) => listeners.push(callback),
        };
    }

    // Vimeo.
    await loadVimeoApi();
    const vimeo = new window.Vimeo.Player(mount, {id: Number(cfg.videoid), responsive: true});
    await vimeo.ready();
    return {
        // Same safeguard as YouTube: once the video has ended, the position is the end.
        getTime: async() => ((await vimeo.getEnded()) ? vimeo.getDuration() : vimeo.getCurrentTime()),
        getDuration: () => vimeo.getDuration(),
        isPlaying: async() => !(await vimeo.getPaused()) && !(await vimeo.getEnded()),
        seek: async(time) => {
            const [ended, duration] = await Promise.all([vimeo.getEnded(), vimeo.getDuration()]);
            // The player's setCurrentTime rejects a time past the end, and watched segments are rounded up to whole
            // seconds, so keep the target inside the video.
            const target = duration > 0 ? Math.min(time, Math.max(0, duration - 0.5)) : time;
            await vimeo.setCurrentTime(Math.max(0, target));
            if (ended) {
                // Once a video has ended, Vimeo keeps its end screen up and stays paused even after the
                // position has moved, so resume playback to show the student where they have been sent.
                await vimeo.play().catch(() => null);
            }
        },
        play: () => vimeo.play().catch(() => null),
        onChange: (callback) => ['seeked', 'ended', 'play'].forEach((name) => vimeo.on(name, callback)),
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
export const init = async(cfg) => {
    const root = document.getElementById('watchcomplete-player');
    const mount = document.getElementById('wc-video');
    const bar = document.getElementById('wc-progress-bar');
    const percentLabel = document.getElementById('wc-percent');
    const completeBox = document.getElementById('wc-complete');
    const notice = document.getElementById('wc-notice');
    if (!root || !mount) {
        return;
    }
    // Lets anyone checking a page in the browser's developer tools see which build of this file is running.
    root.dataset.wcBuild = BUILD;

    const state = {
        merged: decode(cfg.segments),
        pending: [], // Closed segments not yet confirmed by the server.
        open: null, // Segment being played right now.
        duration: cfg.duration || 0,
        percent: cfg.percent || 0,
        completed: !!cfg.completed,
        lastTime: null,
        lastWall: null,
        wasPlaying: false,
        saving: false,
        snapUntil: 0, // While a seek back is in flight, the wall-clock time to wait until before retrying.
        snapAttempts: 0, // Seek backs sent for the current skip, so a method that did not work is not repeated.
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

    const save = async() => {
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
        document.getElementById('wc-resume-btn').addEventListener('click', async() => {
            hideResume();
            await player.seek(cfg.position);
            await player.play();
        });
        document.getElementById('wc-restart-btn').addEventListener('click', async() => {
            hideResume();
            await player.seek(0);
        });
    }

    // Anti-skipping: if the player has moved beyond what has been watched, return to the furthest point.
    // Resolves true when the sample was a skip, so the caller must not count it.
    const blockSkip = async(time, wall) => {
        if (!cfg.preventskipping) {
            return false;
        }
        const limit = reach();
        if (time > limit + TOLERANCE) {
            // An embedded player can go on reporting the old position for a moment after being sent
            // back. Seeking it again during that time restarts the seek (on YouTube, reloading an ended
            // video over and over), so wait for the first one to land.
            if (wall < state.snapUntil) {
                return true;
            }
            closeOpen();
            state.snapUntil = wall + SETTLE;
            // Each retry for the same skip tells the adapter that the previous method did not work.
            const attempt = state.snapAttempts++;
            try {
                const method = await player.seek(limit, attempt);
                debug('skip blocked', {at: time, backTo: limit, attempt, method: method || 'seek'});
            } catch (error) {
                debug('seek back failed, will retry', {at: time, backTo: limit, attempt, error: String(error)});
            }
            if (notice && !notice.dataset.shown) {
                notice.textContent = cfg.skipMessage;
                notice.dataset.shown = '1';
                setTimeout(() => {
                    notice.textContent = '';
                    delete notice.dataset.shown;
                }, 5000);
            }
            state.lastTime = limit;
            state.lastWall = performance.now() / 1000;
            return true;
        }
        if (state.snapAttempts) {
            debug('back within watched range', {at: time, attempts: state.snapAttempts});
        }
        state.snapUntil = 0;
        state.snapAttempts = 0;
        return false;
    };

    const tick = async() => {
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

        if (await blockSkip(time, wall)) {
            return;
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

    // Run one sample at a time. Embedded players answer asynchronously, and two overlapping samples could
    // record a stale position after a seek back has already moved the player.
    let ticking = false;
    const runTick = () => {
        if (ticking) {
            return;
        }
        ticking = true;
        tick().catch(() => null).finally(() => {
            ticking = false;
        });
    };

    if (player.onChange) {
        player.onChange(runTick);
    }
    setInterval(runTick, POLL_MS);
    setInterval(() => {
        save();
    }, SAVE_MS);

    window.addEventListener('pagehide', () => {
        closeOpen();
        save();
    });
};
