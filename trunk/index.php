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
    'titleHeader' => 'this is the title',
    'footerText' => 'This data is released under a CC0 License. Do with it as you will ;)',
));

/*$app->addStore(
    new \SameAsLite\Store('sqlite:test.sqlite', 'webdemo'),
    array(
        'slug'    => 'store-1',
        'name'    => 'My SameAs Store',
        'contact' => 'Joe Bloggs',
        'email'   => 'Joe.Bloggs@acme.org'
    )
);
*/
$app->addStore(
    new \SameAsLite\Store(
        'mysql:host=127.0.0.1;port=3306;charset=utf8',
        'webdemo',
        'root', //'testuser',
        'mysql', //'testpass',
        'testdb'
    ),
    array(
        'slug'    => 'store-2',
        'name'    => 'My SameAs Store in MySQL',
        'contact' => 'Joe Bloggs',
        'email'   => 'Joe.Bloggs@acme.org'
    )
);

$app->run();

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
