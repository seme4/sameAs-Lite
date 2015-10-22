<?php

/**
 * SameAs Lite
 *
 * This class tests the functionality of \SameAsLite\Store subclasses
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

namespace SameAsLite\Tests;

/**
 * PHPUnit tests for the \SameAsLite\Store class.
 */
abstract class StoreTest extends \PHPUnit_Framework_TestCase
{

    /** @var \SameAsLite\StoreInterface $store The store used for each test */
    protected $store;

    /** @var string $storeName The name that should be used for the store */
    protected $storeName = 'test';

    /** @var array $initalPairs The pairs initally in the store before running a test */
    protected $initalPairs = [
        ['a', 'a'],
        ['a', 'aa'],
        ['a', 'aaa'],
        ['crisps', 'crisps'],
        ['pizza', 'pizza']
    ];


    /**
     * Sets up the variables for this store (normally from the XML config)
     *
     */
    protected static function getConfig()
    {
        if (array_key_exists('STORE_NAME', $GLOBALS)) {
            $this->storeName = $GLOBALS['STORE_NAME'];
        }
    }


    /**
     * Creates the store
     * without using the Store functions
     */
    abstract protected function createStore();

    /**
     * Deletes the store
     * without using the Store functions
     */
    abstract protected function deleteStore();


    /**
     * Takes a given 2D array of pairs and adds them to the store
     * without using the Store functions
     *
     * @param string[][] $pairs The array for pairs in the form [ canon, symbol ]
     */
    protected function addPairsToStore(array $pairs)
    {
        foreach ($pairs as $pair) {
            $this->addPairToStore($pair[0], $pair[1]);
        }
    }


    /**
     * Takes a given pair of values and adds them to the store
     * without using the Store functions
     *
     * @param string $symbol1 The first symbol (canon)
     * @param string $symbol2 The second symbol
     */
    abstract protected function addPairToStore($symbol1, $symbol2);


    /**
     * Takes a given pair of values and returns whether they are in the store
     * without using the Store functions
     *
     * @param string $symbol1 The first symbol (canon)
     * @param string $symbol2 The second symbol
     * @return bool Whether the pair exists in the store
     */
    abstract protected function isPairInStore($symbol1, $symbol2);

    /**
     * Takes a given 2D array of pairs and returns whether they are in the store
     * without using the Store functions
     *
     * @param string[][] $pairs The array for pairs in the form [ canon, symbol ]
     * @return bool Whether the pairs exists in the store
     */
    protected function arePairsInStore(array $pairs)
    {
        $i = count($pairs);
        $val = true;
        while ($val && --$i >= 0) {
            $val = $this->isPairInStore($pairs[$i][0], $pairs[$i][1]);
        }
        return $val;
    }

    /**
     * Takes a given symbol and checks whether it is in the store
     * without using the Store functions
     *
     * @param string $symbol The symbol to check
     * @return bool Whether the symbol exists in the store
     */
    abstract protected function isSymbolInStore($symbol);



    /**
     * Set up class
     */
    public static function setUpBeforeClass()
    {
        self::getConfig();

        parent::setUpBeforeClass();
    }


    /**
     * Set up function
     */
    protected function setUp()
    {
        $this->createStore();
        $this->addPairsToStore($this->initalPairs);

        parent::setUp();
    }

    /**
     * Tear down function
     */
    protected function tearDown()
    {
        $this->deleteStore();

        parent::tearDown();
    }


    /**
     * Asserts if the arrays are equal, irrespective of ordering
     *
     * @param array $expected The expected array
     * @param array $actual   The actaul array
     */
    protected function assertArraysEqualNoOrder(array $expected, array $actual)
    {
        $this->assertEmpty(array_merge(array_diff($expected, $actual), array_diff($actual, $expected)));
    }

    /**
     * Checks whether the store will report itself as connected
     *
     * @covers \SameAsLite\StoreInterface::__construct
     * @covers \SameAsLite\StoreInterface::connect
     * @covers \SameAsLite\StoreInterface::isConnected
     * @covers \SameAsLite\StoreInterface::isInit
     */
    public function testStoreWillInitAndConnect()
    {
        $this->assertTrue($this->store->isConnected());
        $this->assertTrue($this->store->isInit());
    }



    /**
     * Check the store is deleted (isInit() === false)
     *
     * @covers \SameAsLite\StoreInterface::deleteStore
     * @covers \SameAsLite\StoreInterface::isInit
     */
    public function testCanDeleteStore()
    {
        $this->store->deleteStore();
        $this->assertFalse($this->store->isInit());
    }

    /**
     * Store will return the correct name
     *
     * @covers \SameAsLite\StoreInterface::getStoreName
     */
    public function testCanGetStoreName()
    {
        $this->assertEquals($this->storeName, $this->store->getStoreName());
    }



    /**
     * Store will query for a symbol correctly
     *
     * @covers \SameAsLite\StoreInterface::querySymbol
     */
    public function testCanQuerySymbols()
    {
        $result = $this->store->querySymbol('aaa');


        $this->assertArraysEqualNoOrder([
            'aaa',
            'aa',
            'a'
        ], $result);
    }


    /**
     * Store will correctly search
     *
     * @covers \SameAsLite\StoreInterface::search
     */
    public function testCanSearchForPairs()
    {
        $result = $this->store->search('aaa');

        $this->assertArraysEqualNoOrder([
            'aaa',
            'aa',
            'a'
        ], $result);
    }



    /**
     * Store will assert a pair correctly
     *
     * @covers \SameAsLite\StoreInterface::assertPair
     */
    public function testCanAssertPair()
    {
        $result = $this->store->assertPair('crisps', 'potato chips');

        $this->assertTrue($result);

        $this->assertTrue($this->isPairInStore('crisps', 'potato chips'));
    }




    /**
     * Store will assert pairs correctly
     *
     * @covers \SameAsLite\StoreInterface::assertPairs
     */
    public function testCanAssertPairs()
    {
        $pairs = [
            ["b", "bb"],
            ["b", "bbb"],
            ["b", "bbbb"],
            ["z", "z"]
        ];
        $this->store->assertPairs($pairs);

        $this->assertTrue($this->arePairsInStore($pairs));
    }


    /**
     * Store will assert pairs given is TSV form correctly
     *
     * @covers \SameAsLite\StoreInterface::assertTSV
     */
    public function testCanAssertTSVString()
    {
        // These pairs in TSV form
        $tsv = "b\tbb\nb\tbbb\nb\tbbbb\nz\tz";
        $pairs = [
            ["b", "bb"],
            ["b", "bbb"],
            ["b", "bbbb"],
            ["z", "z"]
        ];

        $this->store->assertTSV($tsv);

        $this->assertTrue($this->arePairsInStore($pairs));
    }



    /**
     * Store will remove symbols correctly
     *
     * @covers \SameAsLite\StoreInterface::removeSymbol
     */
    public function testCanRemoveSymbol()
    {
        $symbol = 'aaa';
        $this->store->removeSymbol($symbol);


        $this->assertFalse($this->isSymbolInStore($symbol));
    }


    /**
     * Store will remove multiple symbols correctly
     *
     * @covers \SameAsLite\StoreInterface::removeSymbols
     */
    public function testCanRemoveSymbols()
    {
        $symbols = ['aaa', 'aa', 'pizza'];
        $this->store->removeSymbols($symbols);

        foreach ($symbols as $symbol) {
            $this->assertFalse($this->isSymbolInStore($symbol));
        }
    }

    /**
     * Store will remove a bundle correctly, without using the canon as the symbol
     *
     * @covers \SameAsLite\StoreInterface::removeBundle
     */
    public function testCanRemoveBundle()
    {
        $symbol = 'aaa';
        $bundle = ['a', 'aa', 'aaa'];
        $this->store->removeBundle($symbol);

        foreach ($bundle as $symbol) {
            $this->assertFalse($this->isSymbolInStore($symbol));
        }
    }


    /**
     * Store will set the canon correctly
     *
     * @covers \SameAsLite\StoreInterface::setCanon
     */
    public function testCanSetCanon()
    {
        $this->store->setCanon('aaa');

        $this->assertTrue($this->arePairsInStore([
            ['aaa', 'aaa'],
            ['aaa', 'aa'],
            ['aaa', 'a']
        ]));
    }

    /**
     * Store will not change the canon if restrict is set to true
     *
     * @covers \SameAsLite\StoreInterface::setCanon
     * @expectedException \Exception
     */
    public function testCannotSetCanonWhenRestrictIsSet()
    {
        $this->store->setCanon('aaa', true);
    }


    /**
     * Can get the correct canon from a given symbol
     *
     * @covers \SameAsLite\StoreInterface::getCanon
     */
    public function testCanGetCanon()
    {
        $canon = $this->store->getCanon('aa');

        $this->assertEquals('a', $canon);
    }



    /**
     * Can get the all canons from the store
     *
     * @covers \SameAsLite\StoreInterface::getCanon
     */
    public function testCanGetAllCanons()
    {
        $canons = $this->store->getAllCanons();

        $this->assertArraysEqualNoOrder([
            'a',
            'crisps',
            'pizza'
        ], $canons);
    }


    /**
     * Can empty the store
     *
     * @covers \SameAsLite\StoreInterface::emptyStore
     * @covers \SameAsLite\StoreInterface::isInit
     */
    public function testCanEmptyStore()
    {
        $this->store->emptyStore();

        $this->assertTrue($this->store->isInit());


        // Check none of the inital pairs exist in the store
        $vals = array_map(function ($a) {
            return $this->isPairInStore($a[0], $a[1]);
        }, $this->initalPairs);

        $this->assertFalse(in_array(true, $vals));
    }


    /**
     * Store will dump the correct pairs
     *
     * @covers \SameAsLite\StoreInterface::dumpPairs
     */
    public function testCanDumpPairs()
    {
        $pairs = $this->store->dumpPairs();

        $this->assertEquals($this->initalPairs, $pairs);
    }

    /**
     * Can dump pairs as TSV
     *
     * @covers \SameAsLite\StoreInterface::dumpTSV
     */
    public function testCanDumpTSVString()
    {
        $tsv = $this->store->dumpTSV();

        $expected = implode("\n", array_map(function ($a) {
            return implode("\t", $a);
        }, $this->initalPairs));

        $this->assertEquals($expected, $tsv);
    }

    /**
     * Can get the correct statistics
     *
     * @covers \SameAsLite\StoreInterface::statistics
     */
    public function testStatistics()
    {
        $stats = $this->store->statistics();

        $this->assertEquals([
            'symbols' => 5,
            'bundles' => 3
        ], $stats);
    }
}
