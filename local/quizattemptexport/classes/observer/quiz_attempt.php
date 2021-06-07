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
 * Static collection of handler methods for quiz attempt events.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\observer;

use local_quizattemptexport\task\generate_pdf;

defined('MOODLE_INTERNAL') || die();

class quiz_attempt {

    public static function handle_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        $exportenabled = get_config('local_quizattemptexport', 'autoexport');
        if ($exportenabled) {

            $event_data = $event->get_data();
            generate_pdf::add_attempt_to_queue($event_data['objectid']);
        }
    }
}
