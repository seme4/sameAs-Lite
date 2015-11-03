<?php
/**
 * SameAs Lite
 *
 * This interface provides an interface to define a storage capability for SameAs pairs.
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

/**
 * Interface that must be implimented for all SameAsLite Stores
 */
interface StoreInterface
{

// TODO extends Traversable?



    /**
     * Constructor for an SQL store
     *
     * @param string $name    The name of the store
     * @param array  $options Array of options to pass on to the store
     */
    public function __construct($name, array $options = array());


    /**
     * Sets the default options for a store
     * This can help prevent duplicate code when instantating multiple Stores
     *
     * @param array $options The default options to be passed into the store
     */
    public static function setDefaultOptions(array $options);


    /**
     * Gets an array of strings of the options accepted by this store
     *
     * @return string[] Options this store can take
     */
    public static function getAvailableOptions();


    /**
     * Establish a connection to the store (setup on each script run)
     */
    public function connect();

    /**
     * Returns whether the store has been connected to
     *
     * @return bool Whether the store has been connected to
     */
    public function isConnected();

    /**
     * Disconnect from the store, this requires connect to be run again before the store can be used
     */
    public function disconnect();


    /**
     * Set up the store for the first time
     * Do nothing if the store is already inited
     */
    public function init();

    /**
     * Returns whether or not the store has been initialised
     *
     * @return bool Whether the store has been initialised
     */
    public function isInit();

    /**
     * Delete a whole store
     * This means that the init needs to be called before the store can be used and all data is removed
     */
    public function deleteStore();


    /**
     * Get the name of the store
     *
     * @return string The name of this store
     */
    public function getStoreName();


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
    public function querySymbol($symbol);



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
     * @return string[] An array of the symbols, which is empty if none were found.
     */
    public function search($string);


    
    /**
     * Put a possibly new pair into the store
     *
     * Needs to cope with a number of situations:
     *        - Both symbols were already in the store
     *          - do nothing
     *        - The first symbol was not in the store
     *          - add it to the bundle of the second symbol
     *        - The second symbol was not in the store
     *          - add it to the bundle of the first symbol
     *        - Both symbols were in different bundles
     *          - add the symbols from the bundle of the second symbol to the
     *            first bundle, none of them as canons
     *            (leaving the canons situation as it is in bundle 1)
     *
     * @param string $symbol1 The first symbol
     * @param string $symbol2 The second symbol
     *
     * @return bool True on success
     * @throws \Exception On failure
     */
    public function assertPair($symbol1, $symbol2);




    /**
     * Take a array of pairs (also an array) and assert those symbols to the store
     *
     * @param array $data The 2D array of pairs to be asserted
     *
     * @return bool True on success
     * @throws \Exception On FIRST pair to fail to be asserted
     */
    public function assertPairs(array $data);




    /**
     * Take a string in TSV (Tab-separated values) representing pairs of symbols and assert them to the store
     *
     * EG:
     * ```
     * test1<TAB>test2<NEWLINE>test3<TAB>test4
     * ```
     *
     * @param string $tsv The TSV data, as a string
     *
     * @return bool True on success
     * @throws \Exception On FIRST pair to fail to be asserted
     */
    public function assertTSV($tsv);


    
    /**
     * Remove a symbol from this store.
     * If the symbol is the bundle's canon then do not remove it unless it is also the only symbol in the bundle.
     *
     * @param string $symbol The symbol to be deleted.
     * @throws \Exception if the symbol could not be removed or it is a canon in a non-singleton bundle
     */
    public function removeSymbol($symbol);

    /**
     * Remove an array of Symbols from the store
     * If you wish to remove a whole bundle from a given symbol the use {@link removeBundle()}
     *
     * @param array $symbols The symbols to be removed
     *
     * @return bool True on success
     * @throws \Exception On FIRST pair to fail to be removed
     */
    public function removeSymbols(array $symbols);

    /**
     * Remove a bundle and ALL of it's symbols from the store
     * The canon will also be removed
     *
     * @param string $symbol The symbol that's bundle should be removed. Does not have to be the canon
     */
    public function removeBundle($symbol);


    /**
     * Make the given symbol the (only) Canon of it's bundle
     *
     * If $restrict is false, remove the existing canon in the bundle.
     * If $restrict is true, do not change the canon if one already exists
     *
     * @param string  $symbol   The symbol to be set as the canon
     * @param boolean $restrict Whether to restrict if a canon already exists
     */
    public function setCanon($symbol, $restrict = false);



    /**
     * Return the canonical symbol of the bundle with the given symbol ($symbol) in it
     *
     * Look up the given symbol in a store, and return the canon of bundle it is in.
     * If the symbol is not in the store, then return null.
     *
     * @param string $symbol The symbol that we want the canon of
     *
     * @return string The canon
     */
    public function getCanon($symbol);



    /**
     * Return all the canonical symbols in a store
     *
     * @return string[] An array with all the canons in it
     */
    public function getAllCanons();


    /**
     * Remove all data from the store, but retaining the condition $this->isInit() === true
     */
    public function emptyStore();

    /**
     * Get all pairs (a 2 item array) from the store and return them as an array
     *
     * @return string[][] The 2D array of pairs in the form [ canon, symbol ]
     */
    public function dumpPairs();

    /**
     * Get all pairs and output them as a TSV string
     *
     * @return string The TSV string representing all the pairs in the store
     */
    public function dumpTSV();


    /**
     * Provide basic statistics on the store - number of symbols and number of bundles
     *
     * Should contain:
     * ```
     * Array
     *  (
     *      [symbols] => <int>
     *      [bundles] => <int>
     *  )
     * ```
     *
     * @return mixed[] An associative array wih the statistics in
     */
    public function statistics();




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
     *
     * EXAMPLE OUTPUT:
     *  ```
     * Array
     *  (
     *      [meta] => Array
     *          (
     *              // This is an example for a MySQL database
     *              // Anything can be in this section, depending on the store
     *              [store_name] => table1
     *              [database_name] => testdb
     *          )
     *
     *      [rows] => 20
     *      [basic] => Array
     *          (
     *              [symbols] => 20
     *              [bundles] => 10
     *              [average_symbols_per_bundle] => 2
     *              [median_bundle_size] => 1
     *              [mode_bundle_size] => 1
     *          )
     *
     *      [bundle_size_count] => Array
     *          (
     *              [0] => Array
     *                  (
     *                      [size] => 1
     *                      [count] => 6
     *                  )
     *
     *              [1] => Array
     *                  (
     *                      [size] => 0
     *                      [count] => 2
     *                  )
     *
     *              [2] => Array
     *                  (
     *                      [size] => 2
     *                      [count] => 2
     *                  )
     *
     *          )
     *
     *      [symbols_type] => Array
     *          (
     *              [HTTP_symbols] => 3
     *              [HTTPS_symbols] => 0
     *              [plain_symbols] => 17
     *          )
     *
     *      [URIs_per_domain] => Array
     *          (
     *              [http] => Array
     *                  (
     *                      [domain] => Array
     *                          (
     *                              [0] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => data.ordnancesurvey.co.uk
     *                                  )
     *
     *                              [1] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => dbpedia.org
     *                                  )
     *
     *                              [2] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => yago-knowledge.org
     *                                  )
     *
     *                          )
     *
     *                      [base+TLD] => Array
     *                          (
     *                              [0] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => co.uk
     *                                  )
     *
     *                              [1] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => dbpedia.org
     *                                  )
     *
     *                              [2] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => yago-knowledge.org
     *                                  )
     *
     *                          )
     *
     *                      [TLD] => Array
     *                          (
     *                              [0] => Array
     *                                  (
     *                                      [size] => 1
     *                                      [domain] => uk
     *                                  )
     *
     *                              [1] => Array
     *                                  (
     *                                      [size] => 2
     *                                      [domain] => org
     *                                  )
     *
     *                          )
     *
     *                  )
     *
     *              [https] => Array
     *                  (
     *                      [domain] => Array()
     *
     *                      [base+TLD] => Array()
     *
     *                      [TLD] => Array()
     *
     *                  )
     *
     *          )
     *
     *      [warnings] => Array
     *          (
     *              [singleton_bundle_symbols] => Array
     *                  (
     *                      [0] => aaa
     *                      [1] => maths
     *                      [2] => nappy
     *                      [3] => pavement
     *                      [4] => rubbish
     *                      [5] => trainers
     *                  )
     *
     *          )
     *
     *      [errors] => Array
     *          (
     *              [canons_without_symbols] => Array
     *                  (
     *                      [0] => aaa
     *                      [1] => crisps
     *                      [2] => http://data.ordnancesurvey.co.uk/id/7000000000037256
     *                      [3] => maths
     *                      [4] => nappy
     *                      [5] => pavement
     *                      [6] => petrol
     *                      [7] => rubbish
     *                      [8] => test
     *                      [9] => trainers
     *                  )
     *
     *          )
     *
     *  )
     * ```
     *
     *
     *
     * @return mixed[] The array wih the results in
     */
    public function analyse();
}
