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
 * Moodle standard lib.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function local_quizattemptexport_extend_settings_navigation(\settings_navigation $settingsnav, $context) {
    global $CFG, $PAGE;

    // We only want to work with module context.
    if (!($context instanceof \context_module)) {
        return;
    }

    // Check if it is a quiz module.
    $cm = get_coursemodule_from_id('quiz', $context->instanceid);
    if (empty($cm)) {
        return;
    }

    // Make sure the current user may see our settings node.
    if (!has_any_capability(array('mod/quiz:viewreports', 'mod/quiz:grade'), $context)) {
        return;
    }

    // Get the quiz settings node.
    $settingnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);

    // Add our node.
    $text = get_string('nav_exportoverview', 'local_quizattemptexport');
    $url = new moodle_url('/local/quizattemptexport/overview.php', array('cmid' => $context->instanceid));
    $foonode = navigation_node::create(
        $text,
        $url,
        navigation_node::NODETYPE_LEAF,
        $text,
        'quizattemptexportexportoverview',
        new pix_icon('t/download', $text)
    );
    if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
        $foonode->make_active();
    }
    $settingnode->add_node($foonode);
}

/**
 * Serve the files.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function local_quizattemptexport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'export') {
        return false;
    }

    // Make sure the user is logged in and has access to the module (plugins that are not course modules should leave out the 'cm' part).
    require_login();
    require_capability('mod/quiz:viewreports', $context);

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.


    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_quizattemptexport', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
