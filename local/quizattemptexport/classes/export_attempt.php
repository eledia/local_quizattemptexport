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
 * Interface that may be used to export a quiz attempt as
 * a PDF.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport;

use core\uuid;
use Knp\Snappy\Pdf;
use local_quizattemptexport\processing\attachments\processor as attachment_processor;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class export_attempt {

    private $page;

    private $logger;

    private $exportpath;
    private $exportfilesystem;

    /** @var \quiz_attempt $attempt_obj */
    private $attempt_obj;
    private $user_rec;

    public function __construct(\quiz_attempt $attempt) {
        global $SITE, $CFG, $PAGE, $DB;

        $this->attempt_obj = $attempt;

        // Load Vendor requirements.
        require_once $CFG->dirroot . '/local/quizattemptexport/vendor/autoload.php';

        // Create logger.
        $log = new \Monolog\Logger('quizattemptexport');
        $log->pushHandler(new \Monolog\Handler\StreamHandler($CFG->dataroot . '/quizattemptexport.log', \Monolog\Logger::ERROR));
        $this->logger = $log;

        if (!$this->user_rec = $DB->get_record('user', array('id' => $this->attempt_obj->get_userid()))) {

            $exc = new \moodle_exception('except_usernotfound', 'local_quizattemptexport', '', $this->attempt_obj->get_userid());
            $this->logexception($exc);

            throw $exc;
        }

        // idnumber currently not used...
        if (empty($this->user_rec->idnumber)) {

            $exc = new \moodle_exception('except_usernoidnumber', 'local_quizattemptexport', '', $this->user_rec->id);
            $this->logmessage($exc->getMessage());

            throw $exc;
        }

        if ($this->exportfilesystem = get_config('local_quizattemptexport', 'exportfilesystem')) {

            // Check export directory.
            try {
                $this->exportpath = util::prepare_filearea_server($this->attempt_obj);
            } catch (\moodle_exception $e) {
                $this->logexception($e);

                throw $e;
            }
        }

        // Create page object for internal use.
        $this->page = new \moodle_page();
        $this->page->set_context(\context_system::instance());   // We also have to set the context.
        $this->page->set_course($SITE);

        $this->page->set_url('/');
        $this->page->set_pagelayout('popup');
        $this->page->set_pagetype('site-index'); //necessary, or the current url will be used automatically
    }


    public function export_pdf() {
        global $CFG, $DB;

        $conf = util::get_config();

        // Generate the HTML content to be rendered into a PDF.
        $generator = new generate_attempt_html($this->page);
        $html = $generator->generate($this->attempt_obj);

        // Set up some processing requirements.
        set_time_limit(0);
        ob_start();// tcpdf doesnt like outputs here.

        // Generate temp file name for pdf generation.
        $tempexportfile = $CFG->tempdir . '/' . uuid::generate() . '.pdf';

        // Decide which wkhtmltopdf binary to use.
        $osinfo = php_uname('s');
        $binarypath = $CFG->dirroot . '/local/quizattemptexport/vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64';
        if (false !== strpos($osinfo, 'Windows')) {
            $binarypath = $CFG->dirroot . '/local/quizattemptexport/vendor/wemersonjanuario/wkhtmltopdf-windows/bin/64bit/wkhtmltopdf.exe';
        }

        try {
            // Start pdf generation and write into a temp file.
            $snappy = new Pdf();
            $snappy->setLogger($this->logger);
            $snappy->setTemporaryFolder($CFG->tempdir);
            $snappy->setTimeout($conf->pdfgenerationtimeout);

            $snappy->setOption('toc', false);
            $snappy->setOption('no-outline', true);
            $snappy->setOption('images', true);
            $snappy->setOption('enable-local-file-access', true);
            $snappy->setOption('enable-external-links', true);

            if ($conf->mathjaxenable) {
                $snappy->setOption('javascript-delay', $conf->mathjaxdelay);
            }

            $snappy->setBinary($binarypath);
            $snappy->generateFromHtml($html, $tempexportfile);

        } catch (\Exception $exc) {

            // Check if file really was not generated or if the error returned
            // by wkhtmltopdf may have been non-critical.

            if (!file_exists($tempexportfile) || !filesize($tempexportfile)) {
                $this->logexception($exc);
                return;
            }
        }

        // Get content from temp file for further processing and clean up.
        $tempfilecontent = file_get_contents($tempexportfile);
        unlink($tempexportfile);

        // Generate the parts of the target file name.

        // Quiz instance name
        $cm = $this->attempt_obj->get_cm();
        $instance = $DB->get_record('quiz', ['id' => $cm->instance]);
        $quizname = clean_param($instance->name, PARAM_FILE);

        // The idnumber which is used for matriculation id.
        $idnumber = $this->user_rec->idnumber;

        // The attempts id for uniqueness.
        $attemptid = $this->attempt_obj->get_attemptid();

        // The current time for more uniqueness.
        $time = date('YmdHis', time());

        // The sha256 hash of the file content for validation purposes.
        $contenthash = hash('sha256', $tempfilecontent);

        // Piece the file name parts together.
        $filename = $quizname . '_' . $idnumber . '_' . $attemptid . '_' . $time . '_' . $contenthash . '.pdf';

        // Write file into filesystem?
        if ($this->exportfilesystem) {

            // TODO local filname might require milliseconds instead of seconds.
            // Write file into the defined export dir, so it may be archived using sftp.
            $localfilepath = $this->exportpath . '/' . $filename;
            file_put_contents($localfilepath, $tempfilecontent);
        }

        // Debug output...
        //file_put_contents($localfilepath . '.html', $html);

        // Write file into moodle file system for web access to the files.
        $cm = $this->attempt_obj->get_cm();
        $context = \context_module::instance($cm->id);

        $filedata = new \stdClass;
        $filedata->contextid = $context->id;
        $filedata->component = 'local_quizattemptexport';
        $filedata->filearea = 'export';
        $filedata->itemid = $this->attempt_obj->get_attemptid();
        $filedata->userid = $this->attempt_obj->get_userid();
        $filedata->filepath = '/';
        $filedata->filename = $filename;

        $fs = get_file_storage();
        $file = $fs->create_file_from_string($filedata, $tempfilecontent);


        // Export attachments.
        attachment_processor::execute($this->attempt_obj);

        // Clean up any unexpected output.
        ob_end_clean();
    }



    /**
     * Writes the given message to the internal log handler
     * with level ERROR.
     *
     * @param string $msg
     */
    public function logmessage($msg) {
        $this->logger->error($msg);
    }

    /**
     * Writes the given Throwable to the internal log handler
     * with as much info as sensible with level CRITICAL.
     *
     * @param \Throwable $exc
     */
    public function logexception($exc) {

        $message = $exc->getMessage();
        $trace = $exc->getTraceAsString();
        $debug = '';
        $errorcode = '';
        if ($exc instanceof \moodle_exception) {
            $debug = $exc->debuginfo;
            $errorcode = $exc->errorcode;
        }

        $this->logger->critical($message,[
            'trace' => $trace,
            'debug' => $debug,
            'errorcode' => $errorcode
        ]);
    }
}
