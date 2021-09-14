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
 * Implementation of scheduled_task that generates attempt PDFs.
 *
 * Decouples the PDF generation from users submitting their attempts. Submitted
 * attempts are stored in a database table and will be processed in the order
 * they have been submitted.
 *
 * Uses file system locking to avoid race conditions when used in a clustered
 * environment to avoid cluster instances trying to process the same attempts.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\task;

use local_quizattemptexport\export_attempt;
use local_quizattemptexport\util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class generate_pdf extends \core\task\scheduled_task {

    const LOCKDIRNAME = 'quizattemptexport_lockdir';
    protected $haslock = false;

    const STATUS_WAITING = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_PROCESSED = 2;
    const STATUS_ERROR = 3;

    const ATTEMPTS_PER_RUN = 100;
    const JSDELAY_CUTOFF_SECONDS = 60;

    public function get_name() {
        return get_string('task_generate_pdf_name', 'local_quizattemptexport');
    }

    public function execute() {
        global $DB;

        $conf = util::get_config();

        // Check if automatic export is enabled.
        if (!$conf->autoexport) {
            mtrace('Automatic export disabled. Exiting.');
            return;
        }

        // Gain lock for choosing the attempts to export.
        mtrace('Trying to gain lock');
        if (!$this->gain_lock()) {
            mtrace('Could not gain lock. Exiting.');
            return;
        }

        mtrace('Lock gained.');
        mtrace('Choosing attempts to export.');

        // Calculate number of attempts to process.
        $attemptsperrun = self::ATTEMPTS_PER_RUN;
        if ($conf->mathjaxenable) {

            // If MathJax typesetting is enabled we will have a specific delay applied
            // per PDF transformation. We have to account for that delay to avoid
            // blocking general execution of cron tasks.
            $jsdelay = $conf->mathjaxdelay / 1000; // Value in milliseconds.
            $attemptsperrun = floor(self::JSDELAY_CUTOFF_SECONDS / $jsdelay);
        }
        mtrace('Max attempts being processed: ' . $attemptsperrun);


        // Get some non processed attempts and mark them as being processed.
        $attempts = $DB->get_records('quizattemptexport', ['status' => self::STATUS_WAITING], 'timecreated ASC', '*', 0, $attemptsperrun);
        foreach ($attempts as $attempt) {
            $DB->set_field('quizattemptexport', 'status', self::STATUS_PROCESSING, ['id' => $attempt->id]);
            mtrace('Marking queued attempt record as being processed: ' . $attempt->id);
        }

        // Free lock, so other task instances may process attempts.
        mtrace('Freeing lock.');
        $this->free_lock();

        if (empty($attempts)) {
            mtrace('No queued attempts to process.');
            return;
        }

        // Process attempts.
        foreach ($attempts as $attemptrec) {

            try {

                mtrace('Trying to process attempt: ' . $attemptrec->attemptid);

                // Generate attempt.
                $attemptinstance = \quiz_attempt::create($attemptrec->attemptid);
                $export = new export_attempt($attemptinstance);
                $export->export_pdf();

                mtrace('Attempt was processed.');

                // Set attempt as processed.
                $attemptrec->status = self::STATUS_PROCESSED;
                $attemptrec->timegenerated = time();
                $DB->update_record('quizattemptexport', $attemptrec);

                mtrace('Marking queued attempt record as processed.');

            } catch (\Throwable $t) {

                mtrace('Error during processing.');
                mtrace($t->getMessage());
                mtrace($t->getTraceAsString());

                if (isset($export)) {
                    $export->logexception($t);
                }

                // Check how often the PDF generation has failed for the attempt and
                // either reset the processing status or mark the attempt as failed.
                $attemptrec->generationattempts++;
                if ($attemptrec->generationattempts < 3) {
                    mtrace('Marking queued attempt for retry.');
                    $attemptrec->status = self::STATUS_WAITING;
                } else {
                    mtrace('Queued attempt reached max retries. Marking with error state.');
                    $attemptrec->status = self::STATUS_ERROR;
                }
                $DB->update_record('quizattemptexport', $attemptrec);
            }
        }
    }

    /**
     * Tries to gain a process lock by creating a directory within moodles temp
     * directory.
     *
     * Retries up to 10 times in creating the lock with a 1 to 5 second sleep in
     * between each attempt. Returns true if the lock was gained or false if not.
     *
     * Uses a directory as a lock since directory creation is an atomic operation
     * that either succeeds or fails.
     *
     * @return bool Lock gained
     */
    protected function gain_lock() {

        if ($this->haslock) {
            return true;
        }

        $lockdirpath = $this->get_lockdirpath();
        $maxattempts = 10;

        $attempts = 0;
        while($attempts++ < $maxattempts) {

            if (!@mkdir($lockdirpath)) {
                $sleepduration = rand(1, 5);
                sleep($sleepduration);
            } else {
                $this->haslock = true;
                break;
            }
        }

        return $this->haslock;
    }

    /**
     * Frees the lock if there is one.
     *
     */
    protected function free_lock() {

        if (!$this->haslock) {
            return;
        }

        $lockdirpath = $this->get_lockdirpath();
        @rmdir($lockdirpath);
        $this->haslock = false;
    }

    /**
     * Returns the path of the lock directory.
     *
     * @return string
     */
    protected function get_lockdirpath() {
        global $CFG;

        return $CFG->tempdir . '/' . self::LOCKDIRNAME;
    }

    /**
     * Adds an attempt to the processing queue.
     *
     * @param int $attemptid
     * @throws \dml_exception
     */
    public static function add_attempt_to_queue($attemptid) {
        global $DB;

        $rec = new \stdClass;
        $rec->attemptid = $attemptid;
        $rec->status = self::STATUS_WAITING;
        $rec->generationattempts = 0;
        $rec->timecreated = time();
        $rec->timegenerated = 0;

        $DB->insert_record('quizattemptexport', $rec);
    }

}
