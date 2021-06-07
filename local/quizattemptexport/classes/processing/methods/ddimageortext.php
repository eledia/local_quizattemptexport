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
 * Postprocessing implementation for qtype_ddimageortext
 *
 * @package		local_quizattemptexport
 * @copyright	2020 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\methods;

use local_quizattemptexport\processing\domdocument_util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class ddimageortext extends base {

    public static function process(string $questionhtml, \quiz_attempt $attempt, int $slot) : string {
        global $CFG, $DB;

        // Get question attempt and question definition.
        $qa = $attempt->get_question_attempt($slot);
        $question = $qa->get_question();

        // Get the users drops from the question attempt data as well as the order of
        // the choices in the question instance.
        $userdrops = [];
        $instancechoicemapping = [];
        foreach ($qa->get_step_iterator() as $step) {

            // Build mapping of the choices defined in the question and the actual choice ordering
            // within the question instance.
            if ($step->get_state() instanceof \question_state_todo) {

                foreach ($step->get_all_data() as $key => $value) {

                    // Makes sure the key is a choice group.
                    if (0 === strpos($key, '_choiceorder')) {

                        // Get group identifier and ordered choices.
                        $group = (int) substr($key, -1); // Max of 8 within the plugin.
                        $values = explode(',', $value);

                        // Create mapping.
                        $instancechoicemapping[$group] = [];
                        foreach ($values as $instancechoicekey => $choicekey) {
                            $instancechoicemapping[$group][$instancechoicekey + 1] = $question->choices[$group][$choicekey];
                        }
                    }
                }
            }

            // Get the users drops from a complete answer.
            if ($step->get_state() instanceof \question_state_complete) {
                $userdrops = $step->get_all_data();
            }

            // Get the users drops from an incomplete answer.
            if ($step->get_state() instanceof \question_state_invalid) {
                $userdrops = $step->get_all_data();
            }
        }

        // Build map of drop zones and the actual drop the user placed
        // on the drop zone within the attempt.
        $dropzones = [];
        foreach ($question->places as $key => $dropzone) {
            $obj = new \stdClass;
            $obj->definition = $dropzone;
            $obj->drop = null;
            if (!empty($userdrops['p' . $key])) {

                $obj->drop = $instancechoicemapping[$dropzone->group][$userdrops['p' . $key]];
            }

            $dropzones[] = $obj;
        }
        $imagecontent = self::generate_image($question, $dropzones);

        // Get DOM and XPath.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        // Rewrite SRC of background image with our generated image as a base64 encoded data url.
        $dataurl = 'data:image/png;base64,' . base64_encode($imagecontent);
        $backgrounds = $xpath->query('//img[starts-with(@class, "dropbackground")]');
        foreach ($backgrounds as $bg) {
            /** @var \DOMElement $bg */
            $bg->setAttribute('src', $dataurl);
        }

        // Generate image with correct answers.
        $correctdrops = [];
        foreach ($question->rightchoices as $key => $value) {
            $obj = new \stdClass;
            $obj->definition = $question->places[$key];
            $obj->drop = $question->choices[$question->places[$key]->group][$value];

            $correctdrops[] = $obj;
        }
        $imagecorrectanswers = self::generate_image($question, $correctdrops);

        $mainadmin = get_admin();
        $mainadminlang = $mainadmin->lang;

        $dataurl = 'data:image/png;base64,' . base64_encode($imagecorrectanswers);
        $content = $xpath->query('//div[@class="content"]');
        foreach ($content as $contentdiv) {

            $newnodetitle = new \lang_string('ddimageortext_correctanswer_title', 'local_quizattemptexport', null, $mainadminlang);
            $titlenode = $dom->createElement('h4', $newnodetitle);

            $imagenode = $dom->createElement('img');
            $imagenode->setAttribute('src', $dataurl);

            $newnode = $dom->createElement('div');
            $newnode->setAttribute('class', 'correctresult clearfix');
            $newnode->appendChild($titlenode);
            $newnode->appendChild($imagenode);

            /** @var \DOMElement $contentdiv */
            foreach ($contentdiv->childNodes as $childNode) {

                /** @var \DOMElement $childNode */
                if (false !== strpos($childNode->getAttribute('class'), 'comment')) {
                    $childNode->parentNode->insertBefore($newnode, $childNode);
                }
            }
        }


        return domdocument_util::save_html($dom);
    }

    protected static function generate_image(\qtype_ddimageortext_question $question, array $dropdefinitions) {
        global $CFG, $DB;

        // Start image generation.
        $fs = get_file_storage();

        // Get the background image from the specific question instance.
        $params = ['contextid' => $question->contextid, 'itemid' => $question->id, 'filearea' => 'bgimage', 'component' => 'qtype_ddimageortext'];
        $select = 'contextid = :contextid AND itemid = :itemid AND filearea = :filearea AND component = :component AND filesize <> 0';
        $dropbg = $DB->get_record_select('files', $select, $params);
        $bgfileinstance = $fs->get_file_instance($dropbg);
        $bgfilecontent = $bgfileinstance->get_content();
        $bgfileinfo = $bgfileinstance->get_imageinfo();

        // Calculate a somewhat fitting font size from the drop backgrounds height.
        $calculatedfontsize = (int) ($bgfileinfo['height'] / 15);
        if ($calculatedfontsize > 15) {
            $calculatedfontsize = 15;
        }

        // Load background into GD.
        $gdbgfile = imagecreatefromstring($bgfilecontent);

        // Defining some data used in the following calculations.
        $font = $CFG->dirroot . '/local/quizattemptexport/font/Open_Sans/OpenSans-Regular.ttf';
        $margin = 3;
        $border = 1;

        // Iterating all drops and calculating dimensions.
        $calculateddrops = [];
        $largest_x = 0;
        $largest_y = 0;
        foreach ($dropdefinitions as $dropzone) {

            if (empty($dropzone->drop)) {
                continue;
            }

            $dropx = $dropzone->definition->xy[0];
            $dropy = $dropzone->definition->xy[1];

            // Render image or text? If there is a drop file it is an image, if there is no drop file it is text...
            $params = ['contextid' => $question->contextid, 'itemid' => $dropzone->drop->id, 'filearea' => 'dragimage', 'component' => 'qtype_ddimageortext'];
            $select = 'contextid = :contextid AND itemid = :itemid AND filearea = :filearea AND component = :component AND filesize <> 0';
            $dropfile = $DB->get_record_select('files', $select, $params);
            if (empty($dropfile)) {

                // Define text data.
                $textcolor = imagecolorallocate($gdbgfile, 0, 0, 0);
                $text = str_replace(['<br>', '<br/>', '<br />'], ["\n", "\n", "\n"], $dropzone->drop->text);

                // Get dimensions for text box.
                $textdimensions = self::calculateTextBox($text, $font, $calculatedfontsize, 0);
                $textboxwidth = $textdimensions['width'] + ($margin * 2);
                $textboxheight = $textdimensions['height']  + ($margin * 2);

                // Get starting positions for border rect and fill rect.
                $backgroundrect_x_from = $dropx;
                $backgroundrect_y_from = $dropy;

                // Get end positions for rectangles.
                $backgroundrect_x_to = $backgroundrect_x_from + ($textboxwidth + (2 * $border));
                $backgroundrect_y_to = $backgroundrect_y_from + ($textboxheight + (2 * $border));

                // Calculate text position.
                // Need to offset y-value as it starts bottom left for text, instead of top left as for other stuff.
                $text_x = $backgroundrect_x_from + $border + $margin;
                $text_y = $backgroundrect_y_from + $border + $margin + $calculatedfontsize;

                // Gather drop data.
                $drop = new \stdClass;
                $drop->type = 'text';
                $drop->bgrect_x_from = $backgroundrect_x_from;
                $drop->bgrect_x_to = $backgroundrect_x_to;
                $drop->bgrect_y_from = $backgroundrect_y_from;
                $drop->bgrect_y_to = $backgroundrect_y_to;
                $drop->text_y = $text_y;
                $drop->text_x = $text_x;
                $drop->text = $text;
                $calculateddrops[] = $drop;

                // Check if dimensions exceed current maximum.
                if ($backgroundrect_x_to > $largest_x) {
                    $largest_x = $backgroundrect_x_to;
                }
                if ($backgroundrect_y_to > $largest_y) {
                    $largest_y = $backgroundrect_y_to;
                }

            } else {

                // Get the drop file.
                $dropfileinstance = $fs->get_file_instance($dropfile);
                $dropfilecontent = $dropfileinstance->get_content();
                $imageinfo = $dropfileinstance->get_imageinfo();

                // Gather drop data.
                $drop = new \stdClass;
                $drop->type = 'image';
                $drop->image_x = $dropx;
                $drop->image_y = $dropy;
                $drop->image_width = $imageinfo['width'];
                $drop->image_height = $imageinfo['height'];
                $drop->imagecontent = $dropfilecontent;
                $calculateddrops[] = $drop;

                // Calculate dimensions.
                $stop_x = $dropx + $imageinfo['width'];
                $stop_y = $dropy + $imageinfo['height'];

                // Check if dimensions exceed current maximum.
                if ($stop_x > $largest_x) {
                    $largest_x = $stop_x;
                }
                if ($stop_y > $largest_y) {
                    $largest_y = $stop_y;
                }
            }
        }

        // Check if the boxes we want to render exceed the available background area. Create a new background
        // with appropriate dimensions if necessary.
        if ($largest_y > $bgfileinfo['height'] || $largest_x > $bgfileinfo['width']) {

            $newbg_height = $bgfileinfo['height'];
            if ($largest_y > $bgfileinfo['height']) {
                $newbg_height = $largest_y + 3;
            }
            $newbg_width = $bgfileinfo['width'];
            if ($largest_x > $bgfileinfo['width']) {
                $newbg_width = $largest_x + 3;
            }

            $tempimg = imagecreatetruecolor($newbg_width, $newbg_height);
            $bgcolor = imagecolorallocate($tempimg, 255, 255, 255);
            imagefilledrectangle($tempimg, 0, 0, $newbg_width, $newbg_height, $bgcolor);
            imagecopyresampled($tempimg, $gdbgfile, 0, 0, 0, 0, $bgfileinfo['width'], $bgfileinfo['height'], $bgfileinfo['width'], $bgfileinfo['height']);
            imagedestroy($gdbgfile);
            $gdbgfile = $tempimg;
        }

        foreach ($calculateddrops as $drop) {

            if ($drop->type == 'image') {

                // Load drop file into GD.
                $gddropfile = imagecreatefromstring($drop->imagecontent);

                // Render onto background and clean it up.
                imagecopymerge($gdbgfile, $gddropfile, $drop->image_x, $drop->image_y, 0, 0, $drop->image_width, $drop->image_height, 100);
                imagedestroy($gddropfile);
            } else {

                // Define colors for text box.
                $bordercolor = imagecolorallocate($gdbgfile, 0, 0, 0);
                $bgcolor = imagecolorallocatealpha($gdbgfile, 255, 255, 255, 30);

                // Draw text box.
                imagesetthickness($gdbgfile, $border);
                imagefilledrectangle($gdbgfile, $drop->bgrect_x_from, $drop->bgrect_y_from, $drop->bgrect_x_to, $drop->bgrect_y_to, $bgcolor);
                imagerectangle($gdbgfile, $drop->bgrect_x_from, $drop->bgrect_y_from, $drop->bgrect_x_to, $drop->bgrect_y_to, $bordercolor);

                // Render text onto text box.
                imagettftext($gdbgfile, $calculatedfontsize, 0, $drop->text_x, $drop->text_y, $textcolor, $font, $drop->text);
            }
        }

        // We only need the image content anyway, so just collect it from the output buffer
        // instead of writing to a temp file.
        ob_start();
        imagepng($gdbgfile);
        $imagecontent = ob_get_contents();
        ob_end_clean();

        // Clean up.
        imagedestroy($gdbgfile);

        return $imagecontent;
    }

    protected static function calculateTextBox($text,$fontFile,$fontSize,$fontAngle) {
        /************
        simple function that calculates the *exact* bounding box (single pixel precision).
        The function returns an associative array with these keys:
        left, top:  coordinates you will pass to imagettftext
        width, height: dimension of the image you have to create
         *************/
        $rect = imagettfbbox($fontSize,$fontAngle,$fontFile,$text);
        $minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
        $maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
        $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
        $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));

        return array(
            "left"   => abs($minX) - 1,
            "top"    => abs($minY) - 1,
            "width"  => $maxX - $minX,
            "height" => $maxY - $minY,
            "box"    => $rect
        );
    }
}
