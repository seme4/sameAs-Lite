<?php

/**
 * SameAs Lite
 *
 * This class tests the functionality of \SameAsLite\Store
 *
 * @package   SameAsLite
 * @author    Seme4 Ltd <sameAs@seme4.com>
 * @copyright 2009 - 2014 Seme4 Ltd
 * @link      http://www.seme4.com
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

namespace SameAsLite;

/**
 * PHPUnit tests for the \SameAsLite\Store class.
 */
class StoreTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Check that an exception is raised if store name is invalid
     *
     * @covers            \SameAsLite\Store::__construct
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionIsRaisedForInvalidDbaseTableName()
    {
        new \SameAsLite\Store(null);
    }

    /**
     * Check the Store can be successfully created
     *
     * @covers \SameAsLite\Store::__construct
     */
    public function testStoreCanBeConstructedForValidConstructorArguments()
    {
        $s = new Store('test');
        $this->assertInstanceOf('SameAsLite\\Store', $s);
    }

    /**
     * Check an empty Store can be successfully dumped
     *
     * @covers \SameAsLite\Store::dumpStore
     */
    public function testAnEmptyStoreCanBeDumped()
    {
        $s = new Store('test');
        $expected = array("Bundle\tFlags\tSymbol");
        $result = $s->dumpStore();
        $this->assertEquals($expected, $result);
    }

}

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
