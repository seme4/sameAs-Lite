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

require_once 'SQLStoreTest.php';

/**
 * PHPUnit tests for the \SameAsLite\Store\SQLiteStore class.
 */
class SQLiteStoreTest extends SQLStoreTest
{

	/** @var string $location The location of the SQLite database, or null to use :memory: */
	private $location = null;


	/**
     * {@inheritDoc}
     */
	protected static function getConfig(){
		if (array_key_exists('SQLITE_LOCATION', $GLOBALS)) {
		    $this->location = $GLOBALS['SQLITE_LOCATION'];
		}

		parent::getConfig();
	}

	/**
	 * Creates the store
	 * without using the Store functions
	 */
	protected function createStore(){
		$this->store = new \SameAsLite\Store\SQLiteStore($this->storeName, [ 'location' => $this->location ]);
		$this->store->connect();
		$this->store->init();

		$this->pdo = $this->store->getPDOObject();
	}

	/**
	 * Deletes the store
	 * without using the Store functions
	 */
	protected function deleteStore(){
		$table = $this->store->getTableName();

		$sql = "DROP INDEX IF EXISTS `{$table}_idx`";
        $this->pdo->exec($sql);
        $sql = "DROP TABLE IF EXISTS `$table`";
        $this->pdo->exec($sql);

		$this->store->disconnect();

		$this->pdo = null;
		unset($this->store);
	}


}