# Compass WatchComplete (mod_watchcomplete)

WatchComplete is a Moodle activity that embeds a YouTube, Vimeo or uploaded/linked MP4 video, tracks the unique
content each student has watched, and marks the activity complete when the teacher's threshold is met.

## Requirements

- Moodle 4.5 LTS to 5.3 (`$plugin->requires = 2024100700`, `$plugin->supported = [405, 503]`)
- PHP version as required by each Moodle release. Moodle core enforces this; this plugin adds no stricter limit.
- Students need a browser that can play HTML5 video and, for embeds, can reach `youtube.com` and `player.vimeo.com`.

## Installation

1. Copy the `watchcomplete` folder into `mod/` so that the path is `mod/watchcomplete`.
2. Visit *Site administration > Notifications* to run the upgrade.

### Changing the JavaScript (developers only)

The built file `amd/build/player.min.js` is included, so nothing needs building to install the plugin. If you edit `amd/src/player.js`, rebuild it with Moodle's Grunt task from the Moodle root:

    npx grunt amd --root=mod/watchcomplete

## Configuration (teacher)

| Setting | Notes |
|---|---|
| Video source | YouTube, Vimeo, or MP4/WebM. |
| Video URL | HTTPS link. Required unless an MP4 is uploaded. |
| Upload video file | MP4, WebM or M4V, used in preference to a URL. |
| Video length | Seconds. Progress is measured against it. If 0, the first student's player reports it. |
| Completion threshold | 50, 75, 90, 95, 100, or a custom value from 1 to 100. Uses unique coverage. |
| Prevent skipping | Students cannot move past unwatched sections. They can revisit watched ones. |
| Resume playback | Students are offered Resume (to their last position) or Start again. |

Completion uses a custom rule, **Watch to threshold**. When it is ticked, the activity is marked complete once the student reaches the threshold. Viewing the activity does not complete it.

## Changing the video

If the video source, URL or uploaded file changes, the stored video length and all student progress for that activity are reset, because they belonged to the old media. Set the length again if you need it before students watch the new video.

## Watch tracking

- Playback is sampled about four times a second. Time counts only when the media clock moves forward at roughly real speed.
- Paused time, seeks and skipped sections are not counted.
- Overlapping segments are merged, so re-watching does not raise the percentage.
- Progress is saved every 15 seconds, on pause, and when the page is left.
- The server merges the segments, recalculates coverage, and decides completion. The client's claim of completion is ignored.

## Reports

Teachers with `mod/watchcomplete:viewreports` can see each student's percentage, status and last update, and can export CSV.

## Capabilities

| Capability | Default roles |
|---|---|
| `mod/watchcomplete:addinstance` | Editing teacher, manager |
| `mod/watchcomplete:view` | Student, teacher, editing teacher, manager |
| `mod/watchcomplete:submit` | Student |
| `mod/watchcomplete:viewreports` | Teacher, editing teacher, manager |

## Compatibility

- Uses the classic external functions API, the `core/ajax` and `core/notification` AMD modules, Mustache templates, `core\dataformat`, and the Privacy API. These are supported across 4.5 to 5.3.
- Tests use `advanced_testcase`. Moodle 5.0 moved to PHPUnit 11.4, so run the suite on each target version.
- Not yet verified on every version in the range. Run the checks in *Testing* on 4.5, 5.0, 5.1, 5.2 and 5.3 before release.

## Accessibility

- The progress bar is a `progressbar` with a live percentage. Screen readers hear updates through a polite live region.
- Skip-blocking is announced through an assertive live region.
- The completion message is a status region.
- Embedded players are labelled regions. Reports use a captioned table with scoped headers.
- Reduced-motion preference disables the progress animation.
- Keyboard use relies on the native video and embed controls.

**Gaps:** the plugin does not yet accept caption files for uploaded MP4s. Captions for YouTube and Vimeo come from those services. Teachers must supply captions or a transcript for uploaded files.

## Testing

Run on each target Moodle version:

1. `vendor/bin/phpunit mod/watchcomplete/tests/progress_test.php`
2. moodle-plugin-ci with the Moodle code checker and the accessibility checks
3. Keyboard-only and screen reader walkthrough of the student player and report

## Known limitations

- **Attention is not proven.** Browser tracking shows that the video played and which sections played. It cannot show that a person was watching. The UI says so.
- **Embedded players.** YouTube and Vimeo do not expose everything a native player does. Scrubbing forward with prevent skipping on is reversed on the next sample, a fraction of a second later.
- **Duration.** Accuracy depends on the teacher's value or the first player report. A wrong value is corrected by editing the activity.
- **Client-side data.** A determined student could send falsified segments. Server checks limit this to whole-second segments inside the video length, forward jumps when prevent skipping is on, and a segment cap. They do not stop a student who plays the video at a fast rate on a second device.
- **Not included:** grading, group filtering in the report, and translations other than English.
- Download restrictions and DRM content are not supported.

## External services

For YouTube and Vimeo videos, the student's browser loads Google's YouTube player (`youtube.com`) or the Vimeo player (`player.vimeo.com`). Those services receive the viewer's request, as they do on any page that embeds their videos. Uploaded or linked MP4 and WebM videos use no outside service. The plugin sends no data to any other third party.

## Licence

GNU GPL v3 or later. The full text is in the `LICENSE` file.

## Course reset

The course reset page has a WatchComplete option that deletes student progress for the course.

## Support and issues

Report bugs and request features at https://github.com/michaelsw2484-art/moodle-mod_watchcomplete/issues

## Changes

### 0.1.1

- Fixed: video progress was not saved on Moodle versions that no longer provide the old global `external_api` class. The web service now extends `\core_external\external_api`.
- Fixed: the activity settings form showed a debugging notice because the help text for the completion rule was missing.
- Fixed: the activity page no longer repeats the activity name and description under Moodle's own header.
- Added: a save request can no longer credit more new video than could have been played since the page was opened or the last save (up to 2x playback speed). This does not prove attention, but it stops a single forged request from claiming a whole video.
- Fixed: an uploaded video file is now played on sites served over plain HTTP as well as HTTPS.
