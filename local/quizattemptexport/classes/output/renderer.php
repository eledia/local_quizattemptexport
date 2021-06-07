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
 * Plugin renderer.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\output;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class renderer extends \plugin_renderer_base {

    public function render_attemptexportlist($rawdata, $cmid, $canexportagain) {
        global $DB;

        $templatedata = [
            'users' => [],
            'exportallurl' => $canexportagain && !empty($rawdata) ? new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $cmid, 'exportall' => 1]) : ''
        ];
        foreach ($rawdata as $userid => $attempts) {

            $user = $DB->get_record('user', ['id' => $userid]);
            $userdata = [
                'fullname' => fullname($user),
                'username' => $user->username,
                'attempts' => []
            ];

            foreach ($attempts as $attemptid => $files) {

                $attemptobj = \quiz_attempt::create($attemptid);

                $reexporturl = null;
                if ($canexportagain) {
                    $reexporturl = new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $attemptobj->get_cmid(), 'reexport' => $attemptid]);
                }

                $attemptdata = [
                    'timefinished' => date('d.m.Y - H:i:s', $attemptobj->get_attempt()->timefinish),
                    'files' => [],
                    'reexporturl' => $reexporturl
                ];

                foreach ($files as $file) {

                    /** @var \stored_file $file */

                    $filedata = [];
                    $filedata['url'] = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    );
                    $filedata['name'] = $file->get_filename();
                    $filedata['timecreated'] = date('d.m.Y - H:i:s', $file->get_timecreated());

                    $attemptdata['files'][] = $filedata;
                }

                $userdata['attempts'][] = $attemptdata;
            }

            $templatedata['users'][] = $userdata;
        }

        return $this->render_from_template('local_quizattemptexport/usersattemptslist', $templatedata);
    }
}
