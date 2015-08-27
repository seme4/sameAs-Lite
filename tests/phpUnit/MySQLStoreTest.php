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
 * PHPUnit tests for the \SameAsLite\Store\MySQLStore class.
 */
class MySQLStoreTest extends SQLStoreTest
{

	/** @var string $username The username to use for testing the Store, can be overriden in the xml config */
	protected $username = 'testuser';

	/** @var string $password The password to use for testing the Store, can be overriden in the xml config */
	protected $password = 'testpass';

	/** @var string $dbName The database name to use for testing the Store, can be overriden in the xml config */
	protected $dbName  = 'testdb';


    /**
     * {@inheritDoc}
     */
	protected static function getConfig(){
		if (array_key_exists('MYSQL_USERNAME', $GLOBALS)) {
		    $this->username = $GLOBALS['MYSQL_USERNAME'];
		}

		if (array_key_exists('MYSQL_PASSWORD', $GLOBALS)) {
		    $this->password = $GLOBALS['MYSQL_PASSWORD'];
		}

		if (array_key_exists('MYSQL_DB_NAME', $GLOBALS)) {
		    $this->dbName = $GLOBALS['MYSQL_DB_NAME'];
		}

		parent::getConfig();
	}

	/**
	 * Creates the store
	 * without using the Store functions
	 */
	protected function createStore(){
		$this->store = new \SameAsLite\Store\MySQLStore($this->storeName, [ 'username' => $this->username, 'password' => $this->password, 'dbName' => $this->dbName ]);
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

        $sql = "DROP TABLE IF EXISTS `$table`";
        $this->pdo->exec($sql);

		$this->store->disconnect();

		$this->pdo = null;
		unset($this->store);
	}


}