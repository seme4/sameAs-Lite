<?php

/**
 * SameAs Lite Web Application
 *
 * Configure and launch the SameAs Lite RESTful Web Application.
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

require_once 'vendor/autoload.php';

// initialise the web application
$app = new \SameAsLite\WebApp(array(
    'footerText' => 'This data is released under a CC0 License. Do with it as you will ;)',
));

// TODO - think about abstraction of dbase connection, store and dataset. it
// might make more sense to initialise one PDO object and hand this to the
// various \SameAsLits\Store(s) which want to use it? otherwise we're
// duplicating the dbase information for each dataset. alternatively, add all
// details (incl dbase connection info) to the data passed to addDataset and
// get that method to instantiate the \SameAsLite\Store object?

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);


$app->addDataset(
    new \SameAsLite\Store\MySQLStore(
        'webdemo',
        'testuser',
        'testpass',
        'testdb',
        '127.0.0.1',
        '3306',
        'utf8'
    ),
    array(
        'slug'      => 'VIAF',
        'shortName' => 'VIAF',
        'fullName'  => 'Virtual International Authority File',
        'contact' => [
            'name' => 'Joe Bloggs',
            'email' => 'Joe.Bloggs@acme.org',
            'telephone' => '0123456789'
        ]
    )
);


$app->addDataset(
    new \SameAsLite\Store\MySQLStore(
        'table1',
        'testuser',
        'testpass',
        'testdb',
        '127.0.0.1',
        '3306',
        'utf8'
    ),
    array(
        'slug'      => 'test',
        'shortName' => 'Test Store',
        'fullName'  => 'Test store used for SameAs Lite development',
        'description' => 'There is lots of great info in here about the things you can do. Learn about places, cheese and crips and how these all relate!',
        'contact' => [
            'name' => 'Joe Bloggs',
            'email' => 'Joe.Bloggs@acme.org',
            'telephone' => '0123456789'
        ]
    )
);

$app->run();

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
