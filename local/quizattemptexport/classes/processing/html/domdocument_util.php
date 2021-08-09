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
 * Utility class for using DomDocument
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\html;

defined('MOODLE_INTERNAL') || die();

class domdocument_util {

    /**
     * Sanitizes HTML in way that you may str_replace parts of it after
     * you have saved some parts of it using {@see domdocument_util::save_html()}
     *
     * Mainly it removes new lines...
     *
     * @param string $html
     * @return string
     */
    public static function prepare_html(string $html) : string {

        $html = str_replace("\n", '', $html);

        $dom = self::initialize_domdocument($html);
        return self::save_html($dom);
    }

    /**
     * Initializes an instance of \DOMDocument with the given HTML snippet. Ensures
     * that encoding will be correctly set to UTF-8 and does some other required
     * configuration.
     *
     * @param string $htmlsnippet
     * @return \DOMDocument
     */
    public static function initialize_domdocument(string $htmlsnippet) : \DOMDocument {

        $dom = new \DOMDocument();

        // Load html and prepend with XML-Tag to ensure DOMDocument uses
        // correct encoding.
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlsnippet, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR);

        // Remove encoding XML-Tag
        foreach ($dom->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $dom->removeChild($item);
            }
        }

        // Set encoding in case it is required later on.
        $dom->encoding = 'UTF-8';

        return $dom;
    }


    /**
     * Wrapper for DOMDocument::saveHTML that allows us to
     * postprocess the HTML we save.
     *
     * Does the following:
     * * Removes new lines from the saved HTML that may be added by a buggy DOMDocument
     *
     *
     * @param \DOMDocument $dom
     * @param \DOMNode|null $node
     * @return string
     */
    public static function save_html(\DOMDocument $dom, \DOMNode $node = null) {

        $html = $dom->saveHTML($node);

        // https://stackoverflow.com/questions/53288092/how-to-use-php-domdocument-savehtmlnode-without-added-whitespace
        // Fix bug in php version < 7.3.0.alpha3 (https://bugs.php.net/bug.php?id=76285) where new
        // lines are added behind each node when they should not be added.
        $html = str_replace("\n", '', $html);

        return $html;
    }

}
