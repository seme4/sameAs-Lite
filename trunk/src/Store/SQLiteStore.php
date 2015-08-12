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

class MySQLStore extends \SameAsLite\Store\SQLStore {


    /** @var $$dbLocation The location of the database, or false if in memory **/
    protected $dbLocation;



    /**
     * This is the constructor for a SameAs Lite store, validates and saves
     * settings. Once a Store object is created, call the connect() function to
     * establish connection to the underlying database.
     *
     * @param string $name      Name of this store, will also be used as the database table name
     * @param string $location  Location of the database file, if not supplied the database will be loaded into memory
     *
     * @throws \InvalidArgumentException If any parameters are deemed invalid
     */
    public function __construct($name, $location = null){

        // Construct dsn string
        $dsn = 'sqlite:';
        if(isset($location)){
            $dsn .= realpath($location);
        }else{
            $dsn .= ':memory:';
            $location = false;
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
        $this->dbLocation = $location;
    }


    public function connect(){
        parent::connect();

        // For debugging and sanity, make PDO report any problems, not fail silently
        $this->pdoObject->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            $this->pdoObject->exec('USE ' . $this->dbName);
        } catch (\PDOException $e) {
            throw new \Exception(
                'Failed to access database named ' . $this->dbName . ' // ' .
                $e->getMessage()
            );
        }

        if(!$this->isInit()){
            $this->init(); // Init the database if required
        }
    }


    public function init(){
        // Attempt to create the tables in the given DB     

        // try to create tables for this store, if they don't exist
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->getTableName()}
                (canon TEXT, symbol TEXT PRIMARY KEY)
                WITHOUT ROWID;
                CREATE INDEX IF NOT EXISTS ' . $this->storeName . '_idx'
                ON {$this->getTableName()} (canon);";

            $this->pdoObject->exec($sql);
        }catch(\PDOException $e) {
            throw new \Exception(
                'Failed to create Store with name ' . $this->storeName .
                $e->getMessage()
            );
        }
    }

    public function isInit(){
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->getTableName()}'";
        $results = $this->pdoObject->query($sql);
        return ($results->rowCount() > 0);
    }



    public function deleteStore(){
        $sql = "DROP TABLE IF EXISTS `{$this->getTableName()}`; DROP INDEX IF EXISTS `{$this->getTableName()}_idx`;";
        $this->pdoObject->exec($sql);
    }


    public function emptyStore(){
        try{
            $sql = "DELETE FROM `{$this->getTableName()}`";
            $this->pdoObject->exec($sql);
        } catch (\PDOException $e) {
            $this->error("Database failure to empty store", $e);
        }
    }

}
