<?php
/**
 * SameAs Lite
 *
 * This abstract class impliemnts methods in StoreInterface with generic SQL functions.
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
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace SameAsLite\Store;

/**
 * Store for SQLite databases
 */
class SQLiteStore extends \SameAsLite\Store\SQLStore
{


    /** @var $dbLocation The location of the database, or false if in memory */
    protected $dbLocation;

    /** @var array $defaultOptions The default options for a store */
    protected static $defaultOptions = [];

    /** @var string[] $availableOptions The options available for this store */
    protected static $availableOptions = [
        'location'
    ];


    // TODO
    // public static function getFactorySettings(){return [];}




    /**
     * {@inheritDoc}
     */
    public static function setDefaultOptions(array $options)
    {
        self::$defaultOptions = $options;
    }


    /**
     * {@inheritDoc}
     */
    public static function getAvailableOptions()
    {
        return self::$availableOptions;
    }


    /*
        * This is the constructor for a SameAs Lite SQLite store, validates and saves
        * settings. Once a Store object is created, call the connect() function to
        * establish connection to the underlying database.
        *
        * @param string $location  Location of the database file, if not supplied the database will be loaded into memory
        *
        * @throws \InvalidArgumentException If any parameters are deemed invalid
        */
    // TIHNS

    /**
     * Constructor takes the options for the SQLite store:
     * location  - The location of the DB file or null if stored in memory (optional)
     *
     * @throws \InvalidArgumentException If any parameters are deemed invalid
     */
    public function __construct($name, array $options = array())
    {
        if (is_array(self::$defaultOptions)) {
            // Merge arrays to get user defaults
            $options = array_merge(self::$defaultOptions, $options);
        }

        // Construct dsn string
        $dsn = 'sqlite:';
        if (isset($options['location']) && !!$options['location']) {
            $dsn .= $options['location'];
        } else {
            $dsn .= ':memory:';
            $options['location'] = false;
        }

        // ensure store name is sensible
        if (preg_match('/[^a-zA-Z0-9_]/', $name)) {
            throw new \InvalidArgumentException(
                'Invalid store name: only characters A-Z, a-z, 0-9 and underscores are permitted.'
            );
        }

        // save config
        $this->dsn = $dsn;
        $this->storeName = $name;
        $this->dbLocation = $options['location'];
    }


    /**
     * {@inheritDoc}
     */
    public function connect()
    {
        parent::connect();

        // For debugging and sanity, make PDO report any problems, not fail silently
        $this->pdoObject->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (!$this->isInit()) {
            $this->init(); // Init the database if required
        }
    }



    /**
     * {@inheritDoc}
     *
     * @throws \Exception When store cannot be created
     */
    public function init()
    {
        // Attempt to create the tables in the given DB

        // try to create tables for this store, if they don't exist
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->getTableName()}
                (canon TEXT, symbol TEXT PRIMARY KEY)
                WITHOUT ROWID;
                CREATE INDEX IF NOT EXISTS {$this->getTableName()}_idx
                ON {$this->getTableName()} (canon);";

            $this->pdoObject->exec($sql);
        } catch (\PDOException $e) {
            throw new \Exception(
                'Failed to create Store with name ' . $this->storeName .
                $e->getMessage()
            );
        }
    }


    /**
     * {@inheritDoc}
     */
    public function isInit()
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->getTableName()}'";
        $a = $this->pdoObject->query($sql)->fetchAll();
        return count($a) > 0;
    }



    /**
     * {@inheritDoc}
     */
    public function deleteStore()
    {
        $sql = "DROP INDEX IF EXISTS `{$this->getTableName()}_idx`";
        $this->pdoObject->exec($sql);
        $sql = "DROP TABLE IF EXISTS `{$this->getTableName()}`";
        $this->pdoObject->exec($sql);
    }



    /**
     * {@inheritDoc}
     */
    public function emptyStore()
    {
        try {
            $sql = "DELETE FROM `{$this->getTableName()}`";
            $this->pdoObject->exec($sql);
        } catch (\PDOException $e) {
            $this->error("Database failure to empty store", $e);
        }
        /*
            Just recreate table instead?
            $this->deleteStore();
            $this->init();
        */
    }
}
