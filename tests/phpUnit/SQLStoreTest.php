<?php

/**
 * SameAs Lite
 *
 * This class tests the functionality of \SameAsLite\Store\SQLiteStore
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

// require_once 'StoreTest.php';
// use StoreTest;


/**
 * PHPUnit tests for the \SameAsLite\Store\SQLStore subclasses.
 */
abstract class SQLStoreTest extends \SameAsLite\Tests\StoreTest
{

    /** @var \PDO $pdo The PDO object used by the store */
    protected $pdo;


    /**
     * {@inheritDoc}
     */
    protected function addPairToStore($symbol1, $symbol2)
    {
        // As the input is controled, we know $symbol1 is always the canon
        $table = $this->store->getTableName();
        $query = "INSERT INTO $table (`canon`, `symbol`) VALUES (:symbol1, :symbol2)";

        $statement = $this->pdo->prepare($query);
        $statement->execute([ ':symbol1' => $symbol1, ':symbol2' => $symbol2 ]);
    }



    /**
     * {@inheritDoc}
     */
    protected function isPairInStore($symbol1, $symbol2)
    {
        $table = $this->store->getTableName();
        $query = "SELECT COUNT(*) AS 'count' FROM $table WHERE `canon` = :symbol1 AND `symbol` = :symbol2";

        $statement = $this->pdo->prepare($query);
        $statement->execute([ ':symbol1' => $symbol1, ':symbol2' => $symbol2 ]);

        $a = $statement->fetch(\PDO::FETCH_ASSOC);
        return ($a['count'] > 0);
    }

    /**
     * {@inheritDoc}
     */
    protected function isSymbolInStore($symbol)
    {
        $table = $this->store->getTableName();
        $query = "SELECT COUNT(*) as 'count' FROM $table WHERE `symbol` = :symbol";

        $statement = $this->pdo->prepare($query);
        $statement->execute([ ':symbol' => $symbol ]);

        $a = $statement->fetch(\PDO::FETCH_ASSOC);
        return ($a['count'] > 0);
    }
}
