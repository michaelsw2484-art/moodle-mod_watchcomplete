<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// GNU GPL v3 or later. See <http://www.gnu.org/licenses/>.

/**
 * English language strings for mod_watchcomplete.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['collastupdate'] = 'Last updated';
$string['colstatus'] = 'Status';
$string['colstudent'] = 'Student';
$string['colwatched'] = 'Watched';
$string['completedmessage'] = 'Well done. You have watched at least {$a}% of this video, and the activity is complete.';
$string['completionsettings'] = 'Completion';
$string['completionwatched'] = 'Watch to threshold';
$string['completionwatched_help'] = 'When enabled, the activity is marked complete for a student once they have watched the completion threshold percentage of the video. Only unique parts of the video that were really played are counted.';
$string['completionwatchedlabel'] = 'Students must watch the video to the completion threshold';
$string['completionwatchedrule'] = 'Watch at least {$a}% of the video';
$string['customthreshold'] = 'Custom';
$string['customthresholdvalue'] = 'Custom threshold (1 to 100)';
$string['duration'] = 'Video length (seconds)';
$string['duration_help'] = 'The length of the video in seconds. Progress is measured against this value, so it must be accurate. If left as 0, the length is taken from the player the first time a student plays the video.';
$string['enableoption'] = 'Enable';
$string['err_customthreshold'] = 'Enter a whole number from 1 to 100.';
$string['err_duration'] = 'Enter a length from 0 to 86400 seconds.';
$string['err_durationunknown'] = 'The length of this video is not known yet. Play the video once or set a length in the activity settings.';
$string['err_invalidurl'] = 'This URL is not a valid HTTPS link for the selected video source.';
$string['err_mp4required'] = 'Upload a video file or enter a direct link to an MP4, WebM or M4V file.';
$string['err_toomanysegments'] = 'Too many playback segments were sent. Reload the page and try again.';
$string['err_urlrequired'] = 'Enter a video URL, or choose an uploaded file.';
$string['eventvideocompleted'] = 'WatchComplete video completed';
$string['exportcsv'] = 'Export CSV';
$string['modulename'] = 'Compass WatchComplete';
$string['modulename_help'] = 'The Compass WatchComplete activity embeds a YouTube, Vimeo or uploaded MP4 video and tracks how much of it each student has watched. It can mark the activity complete when a set percentage of unique content has been watched.';
$string['modulenameplural'] = 'Compass WatchComplete activities';
$string['neverupdated'] = 'Never';
$string['noparticipants'] = 'No students are enrolled with permission to watch this video.';
$string['nosource'] = 'This activity does not have a playable video source. Please contact your teacher.';
$string['percentwatched'] = '% watched';
$string['pluginadministration'] = 'WatchComplete administration';
$string['pluginname'] = 'Compass WatchComplete';

$string['preventskipnotice'] = 'You can only move forward to parts of the video you have not yet watched after playing up to them.';
$string['preventskipping'] = 'Prevent skipping';
$string['preventskipping_help'] = 'When enabled, students cannot move forward into sections they have not yet watched. They can still go back to sections they have already watched. This makes skipping harder, but it cannot prove that someone was paying attention.';
$string['previewnotrack'] = 'Preview mode: progress is not recorded for this view.';
$string['privacy:metadata:player:ipaddress'] = 'The IP address of the browser that loads the video.';
$string['privacy:metadata:player:useragent'] = 'The browser details of the viewer, sent when the video loads.';
$string['privacy:metadata:vimeo'] = 'When a Vimeo video is used, the viewer\'s browser loads the player from Vimeo. Vimeo\'s privacy policy applies.';
$string['privacy:metadata:watchcomplete_progress'] = 'Records how much of a WatchComplete video a student has watched.';
$string['privacy:metadata:watchcomplete_progress:completed'] = 'Whether the completion threshold has been met.';
$string['privacy:metadata:watchcomplete_progress:covered'] = 'The number of unique seconds watched.';
$string['privacy:metadata:watchcomplete_progress:lastposition'] = 'The last playback position, used for resume.';
$string['privacy:metadata:watchcomplete_progress:percent'] = 'The percentage of unique content watched.';
$string['privacy:metadata:watchcomplete_progress:segments'] = 'The time ranges of the video the student has played.';
$string['privacy:metadata:watchcomplete_progress:timecreated'] = 'When the progress record was created.';
$string['privacy:metadata:watchcomplete_progress:timemodified'] = 'When the progress record was last updated.';
$string['privacy:metadata:watchcomplete_progress:userid'] = 'The student who watched the video.';
$string['privacy:metadata:youtube'] = 'When a YouTube video is used, the viewer\'s browser loads the player from YouTube. YouTube\'s privacy policy applies.';
$string['progresslabel'] = 'Your progress';
$string['reportheading'] = 'Student progress';
$string['reportsummary'] = '{$a->completed} completed, {$a->inprogress} in progress, {$a->notstarted} not started.';
$string['resetprogress'] = 'Delete WatchComplete student progress and completion';
$string['restartbutton'] = 'Start again';
$string['resume'] = 'Resume playback';
$string['resume_help'] = 'When enabled, students are offered the choice to resume where they last stopped or start again.';
$string['resumebutton'] = 'Resume';
$string['resumeprompt'] = 'You stopped at {$a} in this video.';
$string['source_mp4'] = 'Uploaded or linked file (MP4/WebM)';
$string['source_vimeo'] = 'Vimeo';
$string['source_youtube'] = 'YouTube';
$string['status_completed'] = 'Completed';
$string['status_inprogress'] = 'In progress';
$string['status_notstarted'] = 'Not started';
$string['threshold'] = 'Completion threshold';
$string['threshold_help'] = 'The share of unique video content a student must watch before the activity is marked complete. 100% means every second must have been played, not just that the last timestamp was reached.';
$string['thresholdoption'] = '{$a}%';
$string['videofile'] = 'Upload video file';
$string['videofile_help'] = 'Upload one MP4, WebM or M4V file. If a file is uploaded, it is used in preference to a video URL.';
$string['videolabel'] = 'WatchComplete video';
$string['videosettings'] = 'Video';
$string['videosource'] = 'Video source';
$string['videosource_help'] = 'Choose where the video is hosted. YouTube and Vimeo videos are embedded; MP4 and WebM files can be uploaded or linked.';
$string['videourl'] = 'Video URL';
$string['videourl_help'] = 'The full HTTPS address of the video. For YouTube, use a watch, youtu.be, shorts or embed link. For Vimeo, use a vimeo.com video link. For files, link to a .mp4, .webm or .m4v file, or upload one below instead.';



$string['viewreports'] = 'View student progress';


$string['watchcomplete:addinstance'] = 'Add a WatchComplete activity';
$string['watchcomplete:submit'] = 'Submit watch progress';
$string['watchcomplete:view'] = 'View a WatchComplete activity';
$string['watchcomplete:viewreports'] = 'View student progress reports';
