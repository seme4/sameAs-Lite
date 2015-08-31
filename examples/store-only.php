<?php

/**
 * SameAs Lite Web Application
 *
 * Example for creating a sameAs Store and manipulating it without the WebApp wrapper
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
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

require_once '../vendor/autoload.php';


// Create the store
$store = new \SameAsLite\Store\SQLiteStore(
    'example',
    [
        'location' => false // Loads database in memory
    ]
);

// Remember to connect
$store->connect();

// Manipulate the Store
$store->assertPair('a', 'aa');
$store->assertPair('a', 'aaa');

$pairs = $store->dumpPairs();

var_dump($pairs);

echo "\n\n";


echo ($pairs == [
    ['a', 'a'],
    ['a', 'aa'],
    ['a', 'aaa']
]) ? 'true' : 'false';

echo "\n\n";
