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
 * English language strings.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attachmentexport_filenamechunk_questionno'] = 'Question';
$string['attachmentexport_filenamechunk_attachment'] = 'Attachment';
$string['attemptresult'] = '{$a->gradeachieved} of {$a->grademax} marks ({$a->gradepercent}%)';
$string['ddimageortext_correctanswer_title'] = 'Correct answers';
$string['ddmarker_correctanswer_title'] = 'Correct answers';
$string['ddwtos_emptydrop_placeholderstr'] = '-----------------';
$string['except_attemptnotinquiz'] = 'The given attempt does not belong to the current quiz instance.';
$string['except_configinvalid'] = 'A setting of the plugin "local_quizattemptexport" is either missing or contains an invalid value: {$a}';
$string['except_dirmissing'] = 'Directory missing: {$a}';
$string['except_dirnotwritable'] = 'Directory is not writable: {$a}';
$string['except_usernoidnumber'] = 'User does not have an idnumber. User id: {$a}';
$string['except_usernotfound'] = 'User could not be found. User id: {$a}';
$string['label_coursename'] = 'Exam';
$string['label_quizname'] = 'Assessment';
$string['label_studentname'] = 'Student';
$string['label_matriculationid'] = 'Matriculation ID';
$string['label_coursecode'] = 'Assessment code';
$string['label_attemptstarted'] = 'Attempt started';
$string['label_attemptended'] = 'Attempt ended';
$string['label_attemptresult'] = 'Assessment result';
$string['nav_exportoverview'] = 'Attempt export overview';
$string['page_overview_title'] = 'Exports for "{$a}"';
$string['page_overview_attemptedreexport'] = 'Attempted to export the attempt again.';
$string['page_overview_progressbar_step'] = 'Exporting attempt with id "{$a}".';
$string['page_overview_progressbar_finished'] = 'Finished exporting all attempts.';
$string['plugindesc'] = 'Automatic export of quiz attempts.';
$string['pluginname'] = 'Assessment export';
$string['setting_autoexport'] = 'Enable automatic export';
$string['setting_autoexport_desc'] = 'Enable this setting to export each quiz attempt automatically when the user submits the attempt.';
$string['setting_exportfilesystem'] = 'Export into server filesystem';
$string['setting_exportfilesystem_desc'] = 'Enable this option to export PDFs into the servers filesystem as well. <br><br> Each submitted attempt will be exported as PDF and made available through the respective quiz instances administration menu where they may be downloaded individually or conveniently packed together into one ZIP archive. Additionally you may wish to have these files exported into a path within your servers filesystem where server processes like archival jobs may access these files. <br><br>Enable this option to additionally have the files be exported into the directory defined in the setting below.';
$string['setting_pdfexportdir'] = 'Export path on server';
$string['setting_pdfexportdir_desc'] = 'This is the path of a directory within your servers filesystem where the PDFs will additionally be saved to if you enable the option above.';
$string['setting_pdfgenerationtimeout'] = 'Timeout for PDF generation (seconds)';
$string['setting_pdfgenerationtimeout_desc'] = 'Set the timeout in seconds that should apply for the generation of the PDF files. If the generation process has not finished after the given amount of time the process will be cancelled. Set a value of 0 to deactivate the timeout.';
$string['task_generate_pdf_name'] = 'Generate attempt PDFs';
$string['template_usersattemptslist_attachmentexportheader'] = 'Attachments uploaded by user';
$string['template_usersattemptslist_attemptfrom'] = 'Attempt from';
$string['template_usersattemptslist_exportall'] = 'Re-export all attempts within this quiz instance';
$string['template_usersattemptslist_noattempts'] = 'Could not find any attempts for this quiz.';
$string['template_usersattemptslist_nofiles'] = 'Could not find any files for this attempt.';
$string['template_usersattemptslist_pdfexportheader'] = 'Generated PDF files';
$string['template_usersattemptslist_reexportattempttitle'] = 'Export attempt again';
$string['template_usersattemptslist_zipdownload'] = 'Download all exported files as ZIP';

$string['envcheck_execfailed'] = 'Error when trying to execute CLI call.';
$string['envcheck_notexecutable'] = 'The binary contained in the plugin needs to be executable for the webserver user. Check the readme for details.';
$string['envcheck_sharedlibsmissing'] = 'The binary is missing shared libraries: {$a}';
$string['envcheck_success'] = 'The environment check succeeded. All dependencies are met.';
