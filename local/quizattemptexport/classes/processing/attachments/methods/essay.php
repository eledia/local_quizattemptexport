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
 * Processing of user provided attachments for essay question type.
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\attachments\methods;

defined('MOODLE_INTERNAL') || die();

class essay extends base {

    /**
     * Processes attachments of essay question attempts.
     *
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @return mixed|void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public static function process(\quiz_attempt $attempt, int $slot) {
        global $DB;

        // Initialize data.
        $questionattempt = $attempt->get_question_attempt($slot);

        // Get the question attempt step that contains attachments (if any
        // have been uploaded).
        $stepwithattachments = null;
        foreach ($questionattempt->get_step_iterator() as $step) {

            // User provided a full answer, i.e. provided written text and a file.
            if ($step->get_state() instanceof \question_state_complete) {
                $stepwithattachments = $step;
            }

            // User only provided a partial answer, likely just an attachment.
            if ($step->get_state() instanceof \question_state_invalid) {
                $stepwithattachments = $step;
            }
        }

        // Did we find a step that may contain attachments?
        if (empty($stepwithattachments)) {
            return;
        }

        // Prepare context.
        $cmid = $attempt->get_cmid();
        $context = \context_module::instance($cmid);

        // Get attachment files.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'question', 'response_attachments', $stepwithattachments->get_id());
        $attachments = [];
        foreach ($files as $file) {
            if ($file->get_filename() != '.' && $file->get_filename() != '..') {
                $attachments[] = $file;
            }
        }

        // Export attachment files, if any.
        if (!empty($attachments)) {
            self::export_files($attempt, $slot, $attachments);
        }
    }
}
