<?php
/**
 * SameAs Lite Web Application
 *
 * This class provides a RESTful web application around a \SameAsLite\Store.
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
 * Provides a RESTful web interface for a \SameAsLite\Store.
 */
class WebApp extends \Slim\Slim
{
    /** @var \SameAsLite\Store|null $store The underlying Store. */
    protected $store = null;

    /** @var $format A variable for the required output format for convenience */
    private $format = 'HTML';

    /**
     * Constructor.
     *
     * @param \SameAsLite\Store $store The SameAs Lite Store for which we shall
     *                                 provide RESTful interfaces.
     */
    public function __construct(\SameAsLite\Store $store)
    {
        //  fake $_SERVER parameters if required
        $this->initialiseServerParameters();

        // Set up a format variable for convenience
        switch ($_SERVER['HTTP_ACCEPT']) {
            case 'text/html':
                $this->format = 'HTML';
                break;
            case 'text/plain':
                $this->format = 'PLAIN';
                break;
            case 'application/json':
                $this->format = 'JSON';
                break;
            case 'application/rdf+xml':
                $this->format = 'RDFXML';
                break;
            case 'text/n3':
                $this->format = 'N3';
                break;
            default:
                $this->format = 'HTML';
                break;
        }

        // Initialise Slim
        parent::__construct();

        //  save reference to the store
        $this->store = $store;

        // register routes and callbacks
        // In the same order as the Seme4 Platform usage page
        // And with the same names for the same (or similar?!) function

        // Homepage
        $this->get('/', array($this, 'homepage'));

        // Admin actions
        $this->delete('/admin/delete', array($this, 'deleteStore'));
        $this->delete('/admin/empty', array($this, 'emptyStore'));
        $this->put('/admin/restore/:file', array($this, 'restoreStore'));

        // Canon work
        $this->get('/canons', array($this, 'allCanons'));
        $this->put('/canons/:symbol', array($this, 'setCanon'));
        $this->get('/canons/:symbol', array($this, 'getCanon'));

        // Pairs
        $this->get('/pairs', array($this, 'dumpStore'));
        $this->put('/pairs', array($this, 'assertFile'));
        $this->put('/pairs/:symbol1/:symbol2', array($this, 'assertPair'));
// TODO XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
// This is a hack to let me put pairs in the browser!!!!!
        $this->get('/pairs/backdoor/:symbol1/:symbol2', array($this, 'assertPair'));

        // Search
        $this->get('/search/:string', array($this, 'search'));

        // Single symbol stuff
        $this->get('/symbols/:symbol', array($this, 'querySymbol'));
        $this->delete('/symbols/:symbol', array($this, 'removeSymbol'));

        // Simple status
        $this->get('/status', array($this, 'statistics'));

        // New to the service interaction (not in the Seme4 Platform)
        $this->get('/list', array($this, 'listStores'));
        $this->get('/analyse', array($this, 'analyse'));
    }


    /**
     * A simple hompage that gives basic advice on how to use the services
     *
     * Invoked by an HTTP GET on the service root endpoint
     */
    public function homepage()
    {
        $output = '<html> <body>';

        $output .= $this->indexItem("admin/delete", "Delete the store completely", "DELETE", 0, true);
        $output .= $this->indexItem("admin/empty", "Empty the store completely", "DELETE", 0, true);
        $output .= $this->indexItem("admin/restore/:file", "Restore the store from the given local (server) file name", "PUT", 1, true);

        $output .= $this->indexItem("canons", "A list of all the canons in the store");
        $output .= $this->indexItem("canons/:symbol", "Set the given symbol to be the canon of its bundle", "PUT", 1, true);
        $output .= $this->indexItem("canons/:symbol", "Get the canon for the bundle of the given symbol", "GET", 1, false);

        $output .= $this->indexItem("pairs", "A list of symbols, with their attributes - not really a list of all the pairs in the store");
        $output .= $this->indexItem("pairs", "Assert a file of pairs to the store", "PUT", 0, true);
        $output .= $this->indexItem("pairs/:symbol1/:symbol2", "Assert the pair (symbol1, symbol2) into the store", "PUT", 2, true);
        $output .= $this->indexItem("pairs/backdoor/:symbol1/:symbol2", "Assert the pair (symbol1, symbol2) into the store - not RESTful!!!", "GET", 2, true);

        $output .= $this->indexItem("search/:string", "Look up the given string in all the symbols, and list them - simple wildcards can be used", "GET", 1, false);

        $output .= $this->indexItem("symbols/:symbol", "Look up the given symbol in the store, and list all the symbols, any canon(s) first", "GET", 1, false);
        $output .= $this->indexItem("symbols/:symbol", "Delete the given symbol from the store, if it was there", "DELETE", 1, true);

        $output .= $this->indexItem("status", "Basic statistics about the store");

        $output .= $this->indexItem("list", "A list of all the stores in this database");
        $output .= $this->indexItem("analyse", "An analysis of the data held in this store");

        $this->outputText($output);
    }

    /**
     * Actions the HTTP DELETE service from /admin/delete
     *
     * Simply passes the request on to the deleteStore sameAsLite Class method
     * Reports success, since failure will have caused an exception
     */
    public function deleteStore()
    {
        $this->checkAuth();
        $this->store->deleteStore();
        $this->outputText("Store deleted", "RAW");
    }

    /**
     * Actions the HTTP DELETE service from /admin/empty
     *
     * Simply passes the request on to the emptyStore sameAsLite Class method
     * Reports success, since failure will have caused an exception
     */
    public function emptyStore()
    {
        $this->store->emptyStore();
        $this->outputText("Store emptied", "RAW");
    }

    /**
     * Actions the HTTP PUT service from /admin/restore/:file
     *
     * Simply passes the request on to the restoreStore sameAsLite Class method
     * Reports success, since failure will have caused an exception
     *
     * @param string $file The local (server) filename to get the previous dump from
     */
    public function restoreStore($file)
    {
        $this->store->restoreStore($file);
        $this->outputText("Store restored from file '$file'", "RAW");
    }

    /**
     * Actions the HTTP GET service from /canons
     *
     * Simply passes the request on to the allCanons sameAsLite Class method
     * Outputs the canons in the format requested
     */
    public function allCanons()
    {
        $this->checkAuth();
        $result = $this->store->allCanons();
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP PUT service from /canons/:symbol
     *
     * Simply passes the request on to the setCanon sameAsLite Class method
     * Reports success, since failure will have caused an exception
     *
     * @param string $symbol The symbol that we want to make the canon
     */
    public function setCanon($symbol)
    {
        $this->store->setCanon($symbol);
        $this->outputText("Canon set to '$symbol'", "RAW");
    }

    /**
     * Actions the HTTP GET service from /canons/:symbol
     *
     * Simply passes the request on to the getCanon sameAsLite Class method
     * Outputs the canon in the format requested
     *
     * @param string $symbol The symbol that we want to find the canon of
     */
    public function getCanon($symbol)
    {
        $result = $this->store->getCanon($symbol);
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP GET service from /pairs
     *
     * Simply passes the request on to the dumpStore sameAsLite Class method
     * Outputs the a list of the symbols and their attributes in the format requested
     */
    public function dumpStore()
    {
        $result = $this->store->dumpStore();
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP PUT service from /pairs
     *
     * Simply passes the request on to the assertFile sameAsLite Class method
     * Reports the before and after statistics; failure will have caused an exception
     */
    public function assertFile()
    {
        $before = $this->store->statistics();
        $this->store->assertPairs(explode("\n", $this->request->getBody()));
        $after = $this->store->statistics();
        $this->outputText(array_merge(array("Before:"), $before, array("After:"), $after), "RAW");
    }

    /**
     * Actions the HTTP PUT service from /pairs/:symbol1/:symbol2
     *
     * Simply passes the request on to the assertPair sameAsLite Class method
     * Reports success, since failure will have caused an exception
     *
     * @param string $symbol1 The first symbol of the pair
     * @param string $symbol2 The second symbol of the pair
     */
    public function assertPair($symbol1, $symbol2)
    {
        $this->store->assertPair($symbol1, $symbol2);
        $this->outputText("The pair ($symbol1, $symbol2) was asserted", "RAW");
    }

    /**
     * Actions the HTTP GET service from /pairs/backdoor/:symbol1/:symbol2
     *
     * Simply passes the request on to the assertPair sameAsLite Class method
     * Reports success, since failure will have caused an exception
     *
     * @param string $symbol1 The first symbol of the pair
     * @param string $symbol2 The second symbol of the pair
     */
    public function assertPairGET($symbol1, $symbol2)
    {
        $result = $this->store->assertPair($symbol1, $symbol2);
        $this->outputText("The pair ($symbol1, $symbol2) was asserted");
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP GET service from search/:string
     *
     * Simply passes the request on to the search sameAsLite Class method
     * Outputs the symbol in the format requested
     *
     * @param string $string The sub-string that we want to look for
     */
    public function search($string)
    {
        $result = $this->store->search($string);
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP GET service from /symbols/:symbol
     *
     * Simply passes the request on to the querySymbol sameAsLite Class method
     * Outputs the symbol in the format requested
     *
     * @param string $symbol The symbol to be looked up
     */
    public function querySymbol($symbol)
    {
        $result = $this->store->querySymbol($symbol);
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP DELETE service from /symbols/:symbol
     *
     * Simply passes the request on to the removeSymbol sameAsLite Class method
     * Reports success, since failure will have caused an exception
     *
     * @param string $symbol The symbol to be removed
     */
    public function removeSymbol($symbol)
    {
        $result = $this->store->removeSymbol($symbol);
        $this->outputText($result, "RAW");
    }

    /**
     * Actions the HTTP GET service from /status
     *
     * Simply passes the request on to the statistics sameAsLite Class method
     * Outputs the results in the format requested
     */
    public function statistics()
    {
        $result = $this->store->statistics();
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP GET service from /list
     *
     * Simply passes the request on to the listStores sameAsLite Class method
     * Outputs the results in the format requested
     */
    public function listStores()
    {
        $result = $this->store->listStores();
        $this->outputText($result, $this->format);
    }

    /**
     * Actions the HTTP GET service from /analyse
     *
     * Simply passes the request on to the analyse sameAsLite Class method
     * Outputs the results in the format requested
     */
    public function analyse()
    {
        $result = $this->store->analyse();
        $this->outputText($result, $this->format);
    }


    /**
     * Initialise dummy $_SERVER parameters if not set (ie command line).
     */
    private function initialiseServerParameters()
    {
        global $argv;

        if (!isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = (isset($argv[1])) ? $argv[1] : '/';
        }
        if (!isset($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = getHostByAddr('127.0.0.1');
        }
        if (!isset($_SERVER['SERVER_PORT'])) {
            $_SERVER['SERVER_PORT'] = 80;
        }
    }


    /**
     * Output the given text in the specified format
     *
     * The single method that outputs tect in whatever format is required
     *
     * @param mixed  $body   The text to be output - string[] or string
     * @param string $format OPTIONAL("HTML") The required format - HTML|PREF|RAW - will have HTML|PREF|JSON|RDFXML|N3|RAW
     */
    private function outputText($body = null, $format = "HTML")
    {
        // Is it a single string, rather than an array? - if so make it an array
        if (is_string($body)) {
            $body = array($body);
        }
        $output = "";
        switch ($format) {
            case "HTML":
                $output .= "<!DOCTYPE html>\n<html><body>\n";
                break;
            case "PREF":
                $output .= "<html><body><pre>\n";
                break;
            case "JSON":
            case "RDFXML":
            case "N3":
                die("Output type not supported\n");
                break;
            case "RAW":
            default:
        }

        //  add body data
        foreach ($body as $line) {
            switch ($format) {
                case "HTML":
                    $output .= "$line<br />\n";
                    break;
                case "PREF":
                    $output .= "$line\n";
                    break;
                case "JSON":
                case "RDFXML":
                case "N3":
                    die("Output type not supported\n");
                    break;
                case "RAW":
                default:
                    $output .= "$line\n";
            }
        };

        switch ($format) {
            case "HTML":
                $output .= "</body></html>\n";
                break;
            case "PREF":
                $output .= "</pre></body></html>\n";
                break;
            case "JSON":
            case "RDFXML":
            case "N3":
                die("Output type not supported\n");
                break;
            case "RAW":
            default:
        }
        echo $output;
    }


    /**
     * Template to construct an item to gop in the rservice index in HTML
     *
     * @param string  $path        The path from the service root to the endpoint
     * @param string  $description OPTIONAL("")  A description of the endpoint
     * @param string  $method      OPTIONAL("GET")  The HTTP method of the service being described for this endpoint
     * @param integer $paramCount  OPTIONAL(0)  The number of parameters handed over from the Slim framework
     * @param string  $auth        OPTIONAL(false)  True if the invocation requires authentication
     *
     * @return string The html string to be displayed for this item
     */
    public function indexItem($path, $description = "", $method = "GET", $paramCount = 0, $auth = false)
    {
        $endpoint = "http://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}$path";

        $output = "<br/>====================================================<br/>$description<br/>\n";

        // TODO Maybe put boxes below if there were variables in the spec - and it is the right sort
        switch ($method) {
            case "GET":
                $output .= '<tt><a href="'.$endpoint.'">'.$endpoint."</a></tt> (<tt>HTTP $method</tt>)\n";
                $output .= "<br/><tt>curl -X $method $endpoint</tt>\n";
                break;
            case "POST":
                break;
            case "PUT":
                if ($paramCount === 0) {
                    // Then there is a file being put
                    $output .= "<tt>curl -X $method --data-binary @filename $endpoint</tt> (<tt>HTTP $method</tt>)\n";
                } else {
                    $output .= "<tt>curl -X $method $endpoint</tt> (<tt>HTTP $method</tt>)\n";
                }
                break;
            case "DELETE":
                $output .= "<tt>curl -X $method $endpoint</tt> (<tt>HTTP $method</tt>)\n";
                break;
            default:
                $output .= "Unrecognised HTTP Method '$method'\n";
                break;
        };
        if ($auth) {
            $output .= '<br />Note: this service requires authentication';
        }

        $output .= "\n\n";
        return $output;
    }

    /**
     * Check the HTTP authentication is OK
     *
     * Used for non-GET services - that is, services that can change the store
     * Very simple - HTTP auth, and could be over http, if that is the way the service is set up
     * The credentials are in this method too
     */
    public function checkAuth()
    {
        // An array for users => passwords
        $users = array('demo' => 'demo', 'sameAs' => 'Lite');

        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
            array_search($_SERVER['PHP_AUTH_PW'], $users) !== $_SERVER['PHP_AUTH_USER']) {
            header("WWW-Authenticate: Basic realm=\"sameAs Lite Services\"");
            header("HTTP\ 1.0 401 Unauthorized");
            $this->outputText("Authentication denied", $this->format);
            exit;
        }
    }
}
// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
