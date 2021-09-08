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
 * Interface definition for postprocessing methods.
 *
 * @package		local_quizattemptexport
 * @copyright	2020 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\html\methods;

defined('MOODLE_INTERNAL') || die();

abstract class base {

    /**
     * Takes a html snippet which will contain the question container div of a specific
     * question within the html rendered from the attempt and the corresponding question
     * attempt instance.
     *
     * The implementation may to manipulate the HTML as required and will have to return it.
     * The calling code will then replace the input html with the output html.
     *
     * @param string $questionhtml
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @return string
     */
    abstract public static function process(string $questionhtml, \quiz_attempt $attempt, int $slot) : string;


    /**
     * Implementing classes may define any additional CSS required within
     * the return value of this method. It will then be added to the document
     * within a CSS block.
     *
     * @return string
     */
    public static function get_css() : string {
        return '';
    }

}
