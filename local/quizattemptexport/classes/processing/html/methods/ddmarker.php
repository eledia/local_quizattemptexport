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
 * Postprocessing implementation for qtype_ddmarker
 *
 * @package		local_quizattemptexport
 * @copyright	2020 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\html\methods;

use local_quizattemptexport\processing\html\domdocument_util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class ddmarker extends base {

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
        }

        // Build array of markers we need to render onto the background.
        $rendermarkers = [
            'points' => [],
            'shapes' => []
        ];
        foreach ($instancechoicemapping as $group => $groupchoices) {
            foreach ($groupchoices as $key => $option) {

                if (!empty($userdrops['c' . $key])) {

                    // The drop construct might contain multiple coords delimited by ";". If
                    // it does not, this will create an array with a single element...
                    $multicoords = explode(';', $userdrops['c' . $key]);
                    foreach ($multicoords as $coords) {
                        $obj = new \stdClass;
                        $obj->text = $option->text;
                        $obj->coords = explode(',', $coords);
                        $rendermarkers['points'][] = $obj;
                    }
                }
            }
        }

        $imagecontent = self::generate_image($question, $rendermarkers);

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
        $correctmarkers = [
            'points' => [],
            'shapes' => []
        ];
        foreach ($question->rightchoices as $key => $value) {
            $obj = new \stdClass;
            $obj->text = $question->choices[$question->places[$key]->group][$value]->text;
            $obj->coords = $question->places[$key]->shape->center_point();
            $obj->shape = null;

            if ($question->places[$key]->shape instanceof \qtype_ddmarker_shape_rectangle) {

                $coords = $question->places[$key]->coords;
                $parts = explode(';', $coords);

                $shape = new \stdClass;
                $shape->type = 'rect';
                $shape->startpoint = explode(',', $parts[0]);
                $shape->dimensions = explode(',', $parts[1]);

                $correctmarkers['shapes'][] = $shape;
                $obj->shape = $shape;

            } else if ($question->places[$key]->shape instanceof \qtype_ddmarker_shape_circle) {

                $coords = $question->places[$key]->coords;
                $parts = explode(';', $coords);

                $shape = new \stdClass;
                $shape->type = 'circle';
                $shape->startpoint = explode(',', $parts[0]);
                $shape->radius = $parts[1];

                $correctmarkers['shapes'][] = $shape;
                $obj->shape = $shape;

            } else if ($question->places[$key]->shape instanceof \qtype_ddmarker_shape_polygon) {

                $shape = new \stdClass;
                $shape->type = 'polygon';
                $shape->coords = $question->places[$key]->shape->coords;

                $correctmarkers['shapes'][] = $shape;
                $obj->shape = $shape;
            }

            $correctmarkers['points'][] = $obj;
        }
        $imagecorrectanswers = self::generate_image($question, $correctmarkers, true);

        $mainadmin = get_admin();
        $mainadminlang = $mainadmin->lang;

        $dataurl = 'data:image/png;base64,' . base64_encode($imagecorrectanswers);
        $content = $xpath->query('//div[@class="content"]');
        foreach ($content as $contentdiv) {

            $newnodetitle = new \lang_string('ddmarker_correctanswer_title', 'local_quizattemptexport', null, $mainadminlang);
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

    protected static function generate_image(\qtype_ddmarker_question $question, array $rendermarkers, bool $avoidoverlap = false) {
        global $CFG, $DB;

        // Start image generation.
        $fs = get_file_storage();

        // Get the background image from the specific question instance.
        $params = ['contextid' => $question->contextid, 'itemid' => $question->id, 'filearea' => 'bgimage', 'component' => 'qtype_ddmarker'];
        $select = 'contextid = :contextid AND itemid = :itemid AND filearea = :filearea AND component = :component AND filesize <> 0';
        $dropbg = $DB->get_record_select('files', $select, $params);
        $bgfileinstance = $fs->get_file_instance($dropbg);
        $bgfilecontent = $bgfileinstance->get_content();
        $bgfileinfo = $bgfileinstance->get_imageinfo();

        // Calculate a somewhat fitting font size from the drop backgrounds height.
        $calculatedfontsize = (int) ($bgfileinfo['height'] / 18);
        if ($calculatedfontsize > 15) {
            $calculatedfontsize = 15;
        }

        // Load background image and marker icon into GD.
        $markerimg = imagecreatefrompng($CFG->dirroot . '/local/quizattemptexport/pix/crosshairs.png');
        $gdbgfile = imagecreatefromstring($bgfilecontent);

        // Iterate the shapes, if any, and render them onto the background.
        foreach ($rendermarkers['shapes'] as $shape) {

            $drawcolor = imagecolorallocate($gdbgfile, 0, 0, 0);

            if ($shape->type == 'circle') {

                imageellipse(
                    $gdbgfile,
                    $shape->startpoint[0],
                    $shape->startpoint[1],
                    $shape->radius,
                    $shape->radius,
                    $drawcolor
                );
            } else if ($shape->type == 'rect') {

                imagerectangle(
                    $gdbgfile,
                    $shape->startpoint[0],
                    $shape->startpoint[1],
                    $shape->startpoint[0] + $shape->dimensions[0],
                    $shape->startpoint[1] + $shape->dimensions[1],
                    $drawcolor
                );
            } else if ($shape->type == 'polygon') {

                $pointsnum = 0;
                $points = [];
                foreach ($shape->coords as $coord) {

                    $pointsnum++;
                    $points[] = $coord[0];
                    $points[] = $coord[1];
                }

                imagepolygon($gdbgfile, $points, $pointsnum, $drawcolor);
            }
        }

        // Define values used in the following calculations.
        $font = $CFG->dirroot . '/local/quizattemptexport/font/Open_Sans/OpenSans-Regular.ttf';
        $margin = 3;
        $border = 1;

        // Iterate the markers positioned by the user and calculate all required dimensions.
        $textboxes = [];
        foreach ($rendermarkers['points'] as $marker) {

            $dropx = $marker->coords[0];
            $dropy = $marker->coords[1];
            $text = str_replace(['<br>', '<br/>', '<br />'], ["\n", "\n", "\n"], $marker->text);

            // Get dimensions for text box.
            $textdimensions = self::calculateTextBox($text, $font, $calculatedfontsize, 0);
            $textboxwidth = $textdimensions['width'] + ($margin * 2);
            $textboxheight = $textdimensions['height']  + ($margin * 2);

            // Get starting position for border rect.
            $backgroundrect_x_from = $dropx;
            $backgroundrect_y_from = $dropy;

            // Get end position for rectangle.
            $backgroundrect_x_to = $backgroundrect_x_from + ($textboxwidth + (2 * $border));
            $backgroundrect_y_to = $backgroundrect_y_from + ($textboxheight + (2 * $border));

            // Get starting position for text.
            $text_x = $backgroundrect_x_from + $border + $margin;
            $text_y = $backgroundrect_y_from + $border + $margin + $calculatedfontsize;

            // Save coordinates and related data into object.
            $coords = new \stdClass;
            $coords->bgrect_x_from = $backgroundrect_x_from;
            $coords->bgrect_y_from = $backgroundrect_y_from;
            $coords->bgrect_x_to = $backgroundrect_x_to;
            $coords->bgrect_y_to = $backgroundrect_y_to;
            $coords->text_x = $text_x;
            $coords->text_y = $text_y;
            $coords->text = $text;
            $coords->shape = !empty($marker->shape) ? $marker->shape : null; // Only required if $avoidoverlap is used.
            $textboxes[] = $coords;
        }

        // If the $avoidoverlap option is enabled we need to check if there is overlap
        // between text boxes that share a shape and try to reposition those boxes in
        // a way that they are still contained within their shape but have their overlap
        // reduced as much as possible.
        if ($avoidoverlap) {

            // TODO polygon?
            // TODO ellipse?

            // Group text boxes by their containing shape.
            $shapegroups = [];
            foreach ($textboxes as $box) {

                // Make sure the box definition contains a shape definition.
                if (empty($box->shape)) {
                    continue;
                }

                if ($box->shape->type == 'rect') {
                    $matched = false;
                    foreach ($shapegroups as $group) {
                        if ($group->type == 'rect') {
                            if (
                                $group->startpoint[0] == $box->shape->startpoint[0]
                                && $group->startpoint[1] == $box->shape->startpoint[1]
                                && $group->dimensions[0] == $box->shape->dimensions[0]
                                && $group->dimensions[1] == $box->shape->dimensions[1]
                            ) {
                                $group->boxes[] = $box;
                                $matched = true;
                            }
                        }
                    }

                    if (!$matched) {
                        $shapegroup = new \stdClass;
                        $shapegroup->boxes = [$box];
                        $shapegroup->startpoint = $box->shape->startpoint;
                        $shapegroup->dimensions = $box->shape->dimensions;
                        $shapegroup->type = $box->shape->type;
                        $shapegroups[] = $shapegroup;
                    }
                }
            }

            // Iterate shape groups and check if we need to do anything.
            foreach ($shapegroups as $group) {

                if ($group->type == 'rect') {

                    $margin = 10;
                    $shapeheight = $group->dimensions[1];
                    $aggregatedboxheights = 0;
                    $boxcount = 0;

                    // Aggregate box heights and count boxes.
                    foreach ($group->boxes as $box) {
                        $boxheight = $box->bgrect_y_to - $box->bgrect_y_from;
                        $aggregatedboxheights += $boxheight;
                        $boxcount++;
                    }

                    // Do we even have to fit more than one box?
                    if ($boxcount <= 1) {
                        continue;
                    }

                    // Calculate offset for new starting points of boxes.
                    $aggregatedboxheights += $margin;
                    $boxesoffset = floor(($shapeheight - $aggregatedboxheights) / $boxcount);

                    // Reposition boxes.
                    $new_start_x = $group->startpoint[0] + $margin;
                    $new_start_y = $group->startpoint[1] + $margin;
                    foreach ($group->boxes as $box) {

                        // Calculate current dimensions / offsets.
                        $boxheight = $box->bgrect_y_to - $box->bgrect_y_from;
                        $boxwidth = $box->bgrect_x_to - $box->bgrect_x_from;
                        $text_x_offset = $box->text_x - $box->bgrect_x_from;
                        $text_y_offset = $box->text_y - $box->bgrect_y_from;

                        // Calculate new dimensions.
                        $box->bgrect_x_from = $new_start_x;
                        $box->bgrect_y_from = $new_start_y;
                        $box->bgrect_x_to = $new_start_x + $boxwidth;
                        $box->bgrect_y_to = $new_start_y + $boxheight;
                        $box->text_x = $new_start_x + $text_x_offset;
                        $box->text_y = $new_start_y + $text_y_offset;

                        // Calculate new start-y for next box.
                        $new_start_y = $box->bgrect_y_to + $boxesoffset;
                    }
                }
            }
        }

        // Sort textboxes by their x-coordinates to enforce rendering in a left-to-right fashion to
        // avoid situations where the left side of a text box is overlapped by another box.
        usort($textboxes, ['\local_quizattemptexport\processing\methods\ddmarker', 'sort_boxes_ltr']);

        // Calculate largest x and y values so we may check if any of our boxes exceed the
        // available background.
        $largest_x = 0;
        $largest_y = 0;
        foreach ($textboxes as $box) {

            if ($box->bgrect_x_to > $largest_x) {
                $largest_x = $box->bgrect_x_to;
            }
            if ($box->bgrect_y_to > $largest_y) {
                $largest_y = $box->bgrect_y_to;
            }
        }

        // Check if our text boxes exceed the available background area. Create a new background
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

        // Render the calculated text boxes onto the background.
        foreach ($textboxes as $box) {

            // Colors...
            $textcolor = imagecolorallocate($gdbgfile, 0, 0, 0);
            $bordercolor = imagecolorallocate($gdbgfile, 0, 0, 0);
            $bgcolor = imagecolorallocatealpha($gdbgfile, 255, 255, 255, 30);

            // Draw text background.
            imagesetthickness($gdbgfile, $border);
            imagefilledrectangle($gdbgfile, $box->bgrect_x_from, $box->bgrect_y_from, $box->bgrect_x_to, $box->bgrect_y_to, $bgcolor);
            imagerectangle($gdbgfile, $box->bgrect_x_from, $box->bgrect_y_from, $box->bgrect_x_to, $box->bgrect_y_to, $bordercolor);

            // Draw marker icon over text backgrounds
            imagecopyresampled($gdbgfile, $markerimg, $box->bgrect_x_from - 7.5, $box->bgrect_y_from - 7.5, 0, 0, 15, 15, 15, 15);

            // Draw text onto its background.
            imagettftext($gdbgfile, $calculatedfontsize, 0, $box->text_x, $box->text_y, $textcolor, $font, $box->text);
        }

        // We only need the image content anyway, so just collect it from the output buffer
        // instead of writing to a temp file.
        ob_start();
        imagepng($gdbgfile);
        $imagecontent = ob_get_contents();
        ob_end_clean();

        // Clean up.
        imagedestroy($gdbgfile);
        imagedestroy($markerimg);

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

    protected static function sort_boxes_ltr($a, $b) {

        if ($a->bgrect_x_from < $b->bgrect_x_from) {
            return -1;
        } else if ($a->bgrect_x_from > $b->bgrect_x_from) {
            return 1;
        }

        return 0;
    }
}
