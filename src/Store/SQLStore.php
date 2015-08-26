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
 * Abstract class that impliments functions common to SQL Databases
 */
abstract class SQLStore implements \SameAsLite\StoreInterface {

    /** @var \PDO $pdoObject The PDO object for the SQL database */
    protected $pdoObject;

    /** @var string $dsn The PDO connection string */
    protected $dsn;

    /** @var string $storeName The name of this store, also the name of the SQL table */
    protected $storeName;


    /*
     * Get the settings this Store takes for the factory class
     * Expected in this form:
     * ```
     * Array (
     *   [dsn_prefix] => <string>,
     *   [params] => [
     *         <array of strings with the varable names that the __construct function takes
     *               excluding 'name'>
     *   ]
     * )
     *
     * @return mixed[] Array of settings
     */

    //abstract public static function getFactorySettings();



    /**
     * {@inheritDoc}
     *
     * @throws \Exception If unable to connect to the store
     */
    public function connect(){
        /*
         * Simple implimentation of connect
         * Does not create tables, just a connection to the database via PDO
         */

        // skip if we've already connected
        if ($this->pdoObject != null) {
            return null;
        }

        
        $user = (isset($this->dbUser))?$this->dbUser:null;
        $pass = (isset($this->dbPass))?$this->dbPass:null;

        try {
            $this->pdoObject = new \PDO($this->dsn, $user, $pass, [
                \PDO::ATTR_PERSISTENT => true // Establish a persistant connection to avoid overhead of reopening one on each script run
            ]);
        } catch (\PDOException $e) {
            throw new \Exception(
                'Unable to to connect to MySQL // ' .
                $e->getMessage()
            );
        }
    }


    /**
     * {@inheritDoc}
     */
    public function isConnected(){
        // We are connected if the pdoObject is a PDO object
        return (@get_class($this->pdoObject) === "PDO");
    }


    /**
     * {@inheritDoc}
     */
    public function disconnect(){
        // Just set the PDO to null to disconnect
        $pdoObject = null;
    }

    /**
     * Return $storeName
     *
     * @return string The name of the store
     */
    public function getStoreName(){
        return $this->storeName;
    }

    /**
     * Returns the PDO object for this store
     * Should be used for testing only
     *
     * @return \PDO The PDO object used in this store
     */
    public function getPDOObject(){
        return $this->pdoObject;
    }

    /**
     * Gets the name of the sql table used for this store
     * Default implementation uses $this->storeName as the table name
     * @see self::$storeName
     * 
     * @return string The name of the SQL table for this store
     */
    public function getTableName(){
        return $this->storeName;
    }


    /**
     * Most implementations of database functions call another function that can be overridden
     * These functions should return the SQL string to be executed with the given symbols replacing the terms
     */
    public function querySymbol($symbol){

        try{
            $sql = $this->getQuerySymbolString(':search');
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute([ 'search' => $symbol ]);

            $output = [];
            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $output[] = $row['symbol'];
            }
        }catch(\PDOException $e){
            // This function throws an error
            $this->error("Query symbol '$symbol' failed", $e);
        }

        return $output;
    }

    /**
     * Gets the SQL query string that when run returns the expected result of { @link querySymbol() }
     * @see querySymbol()
     *
     * @param string $symbolId The string to be placed where the symbol would go in the query
     *
     * @return string The SQL string for the query
     */
    protected function getQuerySymbolString($symbolId){
        $tn = $this->getTableName();
        return "SELECT `t1`.`canon`, `t1`.`symbol`
                FROM `{$tn}` AS t1, `{$tn}` AS t2
                WHERE `t1`.`canon` = `t2`.`canon` AND `t2`.`symbol` = {$symbolId}
                ORDER BY `t1`.`canon` DESC, `t1`.`symbol` ASC";
    }



    /**
     * {@inheritDoc}
     */
    public function search($string){

        try{
            $sql = $this->getSearchString(':search');
            $statement = $this->pdoObject->prepare($sql);
            $statement->bindValue(':search', "%$string%", \PDO::PARAM_STR);
            $statement->execute();

            $output = [];
            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $output[] = $row['symbol'];
            }
        }catch(\PDOException $e){
            $this->error("Search for '$string' failed", $e);
        }

        return $output;
    }


    /**
     * Gets the SQL query string that when run returns the expected result of { @link search() }
     * @see search()
     *
     * @param string $string The string to be placed where the symbol would go in the query
     *
     * @return string The SQL string for the query
     */
    protected function getSearchString($string){
        $tn = $this->getTableName();
        return "SELECT `t1`.`canon`, `t1`.`symbol`
                FROM `{$tn}` AS t1, `{$tn}` AS t2
                WHERE `t1`.`canon` = `t2`.`canon` AND `t2`.`symbol` LIKE {$string}
                ORDER BY `t1`.`canon` DESC, `t1`.`symbol` ASC";
    }




    /**
     * {@inheritDoc}
     */
    public function assertPair($symbol1, $symbol2){
        try {
            // Are the symbols already in the store?
            $canon1 = $this->getCanon($symbol1);
            $canon2 = $this->getCanon($symbol2);

            // These now have the canons, or null if the symbol wasn't in the store

            if ($canon1 === null && $canon2 === null) {
                // Both symbols are new - create a new bundle
                // And make the Canon $symbol1
                // REPLACE handles the case where they are the same - for neatness
                $sql = "REPLACE INTO $this->storeName VALUES (:symbol1, :symbol1), (:symbol1, :symbol2)";
                $statement = $this->pdoObject->prepare($sql);
                $statement->execute(array(':symbol1' => $symbol1, ':symbol2' => $symbol2));
            } elseif ($canon1 === $canon2) {
                // They were both already in the same bundle
                // Do nothing
            } elseif ($canon1 === null) {
                // Insert new $symbol1 into existing bundle for $symbol2
                // No need to do anything about canons
                $sql = "INSERT INTO $this->storeName VALUES (:canon2, :symbol1)";
                $statement = $this->pdoObject->prepare($sql);
                $statement->execute(array(':symbol1' => $symbol1, ':canon2' => $canon2));
            } elseif ($canon2 === null) {
                // Insert new $symbol2 into existing bundle for $symbol1
                // No need to do anything about canons
                $sql = "INSERT INTO $this->storeName VALUES (:canon1, :symbol2)";
                $statement = $this->pdoObject->prepare($sql);
                $statement->execute(array(':canon1' => $canon1, ':symbol2' => $symbol2));
            } else {
                // They are in different bundles
                // So join the two bundles - set all of bundle 2 to be in bundle 1
                // Canon will be the canon of bundle 1
                // TODO Performance? Doesn't need protection since they came out of the table?
                $sql = "UPDATE $this->storeName SET canon = :canon1 WHERE canon = :canon2";
                $statement = $this->pdoObject->prepare($sql);
                $statement->execute(array(':canon1' => $canon1, ':canon2' => $canon2));
            }

            return true;
        } catch (\PDOException $e) {
            $this->error("Unable to assert pair ($symbol1, $symbol2)", $e);
        }
    }



    /**
     * {@inheritDoc}
     */
    public function assertPairs(array $data){
        foreach($data as $pair){
            $this->assertPair($pair[0], $pair[1]);
        }
        return true;
    }



    /**
     * {@inheritDoc}
     */
    public function assertTSV($tsv){
        $data = str_getcsv($tsv, "\n"); //parse the rows 
        foreach($data as &$row){
            $row = str_getcsv($row, "\t"); //parse the items in rows 
        }

        return $this->assertPairs($data);
    }


    /**
     * {@inheritDoc}
     */
    public function removeSymbol($symbol){

        $canon = $this->getCanon($symbol);
        if($canon === $symbol){
            // This symbol is the canon
            $symbols = $this->querySymbol($symbol);
            if(count($symbols) > 1){
                // Greater than 1
                // This means this is the canon and not the only thing in the bundle
                $this->error("$symbol is the canon of the bundle, please delete all symbols or change the canon before deleting this symbol"); // Throws
                return false; // Just in case
            }
        }

        $sql = $this->getRemoveSymbolString(':symbol');
        $statement = $this->pdoObject->prepare($sql);
        $statement->execute([ ':symbol' => $symbol ]);
    }




    /**
     * Gets the SQL query string that when run removes the symbol given in { @link removeSymbol() }
     * @see removeSymbol()
     *
     * @param string $symbolId The string to be placed where the symbol would go in the query
     *
     * @return string The SQL string for the query
     */
    protected function getRemoveSymbolString($symbolId){
        return "DELETE FROM {$this->getTableName()} WHERE symbol = {$symbolId}";
    }



    /**
     * {@inheritDoc}
     */
    public function setCanon($symbol, $restrict = false){

        // Do we have it?
        $canon = $this->getCanon($symbol);
        if ($canon === null) {
            // No we don't have it already
            // Insert new $symbol
            // And make it the Canon of its singleton bundle
            $this->assertPair($symbol, $symbol);
        } else {
            if($restrict === false){
                // Yes we do have it already
                $sql = $this->getSetCanonString(':symbol', ':canon');
                $statement = $this->pdoObject->prepare($sql);
                $statement->execute([ ':symbol' => $symbol, ':canon' => $canon ]);
            }else{
                $this->error("Cannot change canon, $restrict = true and a canon symbol already exists");
            }
        }
    }


    /**
     * Gets the SQL query string that when run updates the all the symbols with $canonId to $symbolId { @link setCanon() }
     * @see setCanon()
     *
     * @param string $symbolId The string to be placed where the new symbol would go in the query
     * @param string $canonId  The string to be placed where the current canon would go in the query
     *
     * @return string The SQL string for the query
     */
    protected function getSetCanonString($symbolId, $canonId){
        return "UPDATE {$this->getTableName()} SET canon = {$symbolId} WHERE canon = {$canonId}";
    }



    /**
     * {@inheritDoc}
     */
    public function getCanon($symbol){
        try {
            $sql = $this->getCanonString(':symbol');
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute(array(':symbol' => $symbol));
            $r = $statement->fetch(\PDO::FETCH_NUM);

            return $r[0];
        } catch (\PDOException $e) {
            $this->error("Database failure to get the get the canon for symbol '$symbol'", $e);
        }
    }

    /**
     * Gets the SQL query string that when run returns the expected result of { @link getCanon() }
     * @see getCanon()
     *
     * @param string $symbolId The string to be placed where the symbol would go in the query
     *
     * @return string The SQL string for the query
     */
    protected function getCanonString($symbolId){
        return "SELECT canon FROM {$this->getTableName()} WHERE symbol = {$symbolId} LIMIT 1";
    }



    /**
     * {@inheritDoc}
     */
    public function getAllCanons(){

        try {
            $sql = $this->getAllCanonsString();
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute();

            $output = [];
            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $output[] = $row['canon'];
            }
        } catch (\PDOException $e) {
            $this->error("Database failure to get canons from store", $e);
        }

        return $output;
    }


    /**
     * Gets the SQL query string that when run returns the expected result of { @link getAllCanons() }
     * @see getAllCanons()
     *
     * @return string The SQL string for the query
     */
    protected function getAllCanonsString(){
        return "SELECT DISTINCT canon FROM $this->storeName ORDER BY symbol ASC";
    }



    /**
     * {@inheritDoc}
     */
    public function dumpPairs(){

        try {
            $sql = $this->getDumpPairsString();
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute();

            $output = [];
            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $output[] = [ $row['canon'], $row['symbol'] ];
            }
        } catch (\PDOException $e) {
            $this->error("Unable to dump pairs", $e);
        }

        return $output;
    }


    /**
     * Gets the SQL query string that when run returns the expected result of { @link dumpPairs() }
     * @see dumpPairs()
     *
     * @return string The SQL string for the query
     */
    protected function getDumpPairsString(){
        return "SELECT canon, symbol FROM {$this->getTableName()} ORDER BY canon ASC, symbol ASC";
    }



    /**
     * {@inheritDoc}
     */
    public function dumpTSV(){
        // Uses temporary files to get the TSV output string
        $pairs = $this->dumpPairs();

        /* Adapted from http://stackoverflow.com/a/16353448 */

        // Open a memory "file" for read/write...
        $fp = fopen('php://temp', 'r+');
        // ... write the $input array to the "file" using fputcsv()...
        
        foreach($pairs as $pair){
            fputcsv($fp, $pair, "\t");
        }

        // ... rewind the "file" so we can read what we just wrote...
        rewind($fp);
        // ... read the entire line into a variable...
        $data = fread($fp, 1048576);
        // ... close the "file"...
        fclose($fp);
        // ... and return the $data to the caller, with the trailing newline from fgets() removed.
        return rtrim($data, "\n");
    }





    /**
     * {@inheritDoc}
     */
    public function statistics(){

        $stats = [];
        try {
            // get number of symbols
            $sql = $this->getStatisticsSymbolNumberString();
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute();
            $symbols = $statement->fetch(\PDO::FETCH_NUM);
            $stats["symbols"] = $symbols[0];

            // get number of bundles
            $sql = $this->getStatisticsCanonNumberString();
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute();
            $bundles = $statement->fetch(\PDO::FETCH_NUM);
            $stats["bundles"] = $bundles[0];
        } catch (\PDOException $e) {
            $this->error("Database failure to get statistics for store", $e);
        }

        return $stats;
    }

    /**
     * Returns the SQL that gets the number of symbols in the store
     *
     * @return string The SQL that will return the number of symbols in the store
     */
    protected function getStatisticsSymbolNumberString(){
        return "SELECT COUNT(DISTINCT symbol) FROM {$this->getTableName()}";
    }
    /**
     * Returns the SQL that gets the number of canons in the store
     *
     * @return string The SQL that will return the number of canons in the store
     */
    protected function getStatisticsCanonNumberString(){
        return "SELECT COUNT(DISTINCT canon) FROM {$this->getTableName()}";
    }





    /**
     * {@inheritDoc}
     */
    public function analyse(){

        $output = [];

        $output["meta"] = [
            'store_name'     => $this->storeName,
            'database_name'  => $this->dbName
        ];

        try {
            // Just get the whole store into an array to work on
            $sql = $this->getAnalyseAllRowsString();
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute();
            $store = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $output['rows'] = count($store);

            if (count($store) === 0) {
                return $output; // Return here as the store is empty
            };

            $nSymbols = 0; // Symbols in the store
            $nBundles = 0; // Bundles in the store
            $bundleSizes = array(); // An array of canon => bundle size
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
                $b = $row['canon'];
                $nSymbols++;
                
                if(isset($bundleSizes[$b])){
                    $bundleSizes[$b]++;
                }else{
                    $bundleSizes[$b] = 0;
                }
                
                // TODO convert to parse_url?
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
                    $httpsTLDDomains[] = isset($matches2D[2])?$matches2D[2]:0;
                } else {
                    // Not an http(s) symbol
                    $plainSymbols[] = $s;
                }
            }
            $nBundles = count($bundleSizes);



            // Calculate the bundle averages
            $sortedBundleSizes = $bundleSizes;
            sort($sortedBundleSizes);
            if($nBundles % 2 == 0){
                $middle = floor($nBundles / 2) - 1;
            }else{
                $middle = floor($nBundles / 2);
            }
            $bundleMedian = $sortedBundleSizes[$middle];

            $values = array_count_values($bundleSizes);
            $mode = array_search(max($values), $values);


            $output['basic'] = [
                'symbols'                     => $nSymbols,
                'bundles'                     => $nBundles,
                'average_symbols_per_bundle'  => ($nSymbols/$nBundles),
                'median_bundle_size'          => $bundleMedian,
                'mode_bundle_size'            => $mode,
            ];



            $bundleSizeCount = [];
            // Table of count of bundles for each bundle size
            foreach (array_count_values($bundleSizes) as $size => $count) {
                $bundleSizeCount[] = [
                    'size'  => $size,
                    'count' => $count
                ];
            }
            $output['bundle_size_count'] = $bundleSizeCount;

            $output['symbols_type'] = [
                'HTTP_symbols'  => count($httpSymbols),
                'HTTPS_symbols' => count($httpsSymbols),
                'plain_symbols' => count($plainSymbols)
            ];


            $httpUris = [
                'domain'    => [],
                'base+TLD'  => [],
                'TLD'       => []
            ];

            foreach(array_count_values($httpDomains) as $domain => $size){
                $httpUris['domain'][] = [
                    'size'   => $size,
                    'domain' => $domain
                ];
            }

            foreach(array_count_values($http2LDDomains) as $domain => $size){
                $httpUris['base+TLD'][] = [
                    'size'   => $size,
                    'domain' => $domain
                ];
            }

            foreach(array_count_values($httpTLDDomains) as $domain => $size){
                $httpUris['TLD'][] = [
                    'size'   => $size,
                    'domain' => $domain
                ];
            }

            $httpsUris = [
                'domain'    => [],
                'base+TLD'  => [],
                'TLD'       => []
            ];


            foreach(array_count_values($httpsDomains) as $domain => $size){
                $httpsUris['domain'][] = [
                    'size'   => $size,
                    'domain' => $domain
                ];
            }

            foreach(array_count_values($https2LDDomains) as $domain => $size){
                $httpsUris['base+TLD'][] = [
                    'size'   => $size,
                    'domain' => $domain
                ];
            }

            foreach(array_count_values($httpsTLDDomains) as $domain => $size){
                $httpsUris['TLD'][] = [
                    'size'   => $size,
                    'domain' => $domain
                ];
            }


            $output['URIs_per_domain'] = [
                'http'  => $httpUris,
                'https' => $httpsUris
            ];


            $output['warnings'] = [
                'singleton_bundle_symbols' => array_keys($bundleSizes, 1)
            ];



            $canonsWithoutSymbols = [];

            // List of canons that are not symbols
            $sql = $this->getAnalyseCanonsNotSymbolsString();
            $statement = $this->pdoObject->prepare($sql);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($result as $row) {
                $canonsWithoutSymbols[] = $row['canon'];
            }

            $output['errors'] = [
                'canons_without_symbols' => $canonsWithoutSymbols
            ];


        } catch (\PDOException $e) {
            $this->error("Database failure to get some analysis data for store", $e);
        }

        return $output;
    }

    /**
     * Gets the SQL query string that when run returns all rows the database
     * @see analyse()
     *
     * @return string The SQL string for the query
     */
    protected function getAnalyseAllRowsString(){
        return "SELECT * FROM {$this->getTableName()} ORDER BY canon ASC, symbol ASC";
    }
    /**
     * Gets the SQL query string that when run returns unqiue rows where the canon does not have a symbol
     * @see analyse()
     *
     * @return string The SQL string for the query
     */
    protected function getAnalyseCanonsNotSymbolsString(){
        return "SELECT DISTINCT canon FROM {$this->getTableName()} WHERE canon != symbol ORDER BY canon ASC";
    }






    /**
     * Class error function
     *
     * Raises an exception with the given message
     *
     * @param string          $message The error message to display
     * @param \Exception|null $e       The exception which raised the initial error, if any.
     *
     * @throws \Exception A generic exception is thrown, containing useful
     * details and the desired error message.
     */
    protected function error($message, \Exception $e = null)
    {
        $additional = ($e == null) ? '' : ' // ' . $e->getMessage();
        throw new \Exception(get_class() . " (store '{$this->storeName}'): " . $message . $additional);
    }
}