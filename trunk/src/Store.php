<?php
/**
 * SameAs Lite
 *
 * This class provides a specialised storage capability for SameAs pairs.
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

namespace SameAsLite;

ini_set('max_execution_time', 1800);

/**
 * Provides storage and management of SameAs relationships.
 * TODO Note that the is the possibility/probability of there being symbols in the symbol table
 *      that don't have entries in the data tables.
 *      That is, if some inconsistency arises.
 *      At the moment we assume that no such inconsistency occurs.
 *      If there are such symbols, then the system will largely ignore them.
 */
class Store
{

    const CANON = 1;
    const NOTCANON = 0;

    /** @var string $dbType Indicates the type of database, ie sqlite|mysql */
    private $dbType = null;

    /** @var \PDO $dbHandle The PDO object for the DB, once opened */
    protected $dbHandle;

    /** @var string $dsn The PDO dataset connection strin */
    private $dsn = null;

    /** @var string|null $dbUser Database usename */
    private $dbUser = null;

    /** @var string|null $dbPass Database password */
    private $dbPass = null;

    /**
     * @var string|null $dbName Optional database name, used where the PDO
     * connection is to a system eg MySQL which supports multiple databases
     */
    protected $dbName = null;

    /**
     * This is the constructor for a SameAs Lite store, validates and saves
     * settings. Once a Store object is created, call the connect() function to
     * establish connection to the underlying database.
     *
     * @param string $dsn    The PDO database connection string
     * @param string $name   Name of this store (used to define database tables)
     * @param string $user   Optional database username
     * @param string $pass   Optional database password
     * @param string $dbName Optional database name
     *
     * @throws \InvalidArgumentException If any parameters are deemed invalid
     */
    public function __construct($dsn, $name, $user = null, $pass = null, $dbName = null)
    {

        // get dbase type
        if (($p = strpos($dsn, ':')) !== false) {
            $this->dbType = substr($dsn, 0, $p);
        } else {
            throw new \InvalidArgumentException('Invalid PDO database connection string.');
        }

        // ensure dbase type is one we can handle
        $acceptable = array('mysql', 'sqlite');
        if (!in_array($this->dbType, $acceptable)) {
            throw new \InvalidArgumentException(
                'Invalid PDO database connection string, only "mysql" and "sqlite" databases are supported.'
            );
        }

        // ensure we have correct info if mysql
        if ($this->dbType == 'mysql') {
            if ($user == null) {
                throw new \InvalidArgumentException('You must specify the $user parameter for mysql databases.');
            }
            if ($pass == null) {
                throw new \InvalidArgumentException('You must specify the $pass parameter for mysql databases.');
            }
            if ($dbName == null) {
                throw new \InvalidArgumentException('You must specify the $dbName parameter for mysql databases.');
            }
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
        $this->symbolTable = $name."_symbols";
        $this->dataTable = $name."_data";
        $this->dbUser = $user;
        $this->dbPass = $pass;
        $this->dbName = $dbName;
    }

    /**
     * Establish connection to database, if not already made
     * @throws \Exception Exception is thrown if connection fails or table cannot be accessed/created.
     */
    public function connect()
    {

        // skip if we've already connected
        if ($this->dbHandle != null) {
            return null;
        }

        // connect and authenticate to database
        try {
                $this->dbHandle = new \PDO($this->dsn, $this->dbUser, $this->dbPass);
        } catch (\PDOException $e) {
            throw new \Exception(
                'Unable to to connect to ' . $this->dbType . ' // ' .
                $e->getMessage()
            );
        }

        // For debugging and sanity, make PDO report any problems, not fail silently
        $this->dbHandle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // if mysql, we need to select the appropriate database
        if ($this->dbType == 'mysql') {
            try {
                $this->dbHandle->exec('USE ' . $this->dbName);
            } catch (\PDOException $e) {
                throw new \Exception(
                    'Failed to access database named ' . $this->dbName . ' // ' .
                    $e->getMessage()
                );
            }
        }

        // try to create tables for this store, if they don't exist
        try {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->symbolTable .
                   ' (id BIGINT, symbol VARCHAR(256), PRIMARY KEY (id), KEY symbol (symbol) ); ' .
                   'CREATE TABLE IF NOT EXISTS ' . $this->dataTable .
                   ' (id BIGINT, bundle BIGINT, flags BIGINT DEFAULT \'0\', PRIMARY KEY (id), KEY bundle (bundle) ); ';
            $this->dbHandle->exec($sql);
        } catch (\PDOException $e) {
            throw new \Exception(
                'Failed to create Store with name ' . $this->storeName . " query: =$sql=" .
                $e->getMessage()
            );
        }

    }

    /**
     * This is the destructor for a sameAsLite store, destroys the PDO object
     *
     * @throws \PDOException An exception could be thrown if we fail to destroy
     * the database.
     */
    public function __destruct()
    {
        $this->dbHandle = null;
    }

    /**
     * This is the simple method to query a store with a symbol
     *
     * Looks up the given symbol in a store, and returns the bundle with all
     * the symbols in it (including the one given). The bundle is ordered with
     * canon(s) first, then non-canons, in alpha order of symbols.
     *
     * @param string $symbol The symbol to be looked up
     *
     * @return string[] An array of the symbols, which is singleton of the given symbol of nothing was found
     */
    public function querySymbol($symbol)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            // Do we have it?
            $id = $this->queryGetSymbolID($symbol);
            $b = $this->queryGetBundleID($id);
            if ($b === null) {
                // No we don't have it already
                $output = array($symbol);
            } else {
                // Yes we do have it already
                // TODO All of this can probably be done in one query, including sorting
                $statement = $this->dbHandle->prepare(
                    "SELECT id, flags FROM $this->dataTable WHERE bundle = '$b' ORDER BY flags DESC;"
                );
                $statement->execute();
                $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
                $output = array();
                // TODO It might be nice to sort the symbols somehow - canon comes first is the only order at the moment
                foreach ($result as $row) {
                    $output[] = $this->queryGetSymbolSymbol($row['id']);
                }
            }
        } catch (\PDOException $e) {
            $this->error("Query symbol '$s' failed", $e);
        }

        return $output;
    }

    /**
     * Search for symbols in this store that contain the given pattern
     *
     * Looks up the given symbol in a store, and returns the bundle with all
     * the symbols in it (including the one given).
     * The bundle is ordered with canon(s) first, then non-canons, in alpha
     * order of symbols.
     *
     * @param string $string The string to be looked up
     *
     * @return string[] An array of the symbols, which is enpty if none were found.
     */
    public function search($string)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            // Do we have it at all?
            $statement = $this->dbHandle->prepare(
                "SELECT symbol FROM $this->storeName WHERE symbol LIKE :string ORDER BY symbol;"
            );
            $statement->bindValue(':string', "%$string%", \PDO::PARAM_STR);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_NUM);
            $output = array();
            foreach ($result as $row) {
                $output[] = $row[0];
            }
        } catch (\PDOException $e) {
            $this->error("Search for '$string' failed", $e);
        }

        return $output;
    }

    /**
     * Put a possibly new pair into the store
     *
     * Needs to cope with a number of situations:
     *        Both symbols were already in the store
     *          - do nothing
     *        The first symbol was not in the store
     *          - add it to the bundle of the second symbol
     *        The second symbol was not in the store
     *          - add it to the bundle of the first symbol
     *        Both symbols were in different bundles
     *          - add the symbols from the bundle of the second symbol to the
     *            first bundle, none of them as canons
     *            (leaving the canons situation as it is in bundle 1)
     *
     * @param string $symbol1 The first symbol
     * @param string $symbol2 The second symbol
     */
    public function assertPair($symbol1, $symbol2)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            // Are the symbols already in the store?
            $symbolID1 = $this->queryGetSymbolID($symbol1);
            $symbolID2 = $this->queryGetSymbolID($symbol2);
            $bundleID1 = $this->queryGetBundleID($symbolID1);
            $bundleID2 = $this->queryGetBundleID($symbolID2);
            // These now have the IDs, or null if there wasn't one

            if ($symbolID1 === null && $symbolID2 === null) {
                // Both symbols are new - create a new bundle
                // First we find the maximum Bundle identifer, so we can use the next one up
                // And make the Canon $symbol1
                $symbolID = 1 + $this->queryGetMaxSymbolID(); // TODO Should probably use the auto increment
                $bundle = 1 + $this->queryGetMaxBundle(); // TODO Should probably use the auto increment
                $this->queryAssertSymbol($symbol1, $symbolID);
                $this->queryAssertSymbol($symbol2, $symbolID+1);
                $this->queryAssertRow($symbolID, $bundle, self::CANON);
                $this->queryAssertRow($symbolID+1, $bundle, self::NOTCANON);
            } elseif ($symbolID1 === null) {
                // Insert new $symbol1 into existing $bundleID2
                // No need to do anything about canons
                $symbolID = 1 + $this->queryGetMaxSymbolID(); // TODO Should probably use the auto increment
                $this->queryAssertSymbol($symbol1, $symbolID);
                $this->queryAssertRow($symbolID, $bundleID2, self::NOTCANON);
            } elseif ($symbolID2 === null) {
                // Insert new $symbol2 into existing $bundleID1
                // No need to do anything about canons
                $symbolID = 1 + $this->queryGetMaxSymbolID(); // TODO Should probably use the auto increment
                $this->queryAssertSymbol($symbol2, $symbolID);
                $this->queryAssertRow($symbolID, $bundleID1, self::NOTCANON);
            } elseif ($bundleID1 === $bundleID2) {
                // They were both already in the same bundle
                // Do nothing
            } else {
                // They are in different bundles
                // So join the two bundles - set all of bundle 2 to be in bundle 1
                // Canon will be the canon of bundle 1, since the changed ones all get flags=self::NOTCANON
                $IDs = $this->queryGetBundleIDs($bundleID2);
                foreach ($IDs as $id) {
                    $this->queryAssertRow($id, $bundleID1, self::NOTCANON);
                }
            }

        } catch (\PDOException $e) {
            $this->error("Unable to assert pair ($symbol1, $symbol2)", $e);
        }
    }

    /**
     * Take an array of strings of lines of Tab-separated symbols and assert into the store
     *
     * In fact, takes just the first two TAB fields - you can have anything you
     * like after a second TAB, if there is one.
     * Does no checking - simply skips lines that don't have a TAB in them.
     *
     * @param array $data The array of pairs, each being a tab-separated line
     */
    public function assertPairs(array $data)
    {
        foreach ($data as $line) {
            $line = trim($line);
            $bits = explode("\t", $line);
            if (count($bits) >= 2) {
                $this->assertPair($bits[0], $bits[1]);
            }
        }
    }

    /**
//TODO Not needed - done in the WebApp?
     * Take a file of Tab-separated symbols and assert into the store
     *
     * Not a public service - just in the Class
     * In fact, takes just the first two TAB fields - you can have anything you
     * like after a second TAB, if there is one.
     * Does no checking - simply skips lines that don't have a TAB in them.
     *
     * @param string $file The filename to be asserted
     */
    public function assertFile($file)
    {
        $data = file($file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
        if ($data === false) {
            $this->error("Failed to open file '$file'");
        }
        $this->assertPairs(explode("\n", $data));
    }

    /**
     * Simply remove a symbol from this store
     *
     *    Note - if it is the canon, then there will be no canon left
     *        It could choose another one, but has no way of knowing - so you are advised to setCanon after this.
     *
     * @param string $symbol The symbol to be deleted.
     */
    public function removeSymbol($symbol)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        $this->queryDeleteSymbol($symbol);
    }

    /**
     * Make the given symbol the (only) Canon of it's bundle
     *
     *    Any existing canons will be uncanonised.
     *    If it isn't there, then it becomes a singleton bundle
     *        Note - this may be slightly unexpected, but is the only sensible thing to do
     *
     * @param string $symbol The symbol to be set as the canon
     */
    public function setCanon($symbol)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        // Do we have it?
        $bundleID = $this->queryGetBundleID($symbol);
        if ($bundleID === null) {
            // No we don't have it already
            // Insert new $symbol into new $bundleID
            // And make it the Canon of its singleton bundle
            $this->queryAssertRow($symbol, $this->queryGetMaxBundle() + 1, self::CANON);
        } else {
            // Yes we do have it already
            // Get the Canon and de-canonise it
            $oldCanon = $this->queryGetCanon($bundleID);
            $this->queryAssertRow($oldCanon, $bundleID, self::NOTCANON);
            // And make $symbol the Canon
            $this->queryAssertRow($symbol, $bundleID, self::CANON);
        }
    }

    /**
     * Return the Canon of the bundle with the given symbol in it
     *
     * Looks up the given symbol in a store, and returns the canon of bundle it is in.
     * If the symbol was not in the store at all, then it simply returns the symbol itself.
     *
     * @param string $symbol The symbol that we want the canon of
     *
     * @return string[] A singleton array with the canon in it
     */
    public function getCanon($symbol)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        // Do we have it?
        $bundle = $this->queryGetBundleID($symbol);
        if ($bundle === null) {
            // No we don't have it already
            $canon = "$symbol";
        } else {
            // Yes we do have it already
            $canon = $this->queryGetCanon($bundle);
        }

        return array($canon);
    }

    /**
     * Return all the Canons in a store
     *
     * @return string[] An array with all the canons in it
     */
    public function allCanons()
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare(
                "SELECT symbol FROM $this->storeName WHERE " .
                "flags=" . self::CANON . " ORDER BY symbol ASC"
            );
            $statement->execute();
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $output = array();
            foreach ($results as $row) {
                $output[] = $row['symbol'];
            }
        } catch (\PDOException $e) {
            $this->error("Database failure to get canons from store", $e);
        }

        return $output;
    }

    /**
     * Simply delete a whole store
     */
    public function deleteStore()
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare("DROP TABLE $this->dataTable;");
            $statement->execute();
            $statement = $this->dbHandle->prepare("DROP TABLE $this->symbolTable;");
            $statement->execute();
        } catch (\PDOException $e) {
            $this->error("Database failure to delete store", $e);
        }
    }

    /**
     * Simply clear out the whole store, leaving an empty table
     */
    public function emptyStore()
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            if ($this->dbType === 'sqlite') {
                // SQLite doesn't have TRUNCATE
                $statement1 = $this->dbHandle->prepare("DELETE FROM $this->dataTable;");
                $statement2 = $this->dbHandle->prepare("DELETE FROM $this->symbolTable;");
            } else {
                $statement1 = $this->dbHandle->prepare("TRUNCATE $this->dataTable;");
                $statement2 = $this->dbHandle->prepare("TRUNCATE $this->symbolTable;");
            }
            $statement1->execute();
            $statement2->execute();
        } catch (\PDOException $e) {
            $this->error("Database failure to empty store", $e);
        }
    }

    /**
     * Mainly for diagnostics, but can be used to back up or move stores
     *  Output the whole sameAs table.
     *  First line is headers.
     *  Subsequent lines are each entry.
     *  Represented as an array of one string per line.
     *
     *  A dump that has been saved to file can be re-asserted using restoreStore.
     *
     * @return string[] The array of strings
     */
    public function dumpStore()
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            // TODO Do it as one query, and sort on symbol as well
            $statement = $this->dbHandle->prepare(
                "SELECT * FROM $this->dataTable " .
                "ORDER BY bundle ASC, flags DESC"
            );
            $statement->execute();
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $output = array();
            $output[] = "<pre>";
            $output[] = "Bundle\tFlags\tSymbol";
            foreach ($results as $row) {
                $output[] = "{$row['bundle']}\t{$row['flags']}\t{$this->queryGetSymbolSymbol($row['id'])}";
            }
        } catch (\PDOException $e) {
            $this->error("Unable to dump store", $e);
        }
        $output[] = "</pre>";
        return $output;
    }

    /**
     * Takes the output of dumpStore and adds it into this store
     *
     * Overwrites any existing values, leaving the others intact.
     * Assumes the source data is valid.
     *
     * @param string $file The file name of the source data to be asserted
     */
    public function restoreStore($file)
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        $data = file($file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
        if ($data === false) {
            $this->error("Failed to open file '$file'");
        }

        // lose the heading line
        array_shift($data);

        try {
            // assert each row
            foreach ($data as $line) {
                $line = trim($line);
                $bits = explode("\t", $line);
                $this->queryAssertRow($bits[2], $bits[0], $bits[1]);
            }
        } catch (\PDOException $e) {
            $this->error("Unable to restore store", $e);
        }
    }

    /**
     * List the sameAs stores in this database
     *
     * Effectively just lists all the tables - it could try to check to see if
     * they seem to be sameAs or not, but in fact that would always be
     * problematic at the limit.
     * In fact will simply throw an SQL error if there is no symbol column.
     *
     * @return string[] A header line followed by statistics on each store
     */
    public function listStores()
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            if ($this->dbType === 'sqlite') {
                // SQLite doesn't have SHOW TABLES"
                $statement = $this->dbHandle->prepare(
                    "SELECT name FROM sqlite_master " .
                    "WHERE type='table' ORDER BY name;"
                );
            } else {
                $statement = $this->dbHandle->prepare("SHOW TABLES");
            }
            $statement->execute();
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $output = array();
            $output[] = "<pre>\n";
            $output[] = "Symbols\tBundles\tStore";
            foreach ($rows as $row) {
                $store = $row['name']; // TODO This doesn't work for mySQL - it's Array ( [Tables_in_testdb] => webdemo )
                $statement = $this->dbHandle->prepare("SELECT COUNT(DISTINCT id) FROM $this->dataTable;");
                $statement->execute();
                $count = $statement->fetch(\PDO::FETCH_NUM);
                $statement = $this->dbHandle->prepare("SELECT COUNT(DISTINCT bundle) FROM $this->dataTable ;");
                $statement->execute();
                $bundles = $statement->fetch(\PDO::FETCH_NUM);
                $output[] = "{$count[0]}\t{$bundles[0]}\t$store";
            }
        } catch (\PDOException $e) {
            $this->error("Database failure to get the store list", $e);
        }
        $output[] = "</pre>\n";
        return $output;
    }

    /**
     * Provide basic statistics on the store - number of symbols and number of bundles
     *
     * @return string[] The array wih the results in
     */
    public function statistics()
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        $output = array();
        $output[] = "Statistics for sameAs store $this->storeName:";
        try {
            // get number of symbols
            $statement = $this->dbHandle->prepare(
                "SELECT COUNT(DISTINCT id) FROM $this->dataTable ;"
            );
            $statement->execute();
            $symbols = $statement->fetch(\PDO::FETCH_NUM);
            $output[] = $symbols[0]."\tsymbols";

            // get number of bundles
            $statement = $this->dbHandle->prepare(
                "SELECT COUNT(DISTINCT bundle) FROM $this->dataTable ;"
            );
            $statement->execute();
            $bundles = $statement->fetch(\PDO::FETCH_NUM);
            $output[] = $bundles[0]."\tbundles";
        } catch (\PDOException $e) {
            $this->error("Database failure to get statistics for store", $e);
        }
        return $output;
    }

    /**
     * Provide detailed analysis of the store
     *
     *    Number of symbols
     *    Number of bundles
     *    Average and median symbols per bundle
     *    Table of count of bundles for each bundle size
     *
     *    Number of http, https and non-http(s) symbols
     *
     *    URI(s) per domain - for http(s) symbols
     *
     *    List of singleton bundle symbols
     *    Bundles without a canon
     *    Bundles with more than one canon
     *
     * @return string[] The array wih the results in
     */
    public function analyse()
    {
        // skip if we've already connected
        if ($this->dbHandle == null) {
            $this->connect();
        }

        $output = array();
        $output[] = "Analysis of sameAs store '$this->storeName' in Database '$this->dbName':";
        // Can return from the middle of the store is actually empty
        try {
            // Just get the whole store into an array to work on
            $statement = $this->dbHandle->prepare(
                "SELECT * FROM $this->storeName ORDER BY bundle ASC, flags DESC, symbol ASC;"
            );
            $statement->execute();
            $store = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (count($store) === 0) {
                $output[] = "Store is empty!";
                return $output;
            };

            $nSymbols = 0; // Symbols in the store
            $nBundles = 0; // Bundles in the store
            $bundlesAssoc = array(); // An array of bundleID => array of symbols
            $bundleSizes = array(); // An array of bundleID => bundle size
            $httpSymbols = array(); // Array of symbols that have http:// at the start
            $httpsSymbols = array(); // Array of symbols that have https:// at the start
            $plainSymbols = array(); // Array of symbols that have neither http:// nor https:// at the start
            $httpDomains = array(); // Array of http domains, with symbol counts in
            $httpTLDDomains = array(); // Array of http TLD domains, with symbol counts in
            $http2LDDomains = array(); // Array of http second level+TLD domains, with symbol counts in
            $httpsDomains = array(); // Array of https domains, with symbol counts in
            $httpsTLDDomains = array(); // Array of https TLD domains, with symbol counts in
            $https2LDDomains = array(); // Array of https second level+TLD domains, with symbol counts in
            foreach ($store as $row) {
                $s = $row['symbol'];
                $b = $row['bundle'];
                $f = $row['flags'];
                $nSymbols++;
                $bundlesAssoc[$b][] = $s;
                $bundleSizes[$b]++;
                if (substr($s, 0, 7) == 'http://') {
                    // http:// URI
                    $httpSymbols[] = $s;
                    // Get and record the domain name
                    preg_match('@^(?:http://)?([^/]+)@i', $s, $matchesD);
                    $httpDomains[] = $matchesD[1];
                    // Get and record the last two bits of the domain name
                    preg_match('/([^.]+)\.([^.]+)$/', $matchesD[1], $matches2D);
                    $http2LDDomains[] = $matches2D[0];
                    // Record the TLD itself
                    $httpTLDDomains[] = $matches2D[2];
                } elseif (substr($s, 0, 8) == 'https://') {
                    // https:// URI
                    $httpsSymbols[] = $s;
                    // Get and record the domain name
                    preg_match('@^(?:https://)?([^/]+)@i', $s, $matchesD);
                    $httpsTLDDomains[] = $matchesD[1];
                    // Get and record the last two bits of the domain name
                    preg_match('/[^.]+\.[^.]+$/', $matchesD[1], $matches2D);
                    $https2LDDomains[] = $matches2D[0];
                    // Record the TLD itself
                    $httpsTLDDomains[] = $matches2D[2];
                } else {
                    // Not an http(s) symbol
                    $plainSymbols[] = $s;
                }
            }
            $nBundles = count($bundlesAssoc);

            $sep = "=====================================================";
            $minisep = "------------------------";
            $output[] = $sep;
            // Basic numeric statistics
            $output[] = "Basic numeric statistics:";
            $output[] = "$nSymbols \tsymbols";
            $output[] = "$nBundles \tbundles";
            $output[] = sprintf("%.2f", $nSymbols/$nBundles)."\tsymbols per bundle";
            $sortedBundleSizes = $bundleSizes;
            sort($sortedBundleSizes);
            $middle = floor($nBundles / 2);
            $output[] = $bundleSizes[$middle]."\tbundle size median";
            $values = array_count_values($bundleSizes);
            $mode = array_search(max($values), $values);
            $output[] = "$mode\tbundle size mode";

            $output[] = $sep;
            // Table of count of bundles for each bundle size
            $output[] = "Table of count of bundles for each bundle size";
            $bundleSizeFrequency = array_count_values($bundleSizes);
            $output[] = "Size\tBundle Count";
            foreach ($bundleSizeFrequency as $size => $count) {
                $output[] = "$size\t$count";
            }
            $output[] = $sep;
            // Number of http, https and non-http(s) symbols
            $output[] = "Symbols by type:";
            $output[] = count($httpSymbols)."\tHTTP symbols";
            $output[] = count($httpsSymbols)."\tHTTPS symbols";
            $output[] = count($plainSymbols)."\tnon-HTTP(S) symbols";
            $output[] = $sep;
            // URI(s) etc per domain - for http symbols
            $output[] = "URI(s) etc per domain - for http symbols, if any";
            if (!empty($httpSymbols)) {
                $output[] = "Count\tDomain";
                $domainCountFrequency =  array_count_values($httpDomains);
                foreach ($domainCountFrequency as $domain => $size) {
                    $output[] = "$size\t$domain";
                }
                $output[] = $minisep;
                $output[] = "Count\tBase+TLD Domain";
                $domainCountFrequency =  array_count_values($http2LDDomains);
                foreach ($domainCountFrequency as $domain => $size) {
                    $output[] = "$size\t$domain";
                }
                $output[] = $minisep;
                $output[] = "Count\tTLD Domain";
                $domainCountFrequency =  array_count_values($httpTLDDomains);
                foreach ($domainCountFrequency as $domain => $size) {
                    $output[] = "$size\t$domain";
                }
            }
            $output[] = $sep;
            // URI(s) etc per domain - for https symbols
            $output[] = "URI(s) etc per domain - for https symbols, if any";
            if (!empty($httpsSymbols)) {
                $output[] = "Count\tDomain";
                $domainCountFrequency =  array_count_values($httpsDomains);
                foreach ($domainCountFrequency as $domain => $size) {
                    $output[] = "$size\t$domain";
                }
                $output[] = $minisep;
                $output[] = "Count\tBase+TLD Domain";
                $domainCountFrequency =  array_count_values($https2LDDomains);
                foreach ($domainCountFrequency as $domain => $size) {
                    $output[] = "$size\t$domain";
                }
                $output[] = $minisep;
                $output[] = "Count\tTLD Domain";
                $domainCountFrequency =  array_count_values($httpsTLDDomains);
                foreach ($domainCountFrequency as $domain => $size) {
                    $output[] = "$size\t$domain";
                }
            }
            $output[] = $sep;
            $output[] = "Things that might be considered errors:";
            // List of singleton bundle symbols
            $output[] = "Singleton bundles:";
            $singletons = array_keys($bundleSizes, 1);
            $output[] = "Bundle\tSymbol";
            foreach ($singletons as $singleton) {
                $output[] = "$singleton\t{$bundlesAssoc[$singleton][0]}";
            }

            $output[] = $minisep;
            $output[] = "Bundles that have canon issues:";
            // Now run through doing sanity checks on bundles
            $previousBundle = -1; // Start with an invalid bundle number, so it won't match the first bundle
            $previousSymbol = ""; // To report soemthing useful
            $canonCount = 1; // And pretend that the previous one had a canon
            foreach ($store as $row) {
                // $store is sorted by bundle during the original query
                $s = $row['symbol'];
                $b = $row['bundle'];
                $f = $row['flags'];
                if ($b !== $previousBundle) {
                    // Then we have changed to a new bundle
                    // Report any problems with the previous one
                    if ($canonCount === 0) {
                        $output[] = "$previousBundle\thas no canon (a symbol from the bundle is '$previousSymbol')";
                    }
                    if ($canonCount > 1) {
                        $output[] = "$previousBundle\thas $canonCount canons " .
                              "(a symbol from the bundle is '$previousSymbol')";
                    }
                    // And set up for the next bundle
                    $previousBundle = $b;
                    $previousSymbol = $s;
                    $canonCount = 0;
                }
                if ($f == self::CANON) {
                    $canonCount++;
                } //end if
            }
            $output[] = $sep;
            $output[] = "(You can get all the canons by invoking the appropriate method/service)";
            $output[] = $sep;
        } catch (\PDOException $e) {
            $this->error("Database failure to get some analysis data for store", $e);
        }

        return $output;
    }

    /**
     * Raw DB function to get the ID for a given symbol
     * [sqlInjectionProtected]
     *
     * @param string $symbol The symbol being looked up
     *
     * @return int The ID, null if the symbol was not in the symbols table
     */
    private function queryGetSymbolID($symbol)
    {
        try {
            $statement = $this->dbHandle->prepare("SELECT id FROM $this->symbolTable WHERE symbol = :symbol LIMIT 1");
            $statement->bindValue(':symbol', $symbol, \PDO::PARAM_STR);
            $statement->execute();
            $IDs = $statement->fetch(\PDO::FETCH_NUM);
            return $IDs[0];
        } catch (\PDOException $e) {
            print $e->getMessage();
            $this->error("Database failure to get the ID for '$symbol'");
        }
    }

    /**
     * Raw DB function to get the symbol for a given ID
     * not [sqlInjectionProtected] - it's an ID
     *
     * @param int $id The ID being looked up
     *
     * @return string The symbol, null if the ID was not in the symbols table
     */
    private function queryGetSymbolSymbol($id)
    {
        try {
            $statement = $this->dbHandle->prepare("SELECT symbol FROM $this->symbolTable WHERE id = '$id' LIMIT 1"); // TODO
            $statement->execute();
            $symbols = $statement->fetch(\PDO::FETCH_NUM);
            return $symbols[0];
        } catch (\PDOException $e) {
            print $e->getMessage();
            $this->error("Database failure to get the symbol for ID '$id'");
        }
    }

    /**
     * Raw DB function to get the bundle ID for a given symbol ID
     * [sqlInjectionProtected] // TODO
     *
     * @param int $id The symbol ID being looked up
     *
     * @return int The bundle ID, null if the symbol was not in the store
     */
    private function queryGetBundleID($id)
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare("SELECT bundle FROM $this->dataTable WHERE id = :id LIMIT 1");
            $statement->bindValue(':id', $id, \PDO::PARAM_STR);
            $statement->execute();
            $bundles = $statement->fetch(\PDO::FETCH_NUM);
            return $bundles[0];
        } catch (\PDOException $e) {
            $this->error("Database failure to get the bundleID for symbol with ID '$id'", $e);
        }
    }

    /**
     * Raw DB function to get all the symbols from a given bundle (by ID)
     *
     *    SQL injection protected
     *
     * @param integer $bundleID The bundle being looked up
     *
     * @return string[] The bundle ID, empty if the symbol was not in the store
     */
    private function queryGetBundleSymbols($bundleID)
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare("SELECT symbol FROM $this->storeName WHERE bundle = :bundle;");
            $statement->bindValue(':bundle', $bundleID, \PDO::PARAM_INT);
            $statement->execute();
            $rs = $statement->fetchAll(\PDO::FETCH_NUM);
            $result = array();
            foreach ($rs as $r) {
                $result[] = $r[0];
            }

            return $result;
        } catch (\PDOException $e) {
            $this->error("Database failure to get the bundle symbols for bundle '$bundleID'", $e);
        }
    }

    /**
     * Raw DB function to get all the symbols from a given bundle (by ID)
     *
     *    SQL injection protected
     *
     * @param integer $bundleID The bundle being looked up
     *
     * @return int[] The bundle symbol IDs, empty if the symbol was not in the store
     */
    private function queryGetBundleIDs($bundleID)
    {
        try {
            $statement = $this->dbHandle->prepare("SELECT id FROM $this->dataTable WHERE bundle = :bundle;");
            $statement->bindValue(':bundle', $bundleID, \PDO::PARAM_INT);
            $statement->execute();
            $rs = $statement->fetchAll(\PDO::FETCH_NUM);
            $result = array();
            foreach ($rs as $r) {
                $result[] = $r[0];
            }

            return $result;
        } catch (\PDOException $e) {
            print $e->getMessage();
            $this->error("Database failure to get the bundle symbols for bundle '$bundleID'");
        }
    }

    /**
     * Raw DB function to find the maximum value for the symbol ID
     *
     *    SQL injection protected
     *
     * @return integer The maximum symbol ID, 0 if there are no bundles
     */
    private function queryGetMaxSymbolID()
    {
        try {
            $statement = $this->dbHandle->prepare("SELECT MAX(id) FROM $this->symbolTable;");
            $statement->execute();
            $r = $statement->fetch(\PDO::FETCH_NUM);

            if ($r[0] == NULL) {
                return 0;
            } else {
                return $r[0];
            }
        } catch (\PDOException $e) {
            print $e->getMessage();
            $this->error("Database failure to get the maximum symbol ID");
        }
    }

    /**
     * Raw DB function to find the maximum value for the Bundle column in a store
     *
     *    SQL injection protected
     *
     * @return integer The maximum bundle value in the store, 0 if there are no bundles
     */
    private function queryGetMaxBundle()
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare("SELECT MAX(bundle) FROM $this->dataTable;");
            $statement->execute();
            $r = $statement->fetch(\PDO::FETCH_NUM);

            if ($r[0] == NULL) {
                return 0;
            } else {
                return $r[0];
            }
        } catch (\PDOException $e) {
            $this->error("Database failure to get the maximum bundle ID", $e);
        }
    }

    /**
     * Raw DB function to get the canon from a given bundle (by ID)
     *
     *    SQL injection protected
     *
     * @param integer $bundleID The bundle being looked up
     *
     * @return string The symbol which is the canon
     */
    private function queryGetCanon($bundleID)
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare(
                "SELECT symbol FROM $this->storeName WHERE " .
                "bundle = :bundle AND flags = " . self::CANON . " LIMIT 1;"
            );
            $statement->bindValue(':bundle', $bundleID, \PDO::PARAM_INT);
            $statement->execute();
            $r = $statement->fetch(\PDO::FETCH_NUM);

            return $r[0];
        } catch (\PDOException $e) {
            $this->error("Database failure to get the get the canon for bundle '$bundleID'", $e);
        }
    }

    /**
     * Raw DB function to delete the symbol (and whole row) from a store
     *
     *    SQL injection protected
     *
     * @param string $symbol The symbol to be deleted
     */
    private function queryDeleteSymbol($symbol)
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $ID = $this->queryGetSymbolID($symbol);
            $statement = $this->dbHandle->prepare("DELETE FROM $this->symbolTable WHERE symbol = :symbol;");
            $statement->bindValue(':symbol', $symbol, \PDO::PARAM_STR);
            $statement->execute();
            $statement = $this->dbHandle->prepare("DELETE FROM $this->dataTable WHERE id = :id;");
            $statement->bindValue(':id', $ID, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\PDOException $e) {
            $this->error("Database failure to delete '$symbol'", $e);
        }
    }

    /**
     * Raw DB function to insert or update the row of an existing symbol ID data
     *
     * This will do an insert if it wasn't there, or update if it was.
     *
     * [sqlInjectionProtected]
     *
     * @param integer  $id    The symbol being considered
     *
     * @param integer $bundleID  The bundle ID for the symbol is now in
     * (overwrites any existing bundle ID for an existing symbol)
     *
     * @param integer $canonFlag The flags for this symbol (overwrites any
     * existing flags for an existing symbol).
     */

    private function queryAssertRow($id, $bundleID, $canonFlag)
    {
        if ($this->dbHandle == null) {
            $this->connect();
        }

        try {
            $statement = $this->dbHandle->prepare("REPLACE INTO $this->dataTable VALUES (:id, :bundle, :canon)");
            $statement->bindValue(':id', $id, \PDO::PARAM_STR);
            $statement->bindValue(':bundle', $bundleID, \PDO::PARAM_INT);
            $statement->bindValue(':canon', $canonFlag, \PDO::PARAM_INT);
            $statement->execute();
        } catch (\PDOException $e) {
            $this->error("Database failure to assert for symbol with ID='$id', bundle='$bundleID' and canon='$canonFlag'");
        }
    }

    /**
     * Raw DB function to insert or update the row of an existing symbol in symbols
     *
     * This will do an insert if it wasn't there, or update if it was.
     * TODO Pretty sure this should use auto-inc and return the id of the symbol
     *
     * [sqlInjectionProtected]
     *
     * @param string  $symbol    The symbol being considered
     *
     * @param integer  $id    The id to make the symbol
     *
     */
    private function queryAssertSymbol($symbol, $id)
    {
        try {
            $statement = $this->dbHandle->prepare("REPLACE INTO $this->symbolTable VALUES (:id, :symbol)");
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->bindValue(':symbol', $symbol, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\PDOException $e) {
            print $e->getMessage();
            $this->error("Database failure to assert for '$symbol'");
        }
    }

    /**
     * Class error function
     *
     * Raises and exception with the given message
     *
     * @param string          $message The error message to display
     * @param \Exception|null $e       The exception which raised the initial error, if any.
     *
     * @throws \Exception A generic exception is thrown, containing useful
     * details and the desired error message.
     */
    private function error($message, \Exception $e = null)
    {
        $additional = ($e == null) ? '' : ' // ' . $e->getMessage();
        throw new \Exception(get_class() . " (store '{$this->storeName}'): " . $message . $additional);
    }
}

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
