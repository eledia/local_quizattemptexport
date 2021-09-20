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
 * Postprocessing implementation for qtype_stack
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\html\methods;

use local_quizattemptexport\processing\html\domdocument_util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class stack extends base {

    /**
     *
     * @param string $questionhtml
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function process(string $questionhtml, \quiz_attempt $attempt, int $slot): string {
        global $CFG;

        // Get DOM and XPath.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        // Get any plot images.
        $plots = $xpath->query('//div[@class="stack_plot"]/img');
        foreach ($plots as $plot) {
            /** @var \DOMElement $plot */

            // Get value of source attribute and determine local path and file type.
            $src = $plot->getAttribute('src');
            $parts = explode('/', $src);
            $filename = array_pop($parts);
            $path = $CFG->dataroot . '/stack/plots/' . $filename;
            $parts = explode('.', $filename);
            $filetype = array_pop($parts);

            // Check if file exists within local path.
            if (!file_exists($path)) {

                // Use fallback image instead.
                $path = $CFG->dirroot . '/local/quizattemptexport/pix/edit-delete.png';
                $filetype = 'png';
            }

            // Do some additional processing.
            if ($filetype == 'svg') {

                // Prepare correct mime type vale.
                $filetype = 'svg+xml';

                // Load SVG into a DomDocument to retrieve
                // view box definition from SVG root element.
                $svgdom = new \DOMDocument();
                $svgdom->load($path);
                $root = $svgdom->getElementsByTagName('svg')[0];
                $viewbox = $root->getAttribute('viewBox');
                $parts = explode(' ', $viewbox);
                unset($svgdom);

                // Get width/height for use in IMG element
                $width = $parts[2];
                $height = $parts[3];

            } else {

                // Get width/height for use in IMG element
                $imgsize = getimagesize($path);
                $width = $imgsize[0];
                $height = $imgsize[1];
            }

            // Construct data url and replace original value with data url.
            $filecontent = file_get_contents($path);
            $dataurl = 'data:image/' . $filetype . ';base64,' . base64_encode($filecontent);
            $plot->setAttribute('src', $dataurl);

            // Update width and height with the values we determined. This
            // is necessary as the IMG element by default only contains a
            // definition for its width but not its height. When the image
            // is an SVG this will lead to wkhtmltopdf omitting the image
            // entirely...
            $plot->setAttribute('width', $width);
            $plot->setAttribute('height', $height);
        }

        // Save modified HTML and return.
        return domdocument_util::save_html($dom);
    }


    public static function get_css() : string {
        return '
            .que.stack .questiontestslink {
                display: none;
            }
        ';
    }

}
