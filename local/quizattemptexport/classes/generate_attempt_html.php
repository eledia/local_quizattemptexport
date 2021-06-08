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
 *
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport;

use local_quizattemptexport\processing\processor;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class generate_attempt_html {

    private $page;
    private $attempt_obj;
    private $attempt_rec;
    private $quiz_rec;
    private $accessmanager;
    private $user_rec;

    /**
     *
     * @param \moodle_page $page
     */
    public function __construct(\moodle_page $page) {
        $this->page = $page;
    }

    public function generate(\quiz_attempt $attempt) {

        $this->initialize_attempt($attempt);

        ob_start();

        echo $this->page_header();
        echo $this->custom_css_simple();
        echo $this->report_header();
        echo $this->generate_attempt_output();
        echo $this->page_footer();

        $html = ob_get_contents();
        ob_end_clean();

        // Some replacements for better compatibility.
        $html = str_replace('<label', '<span class="quizanswer"', $html);
        $html = str_replace('</label>', '</span>', $html);

        return processor::execute($html, $this->attempt_obj);
    }

    private function initialize_attempt(\quiz_attempt $attempt) {
        global $DB;

        $this->attempt_obj = $attempt;
        $this->attempt_rec = $this->attempt_obj->get_attempt();
        $this->quiz_rec = $this->attempt_obj->get_quiz();
        $this->accessmanager = $this->attempt_obj->get_access_manager(time());

        $this->user_rec = $DB->get_record('user', array('id' => $this->attempt_obj->get_userid()));
    }

    /**
     * Retrieves the code used for enrolment into the assessment course
     * by the user the currently processed attempt belongs to.
     *
     * If we are not able to find a code this method returns
     * a placeholder string.
     *
     * @return string
     * @throws \dml_exception
     */
    private function get_coursecode() {
        global $DB;

        $enrolkey = 'n/a';
        $course = $this->attempt_obj->get_course();
        $userid = $this->attempt_obj->get_userid();

        $sql = 'SELECT ue.*
                FROM 
                    {user_enrolments} ue,
                    {enrol} e
                WHERE
                    e.enrol = :enrolname
                AND 
                    e.courseid = :courseid
                AND 
                    ue.enrolid = e.id
                AND 
                    ue.userid = :userid';
        $params = [
            'enrolname' => 'elediamultikeys',
            'courseid' => $course->id,
            'userid' => $userid
        ];

        if ($enrolments = $DB->get_records_sql($sql, $params)) {
            $enrolment = array_shift($enrolments);

            // Make sure required plugin table exists.
            if($tables = $DB->get_tables()) {

                if (in_array('block_eledia_multikeys', $tables)) {

                    if ($keyrec = $DB->get_record('block_eledia_multikeys', ['enrolid' => $enrolment->enrolid, 'userid' => $userid])) {
                        $enrolkey = $keyrec->code;
                    }
                }
            }
        }

        return $enrolkey;
    }

    protected function report_header() {
        global $CFG, $DB;

        // Prepare data.
        $course = $this->attempt_obj->get_course();
        $quiz = $this->quiz_rec;
        $coursecode = $this->get_coursecode();
        $attemptsubmittedtime = date('d.m.Y - H:i:s', $this->attempt_rec->timefinish);
        $attemptstartedtime = date('d.m.Y - H:i:s', $this->attempt_rec->timestart);

        // Prepare result data.
        $marksachieved = $this->attempt_obj->get_sum_marks();
        $grademultiplier = $quiz->grade / $quiz->sumgrades;
        $grademax = round($quiz->grade, 2);
        $gradeachieved = round($marksachieved * $grademultiplier, 2);
        $gradepercent = round($gradeachieved / $grademax * 100, 0);

        // Prepare result string.
        $params = [
            'grademax' => $grademax,
            'gradeachieved' => $gradeachieved,
            'gradepercent' => $gradepercent
        ];
        $attemptresultstr = get_string('attemptresult', 'local_quizattemptexport', $params);

        // Prepare data for template.
        $templatedata = [
            'coursename' => $course->fullname,
            'quizname' => $quiz->name,
            'studentname' => fullname($this->user_rec),
            'matriculationid' => $this->user_rec->idnumber,
            //'coursecode' => $coursecode,
            'attemptstarted' => $attemptstartedtime,
            'attemptended' => $attemptsubmittedtime,
            'attemptresult' => $attemptresultstr
        ];

        // Render template and return html.
        $renderer = $this->page->get_renderer('core');
        return $renderer->render_from_template('local_quizattemptexport/pdf_header', $templatedata);
    }

    /**
     * Get a simplyfied header to reduce the errors while creating pdf.
     * @return string
     */
    protected function page_header() {

        return '<!DOCTYPE html>
        <html  dir="ltr" lang="de" xml:lang="de">
            <body  id="page-site-index">
                <div id="page" class="container-fluid">
                    <div id="page-content" class="row-fluid">
                        <section id="region-main" class="span12">
                            <span class="notifications" id="user-notifications"></span><div role="main"><span id="maincontent"></span>
        ';
    }

    /**
     * Get a simplyfied footer to reduce the errors while creating pdf.
     * @return string
     */
    protected function page_footer() {
        return '
                        </section>
                    </div>
                </div>
            </body>
        </html>
        ';
    }


    /**
     * Get a simple css definition.
     * @return string
     */
    protected function custom_css_simple() {
        global $CFG;

        return '<style type="text/css">
            @page {
                margin-top: 20px;
                margin-bottom: 20px;
                margin-left: 50px;
                margin-right: 50px;
            }

            body {
                font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
                font-size: 10pt;
            }

            img.questioncorrectnessicon,
            .informationitem {
                display: none;
            }

            .ablock .prompt {
                margin-top: 10px;
                font-weight: bold;
            }

            .outcome {
                margin-top: 10px;
            }

            .que.multichoice div.answer div.correct span.quizanswer {
                background-image: url('.$CFG->wwwroot.'/local/quizattemptexport/pix/correct.png);
                background-repeat: no-repeat;
                background-position: right top;
                background-color: #fff;
            }

            .que.multichoice div.answer div.incorrect span.quizanswer {
                background-image: url('.$CFG->wwwroot.'/local/quizattemptexport/pix/incorrect.png);
                background-repeat: no-repeat;
                background-position: right top;
                background-color: #fff;
            }

            /* remove default correctness icon, since its positioning in pdf is off */
            .que.multichoice div.answer div.correct > img,
            .que.multichoice div.answer div.incorrect > img {
                display: none;
            }

            div.answer {
                display: table;
                width: 90%
            }
            div.answer .r0, div.answer .r1 {
                display: table-row;
            }

            div.answer div input {
                display: table-cell;
                vertical-align: top;
                width: 30px;
                padding-top: 1px;
                margin-top: 1px;
            }

            span.quizanswer {
                display: table-cell;
                padding-right: 50px;
                padding-bottom: 5px;
                padding-top: 5px;
                margin-top: 5px;
            }

            div.que {
                page-break-before: always;
                border-style: solid;
                border-width: 1px;
                border-color: #dddddd;
                padding-left: 15px;
                padding-right: 15px;
                padding-bottom: 10px;
                margin-bottom: 10px;
            }
            div.nobreak {
                page-break-after: avoid;
                page-break-before: auto;
            }
            
            /**
                table styling
             */
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            table th,
            table td {
                padding: 5px 10px;
                border: 1px solid #000;
                text-align: left;
            }
            
            table.reportheader th {
                text-align: right;
                width: 30%;
            }
            
            /*
                Hide specific links that are displayed by moodle if the
                user context the export happens in has review rights for
                the given attempt.
             */
            div.que div.commentlink,
            div.que div.editquestion {
                display: none;
            }
            
            /**
                Question header styling
            */
            div.info {
                background-color: #dddddd;
                margin-top: 10px;
                padding: 10px;
            }
            
            div.info h3 {
                margin: 0 0 10px 0;
                padding: 0 0 5px 5px;
                border-bottom: 1px solid #000;
            }
            
            div.info .state,
            div.info .grade {
                font-weight: bold;
                margin: 10px 0 0 10px;
            }
            
            /**
                question sections styling
             */
             div.comment,
             div.outcome,
             div.formulation,
             div.correctresult, /* Added manually in processing methods */
             div.history {
                page-break-inside: avoid;
                border: 1px solid #000;
                margin: 10px 0;
                padding: 10px;
             }
             
             div.comment h4,
             div.outcome h4,
             div.formulation h4,
             div.correctresult h4, /* Added manually in processing methods */
             div.history h4 {
                margin: 0 0 10px 0;
             }
             
             /*
                Make sure images do not exceed pdf width.
              */
             img {
                max-width: 100%;
             }
             
        </style>';
    }

    protected function generate_attempt_output() {
        global $USER, $DB;

        // Get super admin to temporarily override global $USER so we
        // get all the nice extra information rendered in here the regular
        // user might not see.
        $mainadmin = get_admin();
        $mainadminfull = get_complete_user_data('id', $mainadmin->id);
        $olduser = $USER;
        $USER = $mainadminfull;

        //set up vars required by copypasta
        $page = 0;
        $showall = true;

        try {


            $options = $this->attempt_obj->get_display_options(true);
            $options->flags = 0; // The flags attribute has to be "0".
            $options->rightanswer = \question_display_options::VISIBLE;
            $options->correctness = \question_display_options::VISIBLE;

            $slots = $this->attempt_obj->get_slots();
            $headtags = $this->attempt_obj->get_html_head_contributions($page, $showall);
            $this->accessmanager->setup_attempt_page($this->page);

            $this->page->set_title(format_string($this->attempt_obj->get_quiz_name()));
            $this->page->set_heading($this->attempt_obj->get_course()->fullname);

/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
            //COPYPASTA aus mod/quiz/review.php

            // Work out some time-related things.
            $overtime = 0;
            if ($this->attempt_rec->timefinish) {
                if ($timetaken = ($this->attempt_rec->timefinish - $this->attempt_rec->timestart)) {
                    if ($this->quiz_rec->timelimit && $timetaken > ($this->quiz_rec->timelimit + 60)) {
                        $overtime = $timetaken - $this->quiz_rec->timelimit;
                        $overtime = format_time($overtime);
                    }
                    $timetaken = format_time($timetaken);
                } else {
                    $timetaken = "-";
                }
            } else {
                $timetaken = get_string('unfinished', 'quiz');
            }

            // Prepare summary informat about the whole attempt.
            $summarydata = array();

            //always show student-info
            //if (!$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id) {

            $student = $DB->get_record('user', array('id' => $this->attempt_obj->get_userid()));
            $usrepicture = new \user_picture($student);
            $usrepicture->courseid = $this->attempt_obj->get_courseid();
            $summarydata['user'] = array(
                'title' => $usrepicture,
                'content' => new \action_link(new \moodle_url('/user/view.php', array(
                    'id' => $student->id, 'course' => $this->attempt_obj->get_courseid())),
                    fullname($student, true)),
            );
            //}
            if ($this->attempt_obj->has_capability('mod/quiz:viewreports')) {
                $attemptlist = $this->attempt_obj->links_to_other_attempts($this->attempt_obj->review_url(null, $page,
                    $showall));
                if ($attemptlist) {
                    $summarydata['attemptlist'] = array(
                        'title' => get_string('attempts', 'quiz'),
                        'content' => $attemptlist,
                    );
                }
            }

            // Timing information.
            $summarydata['startedon'] = array(
                'title' => get_string('startedon', 'quiz'),
                'content' => userdate($this->attempt_rec->timestart),
            );

            if ($this->attempt_rec->timefinish) {
                $summarydata['completedon'] = array(
                    'title' => get_string('completedon', 'quiz'),
                    'content' => userdate($this->attempt_rec->timefinish),
                );
                $summarydata['timetaken'] = array(
                    'title' => get_string('timetaken', 'quiz'),
                    'content' => $timetaken,
                );
            }

            if (!empty($overtime)) {
                $summarydata['overdue'] = array(
                    'title' => get_string('overdue', 'quiz'),
                    'content' => $overtime,
                );
            }

            // Show marks (if the user is allowed to see marks at the moment).
            $grade = quiz_rescale_grade($this->attempt_rec->sumgrades, $this->quiz_rec, false);
            if ($options->marks >= \question_display_options::MARK_AND_MAX && quiz_has_grades($this->quiz_rec)) {

                if (!$this->attempt_rec->timefinish) {
                    $summarydata['grade'] = array(
                        'title' => get_string('grade', 'quiz'),
                        'content' => get_string('attemptstillinprogress', 'quiz'),
                    );

                } else if (is_null($grade)) {
                    $summarydata['grade'] = array(
                        'title' => get_string('grade', 'quiz'),
                        'content' => quiz_format_grade($this->quiz_rec, $grade),
                    );

                } else {
                    // Show raw marks only if they are different from the grade (like on the view page).
                    if ($this->quiz_rec->grade != $this->quiz_rec->sumgrades) {
                        $a = new \stdClass();
                        $a->grade = quiz_format_grade($this->quiz_rec, $this->attempt_rec->sumgrades);
                        $a->maxgrade = quiz_format_grade($this->quiz_rec, $this->quiz_rec->sumgrades);
                        $summarydata['marks'] = array(
                            'title' => get_string('marks', 'quiz'),
                            'content' => get_string('outofshort', 'quiz', $a),
                        );
                    }

                    // Now the scaled grade.
                    $a = new \stdClass();
                    $a->grade = \html_writer::tag('b', quiz_format_grade($this->quiz_rec, $grade));
                    $a->maxgrade = quiz_format_grade($this->quiz_rec, $this->quiz_rec->grade);
                    if ($this->quiz_rec->grade != 100) {
                        $a->percent = \html_writer::tag('b', format_float(
                            $this->attempt_rec->sumgrades * 100 / $this->quiz_rec->sumgrades, 0));
                        $formattedgrade = get_string('outofpercent', 'quiz', $a);
                    } else {
                        $formattedgrade = get_string('outof', 'quiz', $a);
                    }
                    $summarydata['grade'] = array(
                        'title' => get_string('grade', 'quiz'),
                        'content' => $formattedgrade,
                    );
                }
            }

            // Feedback if there is any, and the user is allowed to see it now.
            $feedback = $this->attempt_obj->get_overall_feedback($grade);
            if ($options->overallfeedback && $feedback) {
                $summarydata['feedback'] = array(
                    'title' => get_string('feedback', 'quiz'),
                    'content' => $feedback,
                );
            }


/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////

            $quiz_renderer = $this->page->get_renderer('mod_quiz');
            //echo $quiz_renderer->review_summary_table($summarydata, $page);
            $output = $quiz_renderer->questions($this->attempt_obj, true, $slots, $page, $showall, $options);

            // Put an empty div inside the questions box to prevent a pagebreak on this place.
            $searchpattern = '#<div id=".*?" class="que.*?</div>#';
            if (preg_match_all($searchpattern, $output, $founds)) {
                if (!empty($founds[0])) {
                    foreach ($founds[0] as $replsearch) {
                        $output = str_replace($replsearch, '</div>&nbsp;<div class="nobreak">' . $replsearch, $output);
                    }
                }
            }

            // Make the title for the correct answer strong.
            $searchpattern = '#<div class="rightanswer">(.*?:).*?</div>#';
            if (preg_match_all($searchpattern, $output, $founds)) {
                if (!empty($founds[1])) {
                    foreach ($founds[1] as $replsearch) {
                        $output = str_replace($replsearch, '<strong>' . $replsearch . '</strong>', $output);
                        break; // The result is alway the same. So we just need one replace.
                    }
                }
            }
        }
        finally {

            // Make sure we set global $USER back to its initial value...
            $USER = $olduser;
        }

        return $output;
    }


}
