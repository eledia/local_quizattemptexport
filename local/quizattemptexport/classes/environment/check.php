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
 * Contains environment checks that need to be called from environment.xml
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\environment;

class check {

    public static function execute(\environment_results $result) {
        global $CFG;

        // No check if we run inside Win environment. The windows binaries dependencies are self-contained.
        $osinfo = php_uname('s');
        if (false !== strpos($osinfo, 'Windows')) {

            $result->setStatus(true);
            $result->setFeedbackStr(['envcheck_success', 'local_quizattemptexport']);

        } else {

            $binarypath = $CFG->dirroot . '/local/quizattemptexport/vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64';

            // Check if binary is executable. Should be executable on GIT install, needs to be made executable on manual installation...
            if (!is_executable($binarypath)) {
                $result->setStatus(false);
                $result->setFeedbackStr(['envcheck_notexecutable', 'local_quizattemptexport']);

            } else {

                // Use "ldd" to check for any missing shared libraries for the binary contained within the plugin. If any
                // libs are missing set status to false and provide list of missing libs using the feedback string.
                exec('ldd ' . $binarypath, $output, $return);

                if ($return != 0) {

                    $result->setStatus(false);
                    $result->setFeedbackStr(['envcheck_execfailed', 'local_quizattemptexport']);

                } else {

                    $missing = [];
                    foreach ($output as $line) {

                        if (false !== strpos($line, 'not found')) {

                            $parts = explode('=>', $line);
                            $missing[] = trim($parts[0]);
                        }
                    }

                    if (!empty($missing)) {

                        $missing = implode(', ', $missing);
                        $result->setStatus(false);
                        $result->setFeedbackStr(['envcheck_sharedlibsmissing', 'local_quizattemptexport', $missing]);
                    } else {

                        $result->setStatus(true);
                        $result->setFeedbackStr(['envcheck_success', 'local_quizattemptexport']);
                    }
                }
            }
        }

        return $result;
    }

}
