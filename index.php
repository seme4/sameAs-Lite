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


// Dev error reporting
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(-1);



/* This is all old things - clean up later

// initialise the web application
$app = new \SameAsLite\WebApp(array(
    'footerText' => 'This data is released under a CC0 License. Do with it as you will ;)',

    'about' => 'This is a test about section for seme4',
    'contact' => [
        'name' => 'Steven Martin',
        'email' => 'steve.martin@example.com',
        'telephone' => '0123456789'
    ]
));


// Add default options to the MySQLStore
\SameAsLite\Store\MySQLStore::setDefaultOptions([
    'host' => '127.0.0.1',
    'port' => '3306',
    'charset' => 'utf8',
    'dbName' => 'testdb'
]);



$app->addDataset(
    new \SameAsLite\Store\MySQLStore(
        'webdemo',
        [ // Using the default settings for some areas
            'username' => 'testuser',
            'password' => 'testpass'
        ]
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
        [ // Inputting all settings manually
            'username' => 'testuser',
            'password' => 'testpass',
            'dbName' => 'testdb',
            'host' => '127.0.0.1',
            'port' => '3306',
            'charset' => 'utf8'
        ]
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
*/


\SameAsLite\Store\SQLiteStore::setDefaultOptions([ 'location' => 'sameaslite-store.db' ]);

$app = \SameAsLite\SameAsLiteFactory::createWebApp('config.ini');

$app->run();
