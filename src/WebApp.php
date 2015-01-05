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
 * Provides a RESTful web interface for a SameAs Lite Store.
 */
class WebApp
{

    /** @var \SameAsLite\Store $stores The underlying \SameAsLite\Store(s). */
    protected $stores = array();

    /**
     * @var array $storeOptions Store specific options for each underlying
     * \SameAsLite\Store.
     */
    protected $storeOptions = array();

    /** @var \Slim\Slim $app The Slim application instance */
    protected $app = null;

    /** @var string $symbol The term used for a "symbol" (or item) within the store */
    protected $symbol = 'item';

    /**
     * Constructor.
     *
     * @param array $options The SameAs Lite Store for which we shall
     * provide RESTful interfaces.
     */
    public function __construct(array $options = array())
    {
        // fake $_SERVER parameters if required (eg command line invocation)
        $this->initialiseServerParameters();

        // initialise and configure Slim, using Twig template engine
        $this->app = new \Slim\Slim(
            array(
                // 'mode' => 'production',
                'debug' => false,
                'view' => new \Slim\Views\Twig()
            )
        );

        // configure Twig
        $this->app->view()->setTemplatesDirectory('assets/twig/');
        $this->app->view()->parserOptions['autoescape'] = false;
        $this->app->view()->set('path', $this->app->request()->getRootUri());

        // register error and 404 handlers
        $this->app->error(array($this, 'outputException'));
        $this->app->notFound(array($this, 'outputError404'));

        // apply options
        foreach ($options as $k => $v) {
            $this->app->view->set($k, $v);
        }

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
    }

    /**
     * Add a store to this web application
     *
     * @param \SameAsLite\Store $store   The underlying Store to add.
     * @param array             $options Optional array of configration options.
     *
     * @throws \InvalidArgumentException if there are problems with arguments
     */
    public function addStore(\SameAsLite\Store $store, array $options)
    {

        if (!isset($options['name'])) {
            throw new \InvalidArgumentException('The $options array is missing required key/value "name"');
        }

        if (!isset($options['slug'])) {
            throw new \InvalidArgumentException('The $options array is missing required key/value "slug"');
        }

        if (!preg_match('/^[A-Za-z0-9_\-]*$/', $options['slug'])) {
            throw new \InvalidArgumentException(
                'Value for $options["slug"] may contain only characters a-z, A-Z, 0-9, hyphen and undersore'
            );
        }

        $this->store = $store;
        $this->storeOptions[] = $options;
    }

    /**
     * Run application, using configuration defined by previous method
     * @see addStore
     */
    public function run()
    {
        // register routes and callbacks
        // Homepage
        $this->app->get('/', array($this, 'homepage'));

        // Admin actions
        $this->app->delete('/admin/delete', array($this, 'deleteStore'));
        $this->app->delete('/admin/empty', array($this, 'emptyStore'));
        $this->app->put('/admin/restore/:file', array($this, 'restoreStore'));

        // Canon work
        $this->app->get('/canons', array($this, 'allCanons'));
        $this->app->put('/canons/:symbol', array($this, 'setCanon'));
        $this->app->get('/canons/:symbol', array($this, 'getCanon'));

        // Pairs
        $this->app->get('/pairs', array($this, 'dumpStore'));
        $this->app->put('/pairs', array($this, 'assertFile'));
        $this->app->put('/pairs/:symbol1/:symbol2', array($this, 'assertPair'));
// TODO XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
// This is a hack to let me put pairs in the browser!!!!!
        $this->app->get('/pairs/backdoor/:symbol1/:symbol2', array($this, 'assertPair'));

        // Search
        $this->app->get('/search/:string', array($this, 'search'));

        // Single symbol stuff
        $this->app->get('/symbols/:symbol', array($this, 'querySymbol'));
        $this->app->delete('/symbols/:symbol', array($this, 'removeSymbol'));

        // Simple status
        $this->app->get('/status', array($this, 'statistics'));

        // New to the service interaction (not in the Seme4 Platform)
        $this->app->get('/list', array($this, 'listStores'));
        $this->app->get('/analyse', array($this, 'analyse'));

        // run
        $this->app->run();
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
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $_SERVER['HTTP_ACCEPT'] = 'text/html';
        }
    }

    /**
     * If an exception is thrown, the Slim Framework will use this function to
     * render details (if in development mode) or present a basic error page.
     *
     * @param \Exception $e The Exception which caused the error
     */
    public function outputException(\Exception $e)
    {
        if ($this->app->getMode() == 'development') {
            // show details if we are in dev mode
            $op  = "\n";
            $op .= "        <h4>Request details &ndash;</h4>\n";
            $op .= "        <dl class=\"dl-horizontal\">\n";
            foreach ($_SERVER as $key => $value) {
                $key = strtolower($key);
                $key = str_replace(array('-', '_'), ' ', $key);
                $key = preg_replace('#^http #', '', $key);
                $key = ucwords($key);
                $op .= "          <dt>$key</dt>\n            <dd>$value</dd>\n";
            }
            $op .= "        </dl>\n";

            $msg = $e->getMessage();
            $summary = '';
            if (($p = strpos($msg, ' // ')) !== false) {
                $summary = substr($msg, $p + 4) . '</p><p>';
                $msg = substr($msg, 0, $p);
            }

            $this->outputError(
                500,
                'Server Error',
                $summary . '<strong>' . $e->getFile() . '</strong> &nbsp; +' . $e->getLine(),
                $msg,
                "<h4>Stack trace &ndash;</h4>\n        "
                . '<pre class="white">' . $e->getTraceAsString() . '</pre>'
                . $op
            );
        } else {
            // show basic message
            $this->outputError(
                500,
                'Unexpected Error',
                '</p><p>Apologies for any inconvenience, the problem has been logged and we\'ll get on to it ASAP.',
                'Whoops! An unexpected error has occured...'
            );
        }
    }

    /**
     * Output a generic error page
     *
     * @throws \InvalidArgumentException if the status is not a valid HTTP status code
     * @param string $status          The HTTP status code (eg 401, 404, 500)
     * @param string $title           Optional brief title of the page, used in HTML head etc
     * @param string $summary         Optional summary message describing the error
     * @param string $extendedTitle   Optional extended title, used at top of main content
     * @param string $extendedDetails Optional extended details, conveying more details
     */
    public function outputError($status, $title = null, $summary = '', $extendedTitle = '', $extendedDetails = '')
    {
        if (!is_integer($status)) {
            throw new \InvalidArgumentException('The $status parameter must be a valid integer HTTP status code');
        }

        if ($title == null) {
            $title = 'Error ' . $status;
        }

        $summary .= '</p><p>Please try returning to <a href="/' .
            $this->app->request()->getRootUri() . '">the homepage</a>.';

        if ($extendedTitle == '') {
            $defaultMsg = \Slim\Http\Response::getMessageForCode($status);
            if ($defaultMsg != null) {
                $extendedTitle = substr($defaultMsg, 4);
            } else {
                $extendedTitle = $title;
            }
        }

        $this->app->view()->set('titleHTML', strip_tags($title));
        $this->app->view()->set('titleHeader', 'Error ' . $status);
        $this->app->view()->set('title', $extendedTitle);
        $this->app->view()->set('summary', $summary);
        $this->app->view()->set('details', $extendedDetails);
        print $this->app->view()->fetch('error.twig');
        die;
    }

    /**
     * Output a 404 (not found) error
     */
    public function outputError404()
    {
        $this->outputError(
            404,
            'Page not found',
            'Sorry, the page you were looking for does not exist.'
        );
    }

    /**
     * A simple hompage that gives basic advice on how to use the services
     *
     * Invoked by an HTTP GET on the service root endpoint
     */
    public function homepage()
    {
        $output = '';

        $output .= $this->indexItem(
            'admin/delete',
            'Delete the store completely',
            'DELETE',
            0,
            true
        );
        $output .= $this->indexItem(
            'admin/empty',
            'Empty the store completely',
            'DELETE',
            0,
            true
        );
        $output .= $this->indexItem(
            'admin/restore/:file',
            'Restore the store from the given local (server) file name',
            'PUT',
            1,
            true
        );

        $output .= $this->indexItem(
            'canons',
            'A list of all the canons in the store'
        );
        $output .= $this->indexItem(
            'canons/:symbol',
            'Set the given symbol to be the canon of its bundle',
            'PUT',
            1,
            true
        );
        $output .= $this->indexItem(
            'canons/:symbol',
            'Get the canon for the bundle of the given symbol',
            'GET',
            1,
            false
        );

        $output .= $this->indexItem(
            'pairs',
            'A list of symbols, with their attributes - not really a list of all the pairs in the store'
        );
        $output .= $this->indexItem(
            'pairs',
            'Assert a file of pairs to the store',
            'PUT',
            0,
            true
        );
        $output .= $this->indexItem(
            'pairs/{symbol1}/{symbol2}',
            'Assert the pair (symbol1, symbol2) into the store',
            'PUT',
            2,
            true
        );
        $output .= $this->indexItem(
            'pairs/backdoor/:symbol1/:symbol2',
            'Assert the pair (symbol1, symbol2) into the store - not RESTful!!!',
            'GET',
            2,
            true
        );

        $output .= $this->indexItem(
            'search/:string',
            'Look up the given string in all the symbols, and list them - simple wildcards can be used',
            'GET',
            1,
            false
        );

        $output .= $this->indexItem(
            'symbols/:symbol',
            'Look up the given symbol in the store, and list all the symbols, any canon(s) first',
            'GET',
            1,
            false
        );
        $output .= $this->indexItem(
            'symbols/:symbol',
            'Delete the given symbol from the store, if it was there',
            'DELETE',
            1,
            true
        );

        $output .= $this->indexItem(
            'status',
            'Basic statistics about the store'
        );

        $output .= $this->indexItem(
            'list',
            'A list of all the stores in this database'
        );
        $output .= $this->indexItem(
            'analyse',
            'An analysis of the data held in this store'
        );

        $this->outputData($output);
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
        $this->outputData('Store deleted', 'RAW');
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
        $this->outputData('Store emptied', 'RAW');
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
        $this->outputData("Store restored from file '$file'", 'RAW');
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
        $this->outputData($result, $this->format);
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
        $this->outputData("Canon set to '$symbol'", 'RAW');
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
        $this->outputData($result, $this->format);
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
        $this->outputData($result, $this->format);
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
        $this->store->assertPairs(explode("\n", $this->app->request->getBody()));
        $after = $this->store->statistics();
        $this->outputData(array_merge(array('Before:'), $before, array('After:'), $after), 'RAW');
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
        $this->outputData("The pair ($symbol1, $symbol2) was asserted", 'RAW');
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
        $this->outputData("The pair ($symbol1, $symbol2) was asserted");
        $this->outputData($result, $this->format);
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
        $this->outputData($result, $this->format);
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
        $this->outputData($result, $this->format);
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
        $this->outputData($result, 'RAW');
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
        $this->outputData($result, $this->format);
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
        $this->outputData($result, $this->format);
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
        $this->outputData($result, $this->format);
    }

    /**
     * Output data from a \SameasLite\Store object
     *
     * The format is determined by the Accept header in the HTTP request
     *
     * @param mixed $body The text to be output - string[] or string
     */
    private function outputData($body = null)
    {
        // set default template values if not present
        $defaults = array(
            'titleHTML' => '',
            'titleHeader' => '{{ titleHeader }}',
        );
        foreach ($defaults as $key => $value) {
            if ($this->app->view()->get($key) == null) {
                $this->app->view()->set($key, $value);
            }
        }

        // Is it a single string, rather than an array? - if so make it an array
        if (is_string($body)) {
            $body = array($body);
        }

        $this->app->view()->set('body', join("\n", $body));
        print $this->app->view()->fetch('page.twig');
    }

    /**
     * Template to construct an item to gop in the rservice index in HTML
     *
     * @param string  $path        The path from the service root to the endpoint
     * @param string  $description OPTIONAL('')  A description of the endpoint
     * @param string  $method      OPTIONAL('GET')  The HTTP method of the service being described for this endpoint
     * @param integer $paramCount  OPTIONAL(0)  The number of parameters handed over from the Slim framework
     * @param string  $auth        OPTIONAL(false)  True if the invocation requires authentication
     *
     * @return string The html string to be displayed for this item
     */
    protected function indexItem($path, $description = '', $method = 'GET', $paramCount = 0, $auth = false)
    {
        $endpoint = '/' . $this->app->request()->getRootUri() . $path;
        $host = $this->app->request()->getUrl();

        $endpoint = preg_replace('@(:[^/]*)@', '<span>\1</span>', $endpoint);
        $endpoint = preg_replace('@(\{[^}]*})@', '<span>\1</span>', $endpoint);

        $hash = hash('crc32', microtime(true) . rand());
        $map = array(
            'GET' => 'info',
            'DELETE' => 'danger',
            'PUT' => 'warning',
            'POST' => 'success'
        );

        $output = "
<div class=\"panel panel-default panel-api\">
  <div class=\"panel-heading\" data-toggle=\"collapse\" data-target=\"#panel-$hash\" \">
    <div class=\"method\"><label class=\"label label-{$map[$method]}\">$method</label></div>
    <b>$endpoint</b><span class=\"pull-right\">$description</span>
  </div>
  <div id=\"panel-$hash\" class=\"panel-collapse collapse\">
    <div class=\"panel-body\">
      <form class=\"form-horizontal\" role=\"form\">
        <div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Description</label>
          <div class=\"col-sm-10\">
            <p class=\"form-control-static\">$description</p>
          </div>
        </div>

        <div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Example invocation</label>
          <div class=\"col-sm-10\">
            <p class=\"form-control-static\"><tt>curl -X $method $host$endpoint</tt></p>
          </div>
        </div>

        <!--div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Try it now</label>
          <div class=\"col-sm-10\">
            <p class=\"form-control-static\">...</p>
          </div>
        </div-->

      </form>
    </div>
  </div>
</div>";

        return $output;

// <pre>====================================================<br/>$description<br/>\n";
        // TODO Maybe put boxes below if there were variables in the spec - and it is the right sort
        switch ($method) {
            case 'GET':
                $output .= '<tt><a href="'.$endpoint.'">'.$endpoint."</a></tt> (<tt>HTTP $method</tt>)\n";
                $output .= "<br/><tt>curl -X $method $endpoint</tt>\n";
                break;
            case 'POST':
                break;
            case 'PUT':
                if ($paramCount === 0) {
                    // Then there is a file being put
                    $output .= "<tt>curl -X $method --data-binary @filename $endpoint</tt> (<tt>HTTP $method</tt>)\n";
                } else {
                    $output .= "<tt>curl -X $method $endpoint</tt> (<tt>HTTP $method</tt>)\n";
                }
                break;
            case 'DELETE':
                $output .= "<tt>curl -X $method $endpoint</tt> (<tt>HTTP $method</tt>)\n";
                break;
            default:
                $output .= "Unrecognised HTTP Method '$method'\n";
                break;
        };
        if ($auth) {
            $output .= '<br />Note: this service requires authentication';
        }

        $output .= "</pre>\n\n";
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
            header('WWW-Authenticate: Basic realm="SameAs Lite Services"');
            header('HTTP\ 1.0 401 Unauthorized');
            $this->outputError(
                401,
                'Access Denied',
                'You have failed to supply valid credentials, access to this resource is denied.',
                'Access Denied'
            );
        }
    }
}

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
