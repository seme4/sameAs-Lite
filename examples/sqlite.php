<?php

/**
 * SameAs Lite Web Application
 *
 * Example for creating a sameAs Lite Web Application using SQLite
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




/*
    * Using SQLite is a quick way to get the service up and running and checking if everything is working
    * SQLite only takes one option (the location of the store), for quick testing pass in null or false 
    * and the Database will be created in memory
*/



// Initialise the web application
$app = new \SameAsLite\WebApp(array(
    'footerText' => 'This data is released under a CC0 License',

    'about' => 'This is a test about section for seme4',
    'contact' => [
        'name' => 'Steven Martin',
        'email' => 'steve.martin@example.com',
        'telephone' => '0123456789'
    ]
));


$store = \SameAsLite\SameAsLiteFactory::createStores('example-sqlite-config.ini')[0];


// Add the data sets
$app->addDataset(
    $store,
    [
        'slug'      => 'test1',
        'shortName' => 'Test 1',
    ]
);

$app->addDataset(
    new \SameAsLite\Store\SQLiteStore(
        'test2',
        [
            'location' => 'test.db'
        ]
    ),
    [
        'slug'      => 'test2',
        'shortName' => 'Test 2',
    ]
);


$app->run();
