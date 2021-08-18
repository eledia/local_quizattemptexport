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



        // Transform any image contained within the content that is not already a data url
        // into a data URL. This is necessary for local files served through moodle file
        // system but will also avoid problems where wkhtmltopdf will not be able to load
        // non-local files.
        $dom = domdocument_util::initialize_domdocument($html);
        $xpath = new \DOMXPath($dom);
        $imgs = $xpath->query('//img');
        foreach ($imgs as $img) {

            /** @var \DOMElement $img */
            $imgsrc = $img->getAttribute('src');

            // Ignore data urls.
            if (0 === strpos($imgsrc, 'data:')) {
                continue;
            }

            $dataurl = '';

            // Check if the IMG elements SRC attribute contains a local file that likely requires
            // authentication by checking if the URL starts with our WWWROOT and contains a path
            // element that matches "pluginfile.php".
            $urlparts = explode('/', $imgsrc);
            if (0 === strpos($imgsrc, $CFG->wwwroot) && in_array('pluginfile.php', $urlparts)) {

                // Seems to be a local file likely requiring authentication, i.e. we need to get the file
                // content from the moodle file system by using the relevant arguments contained
                // within the URL.

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
                    $dataurl = 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($imgcontent);
                }

            } else {

                // Not a local file that requires authentication. Simply load the image content
                // from the URL if possible and build a data url from it.

                $imgcontent = file_get_contents($imgsrc);
                if (!empty($imgcontent)) {

                    // Get the filename extension.
                    $parts = explode('.', $imgsrc);
                    $filetype = array_pop($parts);

                    // Pretend that the filename extension "svg" actually indicates an SVG file
                    // and handle the file content accordingly.
                    if ($filetype == 'svg') {

                        $dataurl = 'data:image/svg+xml;base64,' . base64_encode($imgcontent);

                    } else {

                        // Let GD decide if it wants to handle the file content. If it does, we will
                        // save it as PNG and embed that one. IF GD does not like the content, we will
                        // let the image default to our placeholder.
                        if ($imgres = imagecreatefromstring($imgcontent)) {

                            // Get PNG version of the file content.
                            ob_start();
                            imagepng($imgres);
                            $imagecontent = ob_get_contents();
                            ob_end_clean();
                            imagedestroy($imgres);

                            // Create data url.
                            $dataurl = 'data:image/png;base64,' . base64_encode($imagecontent);
                        }
                    }
                }
            }

            // Fallback to placeholder image.
            if (empty($dataurl)) {
                $imgcontent = file_get_contents($CFG->dirroot . '/local/quizattemptexport/pix/edit-delete.png');
                $dataurl = 'data:image/png;base64,' . base64_encode($imgcontent);
            }

            $img->setAttribute('src', $dataurl);
        }

        return domdocument_util::save_html($dom);
    }
}
