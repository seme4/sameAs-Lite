<?php
/**
 * SameAs Lite
 *
 * Helper class that provides some simple functions
 *
 * @package   SameAsLite
 * @author    Seme4 Ltd <sameAs@seme4.com>
 * @copyright 2009 - 2014 Seme4 Ltd
 * @link      http://www.seme4.com
 * @version   0.0.1
 * @license   MIT Public License
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace SameAsLite;

/**
 * Interface that must be implimented for all SameAsLite Stores
 */
class Helper
{

    /**
     * Initialise dummy $_SERVER parameters if not set (ie command line).
     */
    public static function initialiseServerParameters()
    {
        global $argv;

        if (!isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = (isset($argv[1])) ? $argv[1] : '/';
        }
        if (!isset($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = getHostByAddr('127.0.0.1');
        }
        if (!isset($_SERVER['SERVER_PORT'])) {
            $_SERVER['SERVER_PORT'] = 80;
        }
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $_SERVER['HTTP_ACCEPT'] = 'text/html';
        }
    }

    /**
     * Check if this is a call for RDF or turtle
     *
     * @param string $mimeBest The best match for the mime type (from conNeg)
     *
     * @return boolean $isRDFRequest Return value (true if mimeType is RDF or Turtle)
     */
    public static function isRDFRequest($mimeBest)
    {
        if (isset($mimeBest) && in_array($mimeBest, array('application/rdf+xml', 'text/turtle', 'application/x-turtle')))
        {
            return true;
        } else {
            return false;
        }
    }

    /**
     * This function turns strings into HTML links, if appropriate
     *
     * @param string $item The item to convert
     * @return string The original item, or a linkified version of the item
     */
    public static function linkify($item)
    {
        $return = $item;

        if (
            !is_array($item) &&
            (
                //URL
                filter_var($item, FILTER_VALIDATE_URL)
                //email and some other linkable url schemes
                || strpos($item, 'mailto:') === 0
                || strpos($item, 'ftp:') === 0
                || strpos($item, 'news:') === 0
                || strpos($item, 'nntp:') === 0
                || strpos($item, 'telnet:') === 0
                || strpos($item, 'wais:') === 0
            )
        ) {
            $return = '<a href="' . $item . '">' . $item . '</a>';
        }

        return $return;
    }

    /**
     * Count array dimensions
     *
     * @param array $array Array
     *
     * @return integer $return Number of dimensions of the array
     */
    public static function countdim(array $array)
    {
        if (is_array(reset($array)))
        {
            $return = self::countdim(reset($array)) + 1;
        }
        else
        {
            $return = 1;
        }
        return $return;
    }

    /**
     * All incoming data must be escaped for output on the webpage
     *
     * @param array $data    The rows to output
     * @param array $headers Column headers
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    public static function escapeInputArray(&$value, $key) {
        // value(s)
        if (!is_array($value)) {
            $value = htmlspecialchars($value);
        } else {
            // clean recursively
            foreach ($value as $k => &$v) {
                self::escapeInputArray($v, $k);
            }
        }
        // key
        // if (!is_numeric($key)) {
        //     $key = htmlspecialchars($key);
        // }
    }

}
