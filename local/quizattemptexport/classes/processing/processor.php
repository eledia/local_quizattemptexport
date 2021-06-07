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
 * Postprocessing controller
 *
 * @package		local_quizattemptexport
 * @copyright	2020 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing;

defined('MOODLE_INTERNAL') || die();

class processor {

    public static function execute(string $html, \quiz_attempt $attempt) {
        global $CFG, $DB;

        $html = domdocument_util::prepare_html($html);
        $dom = domdocument_util::initialize_domdocument($html);

        $xpath = new \DOMXPath($dom);
        $questions = $xpath->query('//div[starts-with(@class, "que ")]');

        foreach ($questions as $question) {

            /** @var \DOMElement $question */

            $class = $question->getAttribute('class');
            $parts = explode(' ', $class);
            $qtype = $parts[1];

            $id = $question->getAttribute('id');
            $parts = explode('-', $id);
            $slot = (int) array_pop($parts);

            $input_html = domdocument_util::save_html($dom, $question);
            $input_html = domdocument_util::prepare_html($input_html);
            $output_html = '';

            /** @var \local_quizattemptexport\processing\methods\base $processingclass */
            $processingclass = '\local_quizattemptexport\processing\methods\\' . $qtype;
            if (class_exists($processingclass)) {
                $output_html = $processingclass::process($input_html, $attempt, $slot);
            }

            if (!empty($output_html)) {
                $html = str_replace($input_html, $output_html, $html);
            }
        }



        // Do generalized replacement of images that contain a pluginfile url.
        $dom = domdocument_util::initialize_domdocument($html);
        $xpath = new \DOMXPath($dom);
        $imgs = $xpath->query('//img');
        foreach ($imgs as $img) {

            /** @var \DOMElement $img */

            // Ignore image sources that don't contain "pluginfile.php". We probably don't need
            // to handle those, and would probably don't know ow to handle them anyway.
            if (false === strpos($img->getAttribute('src'), 'pluginfile.php')) {
                continue;
            }

            $imgsrc = $img->getAttribute('src');
            $parts = explode('pluginfile.php/', $imgsrc);
            $parts = explode('?', $parts[1]); // Remove any query params.
            $filepathargs = $parts[0];

            $fileargs = explode('/', $filepathargs);
            $contextid = array_shift($fileargs);
            $component = array_shift($fileargs);
            $filearea = array_shift($fileargs);

            $imgfilename = urldecode(array_pop($fileargs));
            $itemid = array_pop($fileargs);


            $filerec = $DB->get_record('files', ['component' => $component, 'filearea' => $filearea, 'itemid' => $itemid, 'filename' => $imgfilename, 'contextid' => $contextid]);

            if (!empty($filerec)) {
                $fs = get_file_storage();
                $file = $fs->get_file_instance($filerec);
                $imgcontent = $file->get_content();
                $dataurl = 'data:'.$file->get_mimetype().';base64,' . base64_encode($imgcontent);
            } else {
                $imgcontent = file_get_contents($CFG->dirroot . '/local/quizattemptexport/pix/edit-delete.png');
                $dataurl = 'data:image/png;base64,' . base64_encode($imgcontent);
            }

            $img->setAttribute('src', $dataurl);
        }

        return domdocument_util::save_html($dom);
    }
}
