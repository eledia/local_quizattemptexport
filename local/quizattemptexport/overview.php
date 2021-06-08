<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Overview page for quiz attempt exports.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true); // Required for progress bar to work.

require_once '../../config.php';

// Get params.
$cmid = required_param('cmid', PARAM_INT);
$reexportattemptid = optional_param('reexport', 0, PARAM_INT);
$reexportall = optional_param('exportall', 0, PARAM_INT);
$downloadzip = optional_param('downloadzip', 0, PARAM_INT);


// Get course module, quiz instance and context.
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$instance = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = \context_module::instance($cm->id);
$course = get_course($cm->course);


// Check access.
require_login($cm->course, false);
$hasviewcap = has_capability('mod/quiz:viewreports', $context);
$hasgradecap = has_capability('mod/quiz:grade', $context);
if (!$hasviewcap && !$hasgradecap) {
    $capability = 'mod/quiz:viewreports';
    throw new required_capability_exception($context, $capability, 'nopermission', '');
}


// Initialize page.
$pagecontext = context_system::instance();
$strpagetitle = get_string('page_overview_title', 'local_quizattemptexport', $instance->name);
$selfurl = new moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $cm->id]);
$PAGE->set_context($pagecontext);
$PAGE->set_url($selfurl);
$PAGE->set_title($strpagetitle);
$PAGE->set_heading($strpagetitle);


// Check if we should export a zip file of all attempt exports.
if ($downloadzip) {

    $fs = get_file_storage();
    $attemptfiles = [];
    foreach ($DB->get_records('quiz_attempts', ['quiz' => $instance->id, 'state' => 'finished', 'preview' => 0]) as $attempt) {

        $oneattemptfiles = $fs->get_area_files(
            $context->id,
            'local_quizattemptexport',
            'export',
            $attempt->id,
            'timecreated',
            false
        );
        foreach ($oneattemptfiles as $oneattemptfile) {
            $attemptfiles[$oneattemptfile->get_filename()] = $oneattemptfile;
        }
    }

    $zipname = clean_param($instance->name, PARAM_FILE);
    $temppath = $CFG->tempdir . '/' . $zipname;
        $zipper = new zip_packer();
    if ($zipper->archive_to_pathname($attemptfiles, $temppath)) {
        send_temp_file($temppath, $zipname . '.zip');
    } else {
        debugging("Problems with archiving the files.", DEBUG_DEVELOPER);
        die;
    }

}

// Check if we should export an attempt.
if ($hasgradecap && $reexportattemptid) {

    require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
    require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

    $attempt = \quiz_attempt::create($reexportattemptid);

    $export = new \local_quizattemptexport\export_attempt($attempt);
    $export->export_pdf();

    redirect($selfurl, get_string('page_overview_attemptedreexport', 'local_quizattemptexport'));
}

// Check if we should export all attempts.
if ($hasgradecap && $reexportall) {

    require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
    require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

    echo $OUTPUT->header();

    $progressbar = new progress_bar('exportall', 2500);
    $progressbar->create();

    $allattempts = $DB->get_records('quiz_attempts', ['quiz' => $instance->id, 'state' => 'finished']);
    $allattemptsnum = count($allattempts);
    $current = 1;

    foreach ($allattempts as $attemptid => $attemptrec) {

        $progressbar->update($current++, $allattemptsnum, get_string('page_overview_progressbar_step', 'local_quizattemptexport', $attemptid));

        $attempt = \quiz_attempt::create($attemptid);
        $export = new \local_quizattemptexport\export_attempt($attempt);
        $export->export_pdf();
    }

    $progressbar->update_full(100, get_string('page_overview_progressbar_finished', 'local_quizattemptexport'));

    echo $OUTPUT->continue_button($selfurl, 'get');
    echo $OUTPUT->footer();
    exit;
}



// Update breadcrumb nav.
$navbar = $PAGE->navbar;
$navbar->add($course->fullname, new \moodle_url('/course/view.php', ['id' => $course->id]));
$navbar->add($instance->name, new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]));
$navbar->add($strpagetitle, $selfurl);

// Collect the data we want to display.
$fs = get_file_storage();
$rawdata = [];
foreach ($DB->get_records('quiz_attempts', ['quiz' => $instance->id, 'preview' => 0], '', 'DISTINCT userid AS id') as $userid => $notused) {

    $rawdata[$userid] = [];

    foreach ($DB->get_records('quiz_attempts', ['quiz' => $instance->id, 'userid' => $userid, 'state' => 'finished']) as $attempt) {

        $rawdata[$userid][$attempt->id] = $fs->get_area_files(
            $context->id,
            'local_quizattemptexport',
            'export',
            $attempt->id,
            'timecreated',
            false
        );
    }
}

$renderer = $PAGE->get_renderer('local_quizattemptexport');

echo $OUTPUT->header();
echo $renderer->render_attemptexportlist($rawdata, $cm->id, $hasgradecap);
echo $OUTPUT->footer();
