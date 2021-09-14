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
 * Static collection of utility methods.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport;

defined('MOODLE_INTERNAL') || die();

class util {

    /**
     * This method returns the applicable export path within the servers filesystem
     * for the given attempt.
     *
     * Checks if relevant settings are valid and creates the required directories if
     * they are missing.
     *
     * Throws an exception on any problem.
     *
     * @param \quiz_attempt $attempt
     * @return string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function prepare_filearea_server(\quiz_attempt $attempt) {

        // Get exportdir setting.
        $exportdir = get_config('local_quizattemptexport', 'pdfexportdir');
        if (empty($exportdir)) {
            throw new \moodle_exception('except_configinvalid', 'local_quizattemptexport', '', 'pdfexportdir');
        }

        // Check if exportdir is usable.
        if (!is_dir($exportdir)) {
            throw new \moodle_exception('except_dirmissing', 'local_quizattemptexport', '', $exportdir);
        } else if (!is_writable($exportdir)) {
            throw new \moodle_exception('except_dirnotwritable', 'local_quizattemptexport', '', $exportdir);
        }

        // Construct subdir path.
        $course = $attempt->get_course();
        //$coursename = clean_param($course->fullname, PARAM_SAFEDIR);
        //$dirname = $course->id . '_' . $coursename;
        $dirname = $course->id;
        $exportpath = $exportdir . '/' . $dirname;

        // Create the export path if missing.
        if (!is_dir($exportpath)) {
            if (!mkdir($exportpath)) {
                throw new \moodle_exception('except_dirnotwritable', 'local_quizattemptexport', '', $exportdir);
            }
        }

        return $exportpath;
    }


    public static function get_config() {
        $conf = get_config('local_quizattemptexport');

        // Check timeout for PDF-Generation. An empty value or value < 1 will deactivate the timeout.
        if ($conf->pdfgenerationtimeout) {
            $settingstimeout = (int) $conf->pdfgenerationtimeout;
            if ($settingstimeout < 1) {
                $settingstimeout = null;
            }
            $conf->pdfgenerationtimeout = $settingstimeout;
        } else {
            $conf->pdfgenerationtimeout = null;
        }

        // Check MathJax delay. If an invalid value was set, reset it to the default.
        if ($conf->mathjaxdelay) {
            $delay = (int) $conf->mathjaxdelay;
            if ($delay < 1) {
                $delay = 10;
            }
            $conf->mathjaxdelay = $delay * 1000; // Setting is in seconds, milliseconds are required.
        } else {
            $conf->mathjaxdelay = 10000;
        }

        return $conf;
    }

}
