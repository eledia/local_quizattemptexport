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
 * General plugin settings.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // needs this condition or there is error on login page

    $settings = new admin_settingpage('local_quizattemptexport', get_string('pluginname', 'local_quizattemptexport'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_quizattemptexport/plugindesc',
            '', get_string('plugindesc', 'local_quizattemptexport'))
    );


    $settings->add(new admin_setting_configcheckbox('local_quizattemptexport/autoexport',
            get_string('setting_autoexport', 'local_quizattemptexport'),
            get_string('setting_autoexport_desc', 'local_quizattemptexport'),
            0)
    );

    $settings->add(new admin_setting_configcheckbox('local_quizattemptexport/exportfilesystem',
            get_string('setting_exportfilesystem', 'local_quizattemptexport'),
            get_string('setting_exportfilesystem_desc', 'local_quizattemptexport'),
            0)
    );

    $pdfexportdir_default = $CFG->dataroot . '/quizattemptexport';
    $settings->add(new admin_setting_configdirectory('local_quizattemptexport/pdfexportdir',
            get_string('setting_pdfexportdir', 'local_quizattemptexport'),
            get_string('setting_pdfexportdir_desc', 'local_quizattemptexport'),
            $pdfexportdir_default)
    );

    $settings->add(new admin_setting_configtext('local_quizattemptexport/pdfgenerationtimeout',
            get_string('setting_pdfgenerationtimeout', 'local_quizattemptexport'),
            get_string('setting_pdfgenerationtimeout_desc', 'local_quizattemptexport'),
            120,
            PARAM_INT)
    );

}
