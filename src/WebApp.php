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
 * @version   0.0.2
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

use ptlis\ConNeg\Negotiation;

/**
 * Provides a RESTful web interface for a SameAs Lite Store.
 */
class WebApp
{

    /** @var array $appOptions The options passed into the app on construction */
    protected $appOptions;

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

    /** @var string $mimeBest The preferred MIME type in which to return data for this request */
    protected $mimeBest = 'text/html';

    /** @var array $mimeAlternatives Alternative MIME type(s) in which to return data for this request */
    protected $mimeAlternatives = array();

    /** @var array $mimeLabels Human-readable labels for the MIME types we use */
    public $mimeLabels = array(
        'text/html' => 'HTML',
        'application/rdf+xml' => 'RDF/XML',
        'text/turtle' => 'TTL',
        'application/json' => 'JSON',
        'text/csv' => 'CSV',
        'text/tab-separated-values' => 'TSV',
        'text/plain' => 'TXT',
    );

    /** @var array $routeInfo Details of the available URL routes */
    protected $routeInfo = array();

    /** @var string $store The store from the URL */
    protected $store = null;


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

        // set the default format of acceptable parameters
        // see http://docs.slimframework.com/routing/conditions/#application-wide-route-conditions
        \Slim\Route::setDefaultConditions(array(
            'store' => '[a-zA-Z0-9_\-\.]+'
        ));

        // initialise and configure Slim, using Twig template engine
        $mode = (isset($options['mode']) ? $options['mode'] : 'production');
        $this->app = new \Slim\Slim(
            array(
                'mode' => $mode,
                'debug' => false,
                'view' => new \Slim\Views\Twig()
            )
        );

        // configure Twig
        $this->app->view()->setTemplatesDirectory('assets/twig/');
        $this->app->view()->parserOptions['autoescape'] = false;
        $this->app->view()->set('path', $this->app->request()->getRootUri());

        // register 404 and custom error handlers
        $this->app->notFound(array(&$this, 'outputError404'));
        $this->app->error(array(&$this, 'outputException')); // '\SameAsLite\Exception\Exception::outputException'
        set_exception_handler(array(&$this, 'outputException')); // '\SameAsLite\Exception\Exception::outputException'

        // Hook to set the api path
        $this->app->hook('slim.before.dispatch', function () {
            // fix api pages such that if viewing a particular store
            // then the store name is automatically injected for you
            $params = $this->app->router()->getCurrentRoute()->getParams();
            if (isset($params['store'])) {
                $apiPath = "datasets/{$params['store']}/api";
            } else {
                $apiPath = 'api';
            }

            $this->app->view()->set('apiPath', $apiPath);
        });


        $this->appOptions = $options;

        // apply options
        foreach ($options as $k => $v) {
            $this->app->view->set($k, $v);
        }

    }//end __construct()

    /**
     * Add a dataset to this web application
     *
     * @param \SameAsLite\StoreInterface $store   A class implimenting StoreInterface to contain the data
     * @param array                      $options Array of configration options describing the dataset
     *
     * @throws \SameAsLite\ConfigException if there are problems with arguments in config.ini
     */
    public function addDataset(\SameAsLite\StoreInterface $store, array $options)
    {

        foreach (array('slug', 'shortName') as $configoption) {
            if (!isset($options[$configoption])) {
                throw new Exception\ConfigException('The Store array is missing required key/value "' . $configoption . '" in config.ini');
            }
        }

        if (!isset($options['fullName'])) {
            // as promised in config.ini, if fullName is not defined, it is set to shortName
            // $options['fullName'] = ucwords($options['shortName']);
            $options['fullName'] = $options['shortName'];
        }

        if (!preg_match('/^[A-Za-z0-9_\-]*$/', $options['slug'])) {
            throw new Exception\ConfigException(
                'The value for "slug" in config.ini may contain only characters a-z, A-Z, 0-9, hyphen and underscore'
            );
        }
        if (isset($this->stores[$options['slug']])) {
            throw new Exception\ConfigException(
                'You have already added a store with "slug" value of ' . $options['slug'] . ' in config.ini.'
            );
        }

        // Connect to the DB
        $store->connect();

        $this->stores[$options['slug']] = $store;
        $this->storeOptions[$options['slug']] = $options;

        // store the slug for analysis output
        $this->stores[$options['slug']]->storeSlug($options['slug']);
    }

    /**
     * Run application, using configuration defined by previous method
     * @see addStore
     */
    public function run()
    {
        // homepage and generic functions
        // access: webapp only
        $this->registerURL(
            'GET',
            '/',
            'homepage',
            'Application homepage',
            'Renders the main application homepage',
            false,
            'text/html',
            true, // hide from API
            false //no pagination
        );
        // access: webapp only
        $this->registerURL(
            'GET',
            '/api',
            'api',
            'Overview of the API',
            'Lists all methods available via this API',
            false,
            'text/html',
            true, // hide from API
            false //no pagination
        );
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets',
            'listStores',
            'Lists available datasets',
            'Returns the available datasets hosted by this service',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // enable pagination
        );
        // access: webapp only
        $this->registerURL(
            'GET',
            '/datasets/:store',
            'storeHomepage',
            'Store homepage',
            'Gives an overview of the specific store',
            false,
            'text/html',
            false,
            false // no pagination
        );
        // access: webapp only
        $this->registerURL(
            'GET',
            '/datasets/:store/api',
            'api',
            'Overview of API for specific store',
            'Gives an API overview of the specific store',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        );

        // access: webapp only
        $this->registerURL(
            'GET',
            '/about',
            'aboutPage',
            'About sameAsLite',
            'Renders the about page',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        );
        // access: webapp only
        $this->registerURL(
            'GET',
            '/contact',
            'contactPage',
            'Contact page',
            'Renders the contact page',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        );
        // access: webapp only
        $this->registerURL(
            'GET',
            '/license',
            'licensePage',
            'License page',
            'Renders the SameAsLite license',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        );

        // dataset admin actions

        // TODO: this would also need to update the config.ini
        // access: API + webapp
        $this->registerURL(
            'DELETE',
            '/datasets/:store',
            'deleteStore',
            'Delete an entire store',
            'Removes an entire store, deleting the underlying database',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        );

        // Update the store contents
        // Use a PUT request with empty body to remove the contents of the store
        // access: API + webapp
        $this->registerURL(
            'PUT',
            '/datasets/:store',
            'updateStore',
            'Update or delete the contents of a store',
            'Updates the store with request body or, if the request body is empty, removes the entire contents of a store, leaving an empty database',
            true,
            'text/plain,application/json,text/html',
            false,
            false //no pagination
        );

        // access: webapp only
        // $this->registerURL(
        // 'GET',
        // '/datasets/:store/admin/backup/',
        // 'backupStore',
        // 'Backup the database contents',
        // 'You can use this method to download a database backup file',
        // true,
        // 'text/html,text/plain'
        // );

        // access: API + webapp
        // $this->registerURL(
        //    'PUT',
        //    '/datasets/:store/admin/restore',
        //    'restoreStore',
        //    'Restore database backup',
        //    'You can use this method to restore a previously downloaded database backup',
        //    true,
        //    'text/html,text/plain'
        // );

        // Canon work
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/canons',
            'allCanons',
            'Returns a list of all canons',
            null,
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        );
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/canons/:symbol',
            'getCanon',
            'Get canon',
            'Returns the canon for the given :symbol',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            false // no pagination
        );
        // access: API + webapp
        $this->registerURL(
            'PUT',
            '/datasets/:store/canons/:symbol',
            'setCanon',
            'Set the canon', // TODO: Update text
            'Invoking this method ensures that the :symbol becomes the canon', // TODO: Update text
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        );

        // Pairs
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/pairs',
            'dumpStore',
            'Export list of pairs',
            'This method dumps *all* pairs from the database',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        );
        // access: API + webapp
        $this->registerURL(
            'PUT',
            '/datasets/:store/pairs',
            'assertPairs',
            'Assert multiple pairs',
            'Upload a file of pairs to be inserted into the store',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        );

        // access: API + webapp
        $this->registerURL(
            'PUT',
            '/datasets/:store/pairs/:symbol1/:symbol2',
            'assertPair',
            'Assert single pair',
            'Asserts sameAs between the given two symbols',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        );

        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/pairs/:string',
            'search',
            'Search',
            'Find symbols which contain/match the search string/pattern',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        );

        // Single symbol stuff
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/symbols/:symbol',
            'querySymbol',
            'Retrieve symbol',
            'Return details of the given symbol',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        );
        // access: API + webapp
        $this->registerURL(
            'DELETE',
            '/datasets/:store/symbols/:symbol',
            'removeSymbol',
            'Delete symbol',
            'Delete a symbol from the datastore',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        );

        // Simple status of datastore
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/status',
            'statistics',
            'Statistics',
            'Returns status of the store',
            true,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            false // no pagination
        );

        // Analyse contents of store
        // access: API + webapp
        $this->registerURL(
            'GET',
            '/datasets/:store/analysis',
            'analyse',
            'Analyse store',
            'Analyse contents of the store',
            true,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            false // no pagination
        );


        // add datasets to template
        $this->app->view()->set('datasets', $this->storeOptions);

        // run (Slim)
        $this->app->run();
    }

    /**
     * Register a new URL route
     *
     * @param string  $httpMethod   The HTTP method of the service being described for this endpoint
     * @param string  $urlPath      The URL pattern to register
     * @param string  $funcName     The name of the callback function (within this class) to invoke
     * @param string  $summary      A brief summary of the URL endpoint
     * @param string  $details      A detailed description of the URL endpoint
     * @param boolean $authRequired Indicates whether the invocation requires authentication
     * @param string  $mimeTypes    Valid MIME types for this URL, expressed as an HTTP accept header
     * @param boolean $hidden       Indicates whether this URL should be hidden on the API index
     */
    protected function registerURL(
        $httpMethod,
        $urlPath,
        $funcName,
        $summary,
        $details = null,
        $authRequired = false,
        $mimeTypes = 'text/html',
        $hidden = false,
        $paginate = false
    ) {

        // ensure the URL path has a leading slash
        if (substr($urlPath, 0, 1) !== '/') {
            $urlPath  = '/' . $urlPath;
        }

        // ensure there are no trailing slashes
        if (strlen($urlPath) > 1 && substr($urlPath, -1) === '/') {
            $urlPath = substr($urlPath, 0, -1);
        }

        // do we need to check Auth or MIME types?
        $callbacks = array();
        if (strpos($urlPath, ':store') !== false) {
            $callbacks[] = array($this, 'callbackCheckDataset');
        }
        if ($authRequired) {
            $callbacks[] = array($this, 'callbackCheckAuth');
        }
        if ($mimeTypes !== null) {
            $callbacks[] = array($this, 'callbackCheckFormats');
        }
        if ($paginate === true) {
            $callbacks[] = array($this, 'callbackCheckPagination');
        } else {
            $this->app->view->set('pagination', false);
        }


        // initialise route
        $httpMethod = strToUpper($httpMethod);
        $route = new \Slim\Route($urlPath, array($this, $funcName));
        $this->app->router()->map($route);
        if (count($callbacks) > 0) {
            $route->setMiddleware($callbacks);
        }
        $route->via($httpMethod);

        // save route data, setting defaults on optional arguments if they are not set
        if ($details === null) {
            $details = $summary;
        }
        $this->routeInfo[$httpMethod . $urlPath] = compact(
            'httpMethod',
            'urlPath',
            'funcName',
            'summary',
            'details',
            'authRequired',
            'mimeTypes',
            'hidden',
            'route' // The slim route object
        );
    }

    /**
     * Initialise dummy $_SERVER parameters if not set (ie command line).
     */
    protected function initialiseServerParameters()
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
     * Middleware callback used to check for valid store.
     * It is not intended that you call this function yourself.
     * @throws \InvalidArgumentException Exception thrown if callback invoked incorrectly.
     */
    public function callbackCheckDataset()
    {
        // get the store name
        $args = func_get_args();
        if (count($args) === 0 || (!$args[0] instanceof \Slim\Route)) {
            throw new \InvalidArgumentException('This method should not be invoked outside of the Slim Framework');
        }
        $this->store = $args[0]->getParam('store');

        // if the store is not valid, skip the current route
        if (!isset($this->stores[$this->store])) {
            $this->app->pass();
        }

        // display name of store in titlebar
        $u = $this->app->request()->getRootUri() . '/datasets/' . $this->store;
        $this->app->view()->set(
            'titleSupplementary',
            '<a href="'.htmlspecialchars($u, ENT_QUOTES).'" class="navbar-brand supplementary">' .
                htmlspecialchars($this->storeOptions[$this->store]['shortName']) . '</a>'
        );
    }

    /**
     * Middleware callback used to add pagination for web application results.
     * Must be executed after { @link callbackCheckDataset() }
     */
    public function callbackCheckPagination()
    {
        // pagination check
        if (isset($this->store) && $this->appOptions['pagination']) {
            // enable pagination in the store
            $this->stores[$this->store]->configurePagination($this->appOptions['num_per_page']);
            $this->app->view->set('pagination', true);
        }
    }

    /**
     * Middleware callback used to check the HTTP authentication is OK.
     * Credentials are read from file named auth.htpasswd in the root directory.
     * It is not intended that you call this function yourself.
     *
     * @throws \SameAsLite\AuthException An exception is thrown if the credentials file cannot be opened
     */
    public function callbackCheckAuth()
    {
        // do we have credentials to validate?
        $authorized = false;

        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            // parse the auth.htpasswd file for username/password
            $filename = dirname($_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF']) . '/auth.htpasswd';
            $credentials = @file($filename, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
            if ($credentials === false || count($credentials) === 0) {
                // auth.htpasswd could not be loaded
                throw new Exception\AuthException('Failed to load valid authorization credentials from ' . $filename);
            }
            foreach ($credentials as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, ':') === false) {
                    continue;
                }
                list($u, $p) = explode(':', $line, 2);

                // salt check
                if (strpos($p, '$') === false) {
                    // no salt present in password
                    // password must be invalid
                    continue;
                }

                // Check plaintext password against an APR1-MD5 hash
                // TODO: get rid of the WhiteHat101 package
                if (true === \WhiteHat101\Crypt\APR1_MD5::check($_SERVER['PHP_AUTH_PW'], $p)) {
                    $authorized = true;
                    break;
                }
            }
        }

        // missing or invalid credentials
        if (!$authorized) {
            $this->outputError401();
        }
    }

    /**
     * Middleware callback used to check MIME types are OK.
     * It is not intended that you call this function yourself.
     * @throws \InvalidArgumentException Exception thrown if callback invoked incorrectly.
     */
    public function callbackCheckFormats()
    {
        // get the acceptable MIME type from route info

        $args = func_get_args();
        if (count($args) === 0 || (!$args[0] instanceof \Slim\Route)) {
            throw new \InvalidArgumentException('This method should not be invoked outside of the Slim Framework');
        }
        $route = $args[0];
        $id = $this->app->request()->getMethod() . $route->getPattern();

        $acceptableMime = $this->routeInfo[$id]['mimeTypes'];

        // perform MIME-type matching on the requested and available formats
        // note that if there are no q-values, the *LAST* type "wins"

        $conneg = new \ptlis\ConNeg\Negotiation();

        $this->mimeBest = $conneg->mimeBest($_SERVER['HTTP_ACCEPT'], $acceptableMime);

        if (!$this->mimeBest) {

            // TODO: need to verify that this is the expected return if there are no matches
            $this->outputError(
                406,
                'Not Acceptable',
                '</p><p>This service cannot return information in the format(s) you requested.',
                'Sorry, we cannot serve you data in your requested format'
            );

        }

        // store alternative MIME types
        foreach ($conneg->mimeAll('*/*', $acceptableMime) as $type) {

            $type = $type->getClientPreference();

            // TODO: this stores objects of type ptlis\ConNeg\Preference\Preference
            // but we want to have a list of content types

            if ($type != $this->mimeBest) {
                $this->mimeAlternatives[] = $type;
            }

        }

        // return best match
        return $this->mimeBest;
    }

    /**
     * If an exception is thrown, the Slim Framework will use this function to
     * render details (if in development mode) or present a basic error page.
     *
     * @param \Exception $e The Exception which caused the error
     */
    public function outputException(\Exception $e)
    {
        $status = 500;
        
        // the user requested an unsupported content type
        if ($e instanceof Exception\ContentTypeException) {

            // this is a client error -> use the correct header in 4XX range
            $status = 406;
            
            // TODO:
            // add the available formats in response header
            // see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.7
            // Unless it was a HEAD request, the response SHOULD include an entity
            // containing a list of available entity characteristics and location(s)
            // from which the user or user agent can choose the one most appropriate.
            // The entity format is specified by the media type given in the Content-Type
            // header field. Depending upon the format and the capabilities of the user agent,
            // selection of the most appropriate choice MAY be performed automatically.
            // However, this specification does not define any standard for such automatic selection.


        } elseif ($e instanceof Exception\AuthException) {

            // TODO




        }


        if ($this->app->getMode() == 'development') {

            // show details if we are in dev mode
            $op  = PHP_EOL;
            $op .= "        <h4>Request details &ndash;</h4>" . PHP_EOL;
            $op .= "        <dl class=\"dl-horizontal\">" . PHP_EOL;
            foreach ($_SERVER as $key => $value) {
                $key = strtolower($key);
                $key = str_replace(array('-', '_'), ' ', $key);
                $key = preg_replace('#^http #', '', $key);
                $key = ucwords($key);
                if (is_array($value)) {
                    $op .= "<dt>$key</dt><dd><pre>" . print_r($value, true) . "</pre></dd>" . PHP_EOL;
                } else {
                    $op .= "          <dt>$key</dt>" . PHP_EOL .
                           "          <dd>$value</dd>" . PHP_EOL;
                }
            }
            $op .= "        </dl>" . PHP_EOL;

            $msg = $e->getMessage();
            $summary = '';
            if (($p = strpos($msg, ' // ')) !== false) {
                $summary = substr($msg, $p + 4) . '</p><p>';
                $msg = substr($msg, 0, $p);
            }

            $this->outputError(
                $status,
                'Server Error',
                $summary . '<strong>' . $e->getFile() . '</strong> &nbsp; +' . $e->getLine(),
                $msg,
                "<h4>Stack trace &ndash;</h4>" . PHP_EOL .
                '        <pre class="white">' . $e->getTraceAsString() . '</pre>' .
                $op
            );

        } else {

            // show basic message
            $this->outputError(
                $status,
                'Unexpected Error',
                '</p><p>Apologies for any inconvenience, the problem has been logged and we\'ll get on to it ASAP.',
                'Whoops! An unexpected error has occured...'
            );

        }
    }

    /**
     * Output a generic error page
     *
     * @param string $status          The HTTP status code (eg 401, 404, 500)
     * @param string $title           Optional brief title of the page, used in HTML head etc
     * @param string $summary         Optional summary message describing the error
     * @param string $extendedTitle   Optional extended title, used at top of main content
     * @param string $extendedDetails Optional extended details, conveying more details
     *
     * @throws \InvalidArgumentException if the status is not a valid HTTP status code
     */
    protected function outputError($status, $title = null, $summary = '', $extendedTitle = '', $extendedDetails = '')
    {
        if (!is_integer($status)) {
            throw new \InvalidArgumentException('The $status parameter must be a valid integer HTTP status code');
        }

        $this->app->response->setStatus($status);
        // slim does not set the response code in this function
        // setting it manually
        if (!headers_sent()) {
            http_response_code($status); // PHP 5.4+
        }

        if (is_null($title)) {
            $title = 'Error ' . $status;
        }

        if ($extendedTitle === '') {
            $defaultMsg = \Slim\Http\Response::getMessageForCode($status);
            if ($defaultMsg !== null) {
                $extendedTitle = substr($defaultMsg, 4);
            } else {
                $extendedTitle = $title;
            }
        }


        // display name of store in titlebar
        if (isset($this->storeOptions[$this->store]['shortName'])) {
            $u = $this->app->request()->getRootUri() . '/datasets/' . $this->store;
            $this->app->view()->set(
                'titleSupplementary',
                '<a href="'.htmlspecialchars($u, ENT_QUOTES).'" class="navbar-brand supplementary">' .
                    htmlspecialchars($this->storeOptions[$this->store]['shortName']) . '</a>'
            );
        }


        // Content negotiation for the error message
        // callbackCheckFormats() middleware does the content negotiation.
        // But it was not executed, yet. Call it now to get the mime type.
        $route = $this->app->router()->getCurrentRoute();
        if ($route) {
            $this->mimeBest = $this->callbackCheckFormats($route);
        }

        switch ($this->mimeBest) {
            case 'text/plain':

                $error  = "status  => $status" . PHP_EOL;
                $error .= "title   => $extendedTitle" . PHP_EOL;
                if ($summary) {
                    $error .= "summary => $summary" . PHP_EOL;
                }
                if ($extendedDetails) {
                    $error .= "details => " . preg_replace('~[\n]+|[\s]{2,}~', ' ', $extendedDetails);
                }

                // setBody does not work in this function
                $this->app->response->setBody($error);
                // render a blank template instead
                // $this->app->render('blank.twig', [ 'content' => $error ] );

                break;

            case 'text/csv':
            case 'text/tab-separated-values':

                if ($this->mimeBest === 'text/csv') {
                    $delimiter = ',';
                } else {
                    $delimiter = "\t";
                }

                $error = [
                    array("status",  $status),
                    array("title",   $extendedTitle)
                ];
                if ($summary) {
                    $error[] = array("summary", $summary);
                }
                if ($extendedDetails) {
                    $error[] = array("details", preg_replace('~[\n]+|[\s]{2,}~', ' ', $extendedDetails));
                }

                ob_start();
                // use fputcsv for escaping
                {
                    $out = fopen('php://output', 'w');
                    foreach ($error as $err) {
                        fputcsv($out, $err, $delimiter);
                    }
                    fclose($out);
                    $out = ob_get_contents();
                }
                ob_end_clean();

                // setBody does not work in this function
                $this->app->response->setBody($out);
                // render a blank template instead
                // $this->app->render('blank.twig', [ 'content' => $out ] );

                break;

            case 'application/rdf+xml':
            case 'application/x-turtle':
            case 'text/turtle':
            case 'application/json':

                $json_error = array(
                    'status'  => $status,
                    'title'   => $extendedTitle
                );
                if ($summary) {
                    $json_error['summary'] = $summary;
                }
                if ($extendedDetails) {
                    $json_error['details'] = $extendedDetails;
                }

                // setBody does not work in this function
                $this->app->response->setBody(json_encode($json_error, JSON_PRETTY_PRINT)); // PHP 5.4+
                // render a blank template instead
                // $this->app->render('blank.twig', [ 'content' => json_encode($json_error, JSON_PRETTY_PRINT) ] );

                break;

            case 'text/html':
            case 'application/xhtml+xml':
            default:

                $summary .= '</p>' . PHP_EOL . PHP_EOL . '<p>Please try returning to <a href="' .
                    $this->app->request()->getURL() .
                    $this->app->request()->getRootUri() . '">the homepage</a>.';

                // overwrite the previous content type definition
                $this->app->contentType($this->mimeBest);

                $this->app->render('error/error-html.twig', [
                    'titleHTML'    => ' - ' . strip_tags($title),
                    'titleHeader'  => 'Error ' . $status,
                    'title'        => $extendedTitle,
                    'summary'      => $summary,
                    'details'      => $extendedDetails
                ]);

                break;
        }

        // execution stops here
        $this->app->stop();

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
     * Output a 401 (unauthorized) error
     */
    public function outputError401()
    {
        $this->app->response->headers->set('WWW-Authenticate', 'Basic realm="SameAs Lite"');

        $this->outputError(
            401,
            'Access Denied',
            'You have failed to supply valid credentials, access to this resource is denied.',
            'Access Denied'
        );
    }


    /**
     * Application homepage
     */
    public function homepage()
    {
        // Mirror list stores for now
        $this->listStores();
    }

    /**
     * Store homepage
     *
     * @param string $storeSlug The URL slug identifying the store
     */
    public function storeHomepage($storeSlug)
    {
        $soptions = $this->storeOptions[$storeSlug];
        $store = $this->stores[$storeSlug];

        // Get the store's statistics
        $stats = $store->statistics();


        $viewData = array_merge($soptions, [
            'titleHTML' => " - " . $soptions['shortName'],
            'titleHeader' => $soptions['fullName'],
            'statistics' => $stats,
            'apiPath' => "datasets/$storeSlug/api"
        ]);

        $this->app->view()->set(
            'javascript',
            '<script src="'. $this->app->request()->getRootUri() . '/assets/js/homepage-store.js"></script>'
        );

        $this->app->render('page/homepage-store.twig', $viewData);
    }

    /**
     * Render the about page
     */
    public function aboutPage()
    {
        $this->app->render('page/about.twig', [
            'titleHTML'    => ' - About SameAsLite',
            'titleHeader'  => 'About SameAsLite',
            'storeOptions' => $this->storeOptions

        ]);
    }

    /**
     * Render the contact page
     */
    public function contactPage()
    {
        $this->app->render('page/contact.twig', [
            'titleHTML'    => ' - Contact',
            'titleHeader'  => 'Contact',
            'storeOptions' => $this->storeOptions
        ]);
    }

    /**
     * Render license used by SameAsLite
     */
    public function licensePage()
    {
        $this->app->render('page/license.twig', [
            'titleHTML'    => ' - SameAsLite License',
            'titleHeader'  => 'SameAsLite License'
        ]);
    }

    /**
     * A simple page which gives basic advice on how to use the services
     * @param string|null $store Optional URL slug identifying a store. If present, page is
     * rendered for that specific store, or if null then a generic rendering is produced.
     */
    public function api($store = null)
    {

        $routes = [];

        // iterate over all routes
        foreach ($this->routeInfo as $info) {
            if (!isset($info['hidden']) || !$info['hidden']) {
                $ri = $this->getRouteInfoForTemplate($info, $store);
                //do not show /datasets on store api page
                if ($store !== null && $info['urlPath'] === '/datasets') {
                    continue;
                }
                $routes[] = $ri;
            }
        }

        // template variables (basic info)
        if (isset($this->storeOptions[$store]['shortName'])) {
            $this->app->view()->set('shortName', $this->storeOptions[$store]['shortName']);
        }

        // inject javascript for the API page
        $this->app->view()->set(
            'javascript',
            '<script src="'. $this->app->request()->getRootUri() . '/assets/js/api.js"></script>'
        );

        // render the template
        $this->app->render('page/api-index.twig', [
            'titleHTML' => ' - API',
            'titleHeader' => 'API overview' . ($this->storeOptions[$store]['shortName'] ? ' for ' . $this->storeOptions[$store]['shortName'] : ''),
            'routes' => $routes
        ]);
    }

    /**
     * Returns the information needed to render a route in page/api-index.twig
     *
     * @param array       $info  The routeInfo for the route being described
     * @param string|null $store Optional specific store slug
     *
     * @return array                Array describing this route for the template
     */
    protected function getRouteInfoForTemplate(array $info, $store = null)
    {
        $rootURI = $this->app->request()->getRootUri();
        $host = $this->app->request()->getUrl();
        $method = $info['httpMethod'];
        $map = array(
            'GET' => 'info',
            'DELETE' => 'danger',
            'PUT' => 'warning',
            'POST' => 'success'
        );

        if ($method === 'GET') {
            $formMethod = 'GET';
        } else {
            $formMethod = 'POST';
        }

        // reverse formats
        $formats = join(', ', array_reverse(explode(',', $info['mimeTypes'])));

        // ensure all variables are in {foo} format, rather than :foo
        $endpointURL = $rootURI . $info['urlPath'];
        $endpointURL = preg_replace('@:([^/]*)@', '{\1}', $endpointURL);
        if ($store !== null) {
            $endpointURL = str_replace('{store}', $store, $endpointURL);
        }

        // apply HTML formatting to variables
        $endpointHTML = preg_replace('@(\{[^}]*})@', '<span class="api-parameter">\1</span>', $endpointURL);

        preg_match_all('@<span class="api-parameter">{(.*?)}</span>@', $endpointHTML, $inputs);
        $parameters = $inputs[1];
        // echo "<pre>" . print_r($inputs, true) . "</pre>";

        $id = crc32($method . $info['urlPath']);


        // auth required?
        $authString = ($info['authRequired'] ? ' --user username:password' : '');

        if (count($parameters) === 0 && ($method === 'PUT' || $method === 'POST')) {
            // Upload file command line string
            $cmdLine = "curl --upload-file data.tsv $authString $host$endpointHTML";
        } else {
            $cmdLine = "curl -X $method $authString $host$endpointHTML";
        }


        return [
            'id'            => $id,
            'method'        => $method,
            'methodClass'   => $map[$method],
            'formMethod'    => $formMethod,
            'formats'       => $formats,
            'summary'       => $info['summary'],
            'details'       => $info['details'],
            'authRequired'  => !!$info['authRequired'],
            'commandLine'   => $cmdLine,
            'endpointURL'   => $endpointURL,
            'endpointHTML'  => $endpointHTML,
            'parameters'    => $parameters,
        ];
    }



    /**
     * Actions the HTTP DELETE service from /admin/delete
     *
     * Simply passes the request on to the deleteStore sameAsLite Class method.
     * Reports success, since failure will have caused an exception
     *
     * @param string $store The URL slug identifying the store
     */
    public function deleteStore($store)
    {
        $this->stores[$store]->deleteStore();
        $this->outputSuccess('Store deleted');
    }

    /**
     * Actions the HTTP PUT service from /datasets/:store
     *
     * For non-empty request body:
     * Updates the store's contents with the data from the request body.
     * Reports success with HTTP status 204
     *
     * For empty request body:
     * Simply passes the request on to the emptyStore() if the request body is empty.
     * Reports success with HTTP status 204, since failure will have caused an exception
     *
     * @param string $store The URL slug identifying the store
     */
    public function updateStore($store)
    {

        $body = $this->app->request->getBody();

        // from web query, the body is this: string(17) "_METHOD=PUT&body="

        // filter out the _METHOD parameter

        $body = preg_replace('~_METHOD=.+&body=~i', '', $body);

        if (empty($body)) {

            // PUT request with empty body => remove the contents of the store
            $this->emptyStore($store);

        } else {
            // PUT request with non-empty body => update (replace) the contents of the store

            // TODO - need to detect the type of the incoming data
            die('TODO (no data inserted)');







            // determine the type of incoming data
            $contentType = $this->app->request->getContentType(); // from web: application/x-www-form-urlencoded"
            var_dump($contentType);die;


            $body = json_decode($body);



        }

    }

    /**
     * Actions the HTTP DELETE service from /admin/empty
     *
     * Simply passes the request on to the emptyStore sameAsLite Class method.
     * Reports success, since failure will have caused an exception
     *
     * @param string $store The URL slug identifying the store
     */
    public function emptyStore($store)
    {
        $this->stores[$store]->emptyStore();
        $this->outputSuccess('Store emptied');
    }

    /**
     * Actions the HTTP PUT service from /admin/restore/:file
     *
     * Simply passes the request on to the restoreStore sameAsLite Class method.
     * Reports success, since failure will have caused an exception
     *
     * @param string $store The URL slug identifying the store
     * @param string $file  The local (server) filename to get the previous dump from
     */
    // public function restoreStore($store, $file)
    // {
    //     $this->stores[$store]->restoreStore($file);
    //     $this->outputSuccess("Store restored from file '$file'");
    // }

    /**
     * Actions the HTTP GET service from /canons
     *
     * Simply passes the request on to the allCanons sameAsLite Class method.
     * Outputs the canons in the format requested
     *
     * @param string $store The URL slug identifying the store
     */
    public function allCanons($store)
    {
        $this->app->view()->set('titleHTML', 'Canons');
        $this->app->view()->set('titleHeader', 'All Canons in this dataset');
        $results = $this->stores[$store]->getAllCanons();

        if ($this->isRDFRequest()) {
            $this->outputRDF($results, 'list', 'ns:canon');
        } else {
            $this->outputList($results, true); //numeric list
        }
    }

    /**
     * Actions the HTTP PUT service from /canons/:symbol
     *
     * Simply passes the request on to the setCanon sameAsLite Class method.
     * Reports success, since failure will have caused an exception
     *
     * @param string $store  The URL slug identifying the store
     * @param string $symbol The symbol that we want to make the canon
     */
    public function setCanon($store, $symbol)
    {
        $this->stores[$store]->setCanon($symbol);
        // escaping for output
        $symbol = htmlspecialchars($symbol);
        $this->outputSuccess("Canon set to '$symbol'");
    }


    /**
     * Actions the HTTP GET service from /canons/:symbol
     *
     * Simply passes the request on to the getCanon sameAsLite Class method.
     * Outputs the canon in the format requested
     *
     * @param string $store  The URL slug identifying the store
     * @param string $symbol The symbol that we want to find the canon of
     */
    public function getCanon($store, $symbol)
    {
        $this->app->view()->set('titleHTML', 'Canon query');
        $this->app->view()->set('titleHeader', 'Canon for \'' . $symbol . '\'');
        $canon = $this->stores[$store]->getCanon($symbol);
        if (!$canon) {
            $results = [];
        } else {
            $results = [$canon];
        }
        $this->outputList($results);
    }

    /**
     * Actions the HTTP GET service from /pairs
     *
     * Simply passes the request on to the dumpStore sameAsLite Class method.
     * Outputs symbols pairs in the format requested
     *
     * @param string $store The URL slug identifying the store
     */
    public function dumpStore($store)
    {
        $this->app->view()->set('titleHTML', 'All pairs');
        $this->app->view()->set('titleHeader', 'Contents of the store:');

        $result = $this->stores[$store]->dumpPairs();

        $this->outputTable($result, array('canon', 'symbol'));
    }

    /**
     * Actions the HTTP PUT service from /pairs
     *
     * Simply passes the request on to the assertPairs sameAsLite Class method.
     * Reports the before and after statistics; failure will have caused an exception
     *
     * @param string $store The URL slug identifying the store
     *
     * @throws \SameAsLite\InvalidRequestException An exception is thrown if the request body is empty
     */
    public function assertPairs($store)
    {
        // TODO - if the input is very large, this will probably blow up available memory?
        // $before = $this->stores[$store]->statistics();

        // if request body is empty, there is nothing to assert
        $body = $this->app->request->getBody();
        if (!$body) { // body no longer exists in request obj
            throw new Exception\InvalidRequestException('Empty request body. Nothing to assert.'); // TODO : this would also require HTTP status
        } else {
            $this->stores[$store]->assertTSV($body);

            // $after = $this->stores[$store]->statistics();
            // TODO array_merge(array('Before:'), $before, array('After:'), $after));

            $this->outputSuccess('Pairs asserted');
        }
    }

    /**
     * Actions the HTTP PUT service from /pairs/:symbol1/:symbol2
     *
     * Simply passes the request on to the assertPair sameAsLite Class method.
     * Reports success, since failure will have caused an exception
     *
     * @param string $store   The URL slug identifying the store
     * @param string $symbol1 The first symbol of the pair
     * @param string $symbol2 The second symbol of the pair
     */
    public function assertPair($store, $symbol1, $symbol2)
    {
        $this->stores[$store]->assertPair($symbol1, $symbol2);

        $this->app->view()->set('titleHTML', 'Pair asserted');
        $this->app->view()->set('titleHeader', 'Pair asserted');

        // escaping for output
        $symbol1 = htmlspecialchars($symbol1);
        $symbol2 = htmlspecialchars($symbol2);

        $this->outputSuccess("The pair ($symbol1, $symbol2) has been asserted");
    }

    /**
     * Actions the HTTP GET service from search/:string
     *
     * Simply passes the request on to the search sameAsLite Class method.
     * Outputs the symbol in the format requested
     *
     * @param string $store  The URL slug identifying the store
     * @param string $string The sub-string that we want to look for
     */
    public function search($store, $string)
    {
        $this->app->view()->set('titleHTML', ' - Search: "' . $string . '"');
        $this->app->view()->set('titleHeader', 'Search: "' . $string . '"');

        $results = $this->stores[$store]->search($string);
        $this->outputList($results);
    }

    /**
     * Actions the HTTP GET service from /symbols/:symbol
     *
     * Simply passes the request on to the querySymbol sameAsLite Class method.
     * Outputs the symbol in the format requested
     *
     * @param string $store  The URL slug identifying the store
     * @param string $symbol The symbol to be looked up
     */
    public function querySymbol($store, $symbol)
    {
        $accept = $this->app->request->headers->get('Accept');

        if (isset($this->mimeBest) && $this->mimeBest !== 'text/html') {
            // non-HTML output

            $results = $this->stores[$store]->querySymbol($symbol);
            $results = array_diff($results, [ $symbol ]);

            $this->mimeBest = $accept;
            $this->outputList($results);
        } else {
            // HTML output

            $shortName = $this->storeOptions[$store]['shortName'];
            $this->app->view()->set('titleHTML', ' - ' . $symbol . ' in ' . $shortName);
            $this->app->view()->set('titleHeader', $symbol . ' in ' . $shortName);


            $results = $this->stores[$store]->querySymbol($symbol);

            if (count($results) > 0) {
                $canon = $this->stores[$store]->getCanon($symbol);

                // Remove the queried symbol from the results
                $results = array_diff($results, [ $symbol ]);

                // Linkify the results
                foreach ($results as &$result) {
                    $result = $this->linkify($result);
                }

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                // render the page
                $this->app->render('snippet/bundle.twig', [
                    'symbol' => $symbol,
                    'equiv_symbols' => $results,
                    'canon' => $canon
                ]);

            } else {
                $this->outputHTML("Symbol &ldquo;$symbol&rdquo; not found in the store", 404);
            }
        }
    }

    /**
     * Actions the HTTP DELETE service from /symbols/:symbol
     *
     * Simply passes the request on to the removeSymbol sameAsLite Class method.
     * Reports success, since failure will have caused an exception
     *
     * @param string $store  The URL slug identifying the store
     * @param string $symbol The symbol to be removed
     */
    public function removeSymbol($store, $symbol)
    {
        $result = $this->stores[$store]->removeSymbol($symbol);
        if ($result === true) {
            $this->outputSuccess('Symbol deleted');
        } else {
            $this->outputError(
                400,
                'Symbol not deleted'
            );
        }
    }

    /**
     * Actions the HTTP GET service from /status
     *
     * Simply passes the request on to the statistics sameAsLite Class method.
     * Outputs the results in the format requested
     *
     * @param string $store The URL slug identifying the store
     */
    public function statistics($store)
    {
        $shortName = $this->storeOptions[$store]['shortName'];
        $this->app->view()->set('titleHTML', ' - Statistics ' . $shortName);
        $this->app->view()->set('titleHeader', 'Statistics ' . $shortName);

        $result = $this->stores[$store]->statistics();

        // content negotiation
        switch ($this->mimeBest) {
            case 'text/html':
            case 'application/xhtml+xml':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                // fall through

            case 'text/tab-separated-values':
            case 'text/csv':

                // output as a table

                $rows = $headers = array();
                foreach ($result as $header => $value) {
                    $rows[] = $value;
                    $headers[] = $header;
                }

                $this->outputTable(array($rows), $headers);

                break;

            case 'text/plain':

                $out = '';
                foreach ($result as $header => $value) {
                    $out .= $header . " => " . $value . PHP_EOL;
                }

                $this->app->response->setBody($out);

                break;

            default:

                $this->outputList($result, false, 200);

                break;
        }

    }//end statistics()

    /**
     * Actions the HTTP GET service from /datasets
     *
     * Simply passes the request on to the listStores sameAsLite Class method.
     * Outputs the results in the format requested
     */
    public function listStores()
    {
        switch ($this->mimeBest) {
            case 'text/plain':

                ob_start();
                {
                    $out = fopen('php://output', 'w');
                    $url = $this->app->request->getUrl();
                    foreach ($this->storeOptions as $i) {
                        $o = $i['shortName'] . ' => ' . $url . '/datasets/' . $i['slug'] . PHP_EOL;
                        fwrite($out, $o);
                    }
                    fclose($out);
                }
                $out = ob_get_contents();
                ob_end_clean();

                $this->app->response->setBody($out);

                break;

            case 'text/csv':
            case 'text/tab-separated-values':

                ob_start();
                {
                    $out = fopen('php://output', 'w');
                    $url = $this->app->request->getUrl();

                    // delimiter for CSV
                    $delimiter = ',';
                    // delimiter for TSV
                    if ($this->mimeBest === 'text/tab-separated-values') {
                        $delimiter = "\t";
                    }

                    fputcsv($out, ['name', 'url'], $delimiter);
                    foreach ($this->storeOptions as $i) {
                        $o = [
                            $i['shortName'],
                            $url . '/datasets/' . $i['slug']
                        ];
                        fputcsv($out, $o, $delimiter);
                    }
                }
                $out = ob_get_contents();
                ob_end_clean();

                $this->app->response->setBody($out);

                break;

            case 'application/json':
                $out = [];
                $url = $this->app->request->getUrl();

                foreach ($this->storeOptions as $k => $i) {
                    $out[$k] = [
                        'name' => $i['shortName'],
                        'url' => $url . '/datasets/' . $i['slug']
                    ];
                    // only add the full store name if it differs from the short name 
                    if ($i['shortName'] !== $i['fullName']) {
                        $out[$k]['description'] = $i['fullName'];
                    }
                }

                $this->app->response->setBody(json_encode($out, JSON_PRETTY_PRINT)); // PHP 5.4+

                break;

            case 'application/rdf+xml':
            case 'text/turtle':
            case 'application/x-turtle':

                $out = [];
                $url = $this->app->request->getUrl();

                foreach ($this->storeOptions as $i) {
                    $out[$i['shortName']] = [
                        'dc:type' => 'Store',
                        'rdfs:label' => $i['shortName'],
                        'url' => $url . '/datasets/' . $i['slug']
                    ];

                    // only add the full store name if it differs from the short name 
                    if ($i['shortName'] !== $i['fullName']) {
                        $out[$i['shortName']]['dc:description'] = $i['fullName'];
                    }
                }

                $this->outputArbitrary($out);

                break;

            case 'text/html':
            case 'application/xhtml+xml':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                $this->app->render('page/storeList.twig', [
                    'titleHTML' => ' ',
                    'titleHeader' => 'Datasets',
                    'stores' => $this->storeOptions
                ]);

                break;

            default:
                // TODO: this should notify about the available formats in the HTTP response headers
                $this->outputError(400, "Cannot return in format requested");

                break;

        }
        
        
    }

    /**
     * Actions the HTTP GET service from /analysis
     *
     * Simply passes the request on to the analyse sameAsLite Class method.
     * Outputs the results in the format requested
     *
     * @param string $store The URL slug identifying the store
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    public function analyse($store)
    {
        $shortName = $this->storeOptions[$store]['shortName'];
        $this->app->view()->set('titleHTML', ' - Analyse ' . $shortName);
        $this->app->view()->set('titleHeader', 'Analyse ' . $shortName);

        $result = $this->stores[$store]->analyse();

        switch ($this->mimeBest) {

            case 'text/plain':

                $this->app->response->setBody(print_r($result, true));

                break;

            case 'application/json':

                $this->app->response->setBody(json_encode($result, JSON_PRETTY_PRINT));

                break;

            case 'text/html':
            case 'application/xhtml+xml':
                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                // old way:
                // $this->outputHTML('<pre>' . print_r($result, true) . '</pre>');
                $this->outputTable($result); // headers are contained in the multidimensional array

                break;

            default:
                throw new Exception\ContentTypeException('Could not render analysis as ' . $this->mimeBest);
        }
    }

    /**
     * Output an HTML page
     *
     * @param mixed   $body   The information to be displayed
     * @param integer $status The HTTP status to return with the HTML
     */
    protected function outputHTML($body, $status = null)
    {
        // set default template values if not present
        $defaults = array(
            'titleHTML' => ' - [titleHTML]',
            'titleHeader' => '[titleHeader]'
        );
        foreach ($defaults as $key => $value) {
            if ($this->app->view()->get($key) == null) {
                $this->app->view()->set($key, $value);
            }
        }

        // fold arrays into PRE blocks
        if (is_array($body)) {
            $body = '<pre>' . join("\n", $body) . "</pre>\n";
        }

        if (!is_null($status)) {
            $this->app->response->setStatus($status);
        }

        $this->app->render('page.twig', [
            'body'    => $body
        ]);

    }

    /**
     * Output a success message, in the most appropriate MIME type
     *
     * @param string $msg The information to be displayed
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputSuccess($msg, $status = 200)
    {
        $this->app->response->setStatus($status);

        // headline for template
        $this->app->view()->set('titleHTML', ' - Result');
        $this->app->view()->set('titleHeader', 'Result of operation on ' . $this->storeOptions[$this->store]['shortName']);

        switch ($this->mimeBest) {
            case 'text/plain':
            case 'text/csv':

                $this->app->response->setBody($msg . PHP_EOL);

                break;

            case 'application/json':

                $this->app->response->setBody(json_encode(array('ok' => $msg)) . PHP_EOL);

                break;

            case 'text/html':
            case 'application/xhtml+xml':

                $this->outputHTML('<h2>Success!</h2><p>' . $msg . '</p>') . PHP_EOL;

                break;

            default:
                throw new Exception\ContentTypeException('Could not render success output as ' . $this->mimeBest);
        }
    }

    /**
     * Output data which is an keyed list of items, in the most appropriate
     * MIME type
     *
     * @param array   $list          The items to output
     * @param integer $status        HTTP status code
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputArbitrary(array $list = array(), $status = null)
    {
        // escaping for output
        array_walk($list, 'self::escapeInputArray');

        if (!is_null($status)) {
            $this->app->response->setStatus($status);
        }//end if

        switch ($this->mimeBest) {

            case 'application/rdf+xml':
            case 'text/turtle':
            case 'application/x-turtle':

                $domain = 'http://';
                if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]) {
                    $domain = "https://";
                }//end if
                $domain .= $_SERVER["SERVER_NAME"];
                if ($_SERVER["SERVER_PORT"] != "80") {
                    $domain .= ":" . $_SERVER["SERVER_PORT"];
                }//end if


                // EasyRdf graph
                $graph = new \EasyRdf_Graph();

                foreach ($list as $key => $arr) {

                    if (isset($arr['url'])) {
                        $url = $arr['url'];
                        unset($arr['url']);
                    } else {
                        $url = $domain . $_SERVER['REQUEST_URI'];
                    }

                    $resource = $graph->resource($url);

                    if (is_array($arr)) {
                        foreach ($arr as $predicate => $value) {
                            if ($value) {
                                if (strpos($value, 'http') === 0) {
                                    $graph->addResource($resource, $predicate, $graph->resource(urldecode($value)));
                                } else {
                                    $graph->addLiteral($resource, $predicate, $value);
                                }
                            }
                        }
                    } else {
                            if (strpos($arr, 'http') === 0) {
                                $graph->addResource($resource, $key, $graph->resource(urldecode($arr)));
                            } else {
                                $graph->addLiteral($resource, $key, $arr);
                            }
                    }
                }

                if ($this->mimeBest === 'application/rdf+xml') {
                    $format = 'rdf';
                } else {
                    $format = 'turtle';
                }

                $data = $graph->serialise($format);
                //if (!is_scalar($data)) {
                //    $data = var_export($data, true);
                //}

                $this->app->response->setBody(trim($data));

                break;


            case 'text/plain':

                $this->app->response->setBody(print_r($list, true));

                break;

            case 'application/json':

                $this->app->response->setBody(json_encode($list, JSON_PRETTY_PRINT)); // PHP 5.4+

                break;

            case 'text/html':
            case 'application/xhtml+xml':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                $list = array_map([ $this, 'linkify' ], $list); // Map array to linkify the contents

                $this->app->render('page/output.twig', [
                    'list' => '<pre>' . print_r($list, true) . '</pre>'
                ]);

                break;

            default:
                throw new Exception\ContentTypeException('Could not render list output as ' . $this->mimeBest);
        }
    }


    /**
     * Output data in RDF and Turtle format
     *
     * @param array   $list      The items to output
     * @param string  $format    The output format
     * @param string  $predicate The predicate for the list
     * @param integer $status    HTTP status code
     *
     * @uses \EasyRdf_Graph
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputRDF(array $list = array(), $format = 'list', $predicate = 'owl:sameAs', $status = null)
    {
        // escaping for output
        // array_walk($list, 'self::escapeInputArray');

        // get the query parameter
        $symbol = $this->app->request()->params('string');
        if (!$symbol) {
            $symbol = $this->app->request()->params('symbol');
        }//end if
        $symbol = $symbol ? $symbol : false;

        $store = $this->store ? $this->store : false;

        // meta info
        $domain = 'http://';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]) {
            $domain = "https://";
        }//end if
        $domain .= $_SERVER["SERVER_NAME"];
        if ($_SERVER["SERVER_PORT"] != "80") {
            $domain .= ":" . $_SERVER["SERVER_PORT"];
        }//end if


        // EASY RDF

        $graph = new \EasyRdf_Graph();

        $meta_block = $graph->resource($domain . $_SERVER['REQUEST_URI']);
        // TODO: maybe also add info about store (storename, URI)?
        $meta_block->set('dc:creator', 'sameAsLite');
        // $meta_block->set('dc:title', 'Co-references from sameAs.org for ' . $symbol);
        if (isset($this->appOptions['license']['url'])) {
            $meta_block->add('dct:license', $graph->resource($this->appOptions['license']['url']));
        }

        // list
        if (!$format === 'list') {
            //sameAs relationships
            if (strpos($symbol, 'http') === 0) {
                $symbol_block = $graph->resource($symbol);
                $meta_block->add('foaf:primaryTopic', $graph->resource(urldecode($symbol)));
            } else {
                $symbol_block = $graph->newBNode();
                $meta_block->add('foaf:primaryTopic', $graph->resource('_:' . $symbol_block->getBNodeId()));
            }
            $predicate = 'owl:sameAs';
            foreach ($list as $s) {
                if (strpos($s, 'http') === 0) {
                    // resource
                    $symbol_block->add($predicate, $graph->resource(urldecode($s)));
                } else {
                    // literal values - not technically correct, because sameAs expects a resource
                    // but validates in W3C Validator
                    $symbol_block->add($predicate, $s);
                }
            }
        } else {
            //simple list


            $ns = array();
            if (strpos($predicate, ':') !== false) {

                // create the namespace from the incoming predicate (<ns>:<predicate>)
                $ns = explode(':', $predicate);
                $ns['ns'] = $ns[0];
                $ns['slug'] = $ns[1];

            } elseif (isset($this->appOptions['namespace']['prefix']) && isset($this->appOptions['namespace']['slug'])) {
            // fallback: namespace from config.ini

                $ns['ns'] = $this->appOptions['namespace']['prefix'];
                // remove slashes
                $ns['slug'] = trim($this->appOptions['namespace']['slug'], '/ ');

            } else {

                throw new \Exception("Expecting namespace setting in config.ini or $predicate parameter to be namespaced as <ns>:<predicate>");

            }


            \EasyRdf_Namespace::set($ns['ns'], 'http://sameas.org/' . $ns['slug'] . '/');
            $symbol_block = $graph->resource($domain . '/datasets/' . $this->store. '/' . $ns['slug'] . '/');


            foreach ($list as $s) {
                if (strpos($s, 'http') === 0) {
                    // resource
                    $symbol_block->add($predicate, $graph->resource(urldecode($s)));
                } else {
                    // literal values - not technically correct, because sameAs expects a resource
                    // but validates in W3C Validator
                    $symbol_block->add($predicate, $s);
                }
            }
        }

        if ($this->mimeBest === 'application/rdf+xml') {
            $outFormat = 'rdf';
        } else {
            $outFormat = 'turtle';
        }

        $data = $graph->serialise($outFormat);
        if (!is_scalar($data)) {
            $data = var_export($data, true);
        }

        $this->app->response->setBody($data);
    }

    /**
     * Output data which is an unordered list of items, in the most appropriate
     * MIME type
     *
     * @param array   $list          The items to output
     * @param boolean $numeric_array Convert the array into a numerically-keyed array, if true
     * @param integer $status        HTTP status code
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputList(array $list = array(), $numeric_array = true, $status = null)
    {
        // Convert into numeric array, if required
        if ($numeric_array) {
            $list = array_values($list);
        }//end if

        // pagination check
        if (empty($list)) {

            $this->app->view->set('pagination', false);

        } elseif ($this->stores[$this->store]->isPaginated()) {
            // add pagination buttons to the template
            $this->app->view()->set('currentPage', $this->stores[$this->store]->getCurrentPage());
            // $this->app->view()->set('numResults', count($list));
            $this->app->view()->set('maxPageNum', (int) ceil($this->stores[$this->store]->getMaxResults() / $this->appOptions['num_per_page']));
        }

        if (!is_null($status)) {
            $this->app->response->setStatus($status);
        }//end if

        switch ($this->mimeBest) {
            case 'text/plain':

                $this->app->response->setBody(join(PHP_EOL, $list));

                break;

            case 'text/csv':
            case 'text/tab-separated-values':

                ob_start();
                // use fputcsv for escaping
                {
                    $delimiter = PHP_EOL;
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $list, $delimiter);
                    fclose($out);
                    $out = ob_get_contents();
                }
                ob_end_clean();

                $this->app->response->setBody(join(PHP_EOL, $list));

                break;

            case 'application/json':

                // convert numbers
                foreach ($list as &$v) {
                    if (is_numeric($v)) {
                        $v = intval($v);
                    }
                }

                $this->app->response->setBody(json_encode($list, JSON_PRETTY_PRINT)); // PHP 5.4+

                break;

            case 'text/html':
            case 'application/xhtml+xml':

                // escaping for output
                array_walk($list, 'self::escapeInputArray');

                $list = array_map([ $this, 'linkify' ], $list); // Map array to linkify the contents

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                $this->app->render('page/list.twig', [
                    'list' => $list
                ]);

                break;

            default:
                throw new Exception\ContentTypeException('Could not render list output as ' . $this->mimeBest);
        }
    }

    /**
     * All incoming data must be escaped for output on the webpage
     *
     * @param array $data    The rows to output
     * @param array $headers Column headers
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function escapeInputArray(&$value, $key) {
        // value(s)
        if (!is_array($value)) {
            $value = htmlspecialchars($value);
        } else {
            // clean recursively
            foreach ($value as $k => &$v) {
                $this->escapeInputArray($v, $k);
            }
        }
        // key
        // if (!is_numeric($key)) {
        //     $key = htmlspecialchars($key);
        // }
    }

    /**
     * Output tabular data, in the most appropriate MIME type
     *
     * @param array $data    The rows to output
     * @param array $headers Column headers
     *
     * @throws \SameAsLite\Exception\ContentTypeException An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputTable(array $data, array $headers = array())
    {
        // pagination check
        if (empty($data)) {

            $this->app->view()->set('pagination', false);

        } elseif ($this->stores[$this->store]->isPaginated()) {
            // add pagination buttons to the template
            $this->app->view()->set('currentPage', $this->stores[$this->store]->getCurrentPage());
            // $this->app->view()->set('numResults', count($data));
            $this->app->view()->set('maxPageNum', (int) ceil($this->stores[$this->store]->getMaxResults() / $this->appOptions['num_per_page']));
        }


        switch ($this->mimeBest) {
            case 'text/csv':
            case 'text/tab-separated-values':

                if ($this->mimeBest === 'text/tab-separated-values') {
                    $delimiter = "\t";
                } else {
                    $delimiter = ",";
                }

                ob_start();
                {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $headers, $delimiter);
                    foreach ($data as $i) {
                        fputcsv($out, $i, $delimiter);
                    }
                    fclose($out);
                }
                $out = ob_get_contents();
                ob_end_clean();

                $this->app->response->setBody($out);

                break;

            case 'text/plain':

                ob_start();
                {
                    $out = fopen('php://output', 'w');
                    // fwrite($out, implode(' => ', $headers) . PHP_EOL);
                    foreach ($data as $i) {
                        fwrite($out, implode(' => ', $i) . PHP_EOL);
                    }
                    fclose($out);
                }
                $out = ob_get_contents();
                ob_end_clean();

                $this->app->response->setBody($out);

                break;

            case 'application/rdf+xml':
            case 'text/turtle':
            case 'application/x-turtle':
                $this->outputArbitrary(array_merge($headers, $data));
                break;

            case 'application/json':
                $op = array();
                foreach ($data as $row) {
                    $op[] = array_combine($headers, $row);
                }

                $this->app->response->setBody(json_encode($op, JSON_PRETTY_PRINT)); // PHP 5.4+

                break;

            // full webpage output
            case 'text/html':
            case 'application/xhtml+xml':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                // escaping for output
                array_walk($headers, 'self::escapeInputArray');
                array_walk($data, 'self::escapeInputArray');

                $tables = array();

                // no headers were given
                // turn the array keys into table headlines
                // use the sub-keys in the first column
                // and the array values in the second column
                if (!$headers && $this->countdim($data) === 2) {

                    foreach ($data as $hdr => $dat) {

                        // reset the table
                        $subtabledata = array();

                        if (is_array($dat)) {
                            foreach ($dat as $k => $v) {
                                if (is_array($v)) {
                                    $hdr = $k;
                                    // TODO
                                    //add a new data row with key and value
                                    foreach ($v as $uk => $uv) {
                                        $subtabledata[] = array($uk, $uv);
                                    }
                                } else {
                                    //add a new data row with key and value
                                    $subtabledata[] = array($k, $v);
                                }
                            }
                        } else {
                            $subtabledata[] = array($hdr, $dat);
                        }

                        $tables[] = array(
                            'title' => $hdr,
                            'headers' => array(),
                            "data"    => $subtabledata
                        );

                    }

                    // var_dump($tables);die;

                } else {

                    $tables[] = array(
                        'headers' => $headers,
                        "data"    => $data
                    );
                    foreach ($data as &$d) {
                        if (!is_array($d)) {
                            $d = array_map([ $this, 'linkify' ], $d);
                            // $d = $this->linkify($d);
                        }
                    }

                }

                $this->app->render('page/table.twig', array('tables' => $tables));

                break;

            default:
                throw new Exception\ContentTypeException('Could not render tabular output as ' . $this->mimeBest);
        }
    }

    /**
     * This function turns strings into HTML links, if appropriate
     *
     * @param string $item The item to convert
     * @return string The original item, or a linkified version of the item
     */
    protected function linkify($item)
    {
        // what if the item is a symbol 'http'?
        // if (substr($item, 0, 4) === 'http') {
        if (
            !is_array($item) &&
            (
                //URL
                filter_var($item, FILTER_VALIDATE_URL)
                //email and some other linkable url schemes
                || strpos($item, 'mailto:') === 0
                || strpos($item, 'ftp:') === 0
                || strpos($item, 'news:') === 0
                || strpos($item, 'nntp:') === 0
                || strpos($item, 'telnet:') === 0
                || strpos($item, 'wais:') === 0
            )
        ) {
            return '<a href="' . $item . '">' . $item . '</a>';
        }
        return $item;
    }

    /**
     * This function adds the clickable labels with alternate formats to the results webpage
     *
     * @return void
     */
    protected function prepareWebResultView()
    {
        // mime type buttons
        // TODO:
        // get mimetypes of the current route and
        // only output the buttons for the allowed mimetypes of that route
        // $currentRouteAcceptableMimeTypes = $this->app->router()->getCurrentRoute();

        // $formats = (isset($this->routeInfo->mimeTypes) ? $this->routeInfo->mimeTypes : $this->mimeLabels);
        $formats = $this->mimeLabels;
        // we are viewing a html page, so remove this result format
        // unset($formats['text/html']);
        $this->app->view()->set('alternate_formats', $formats);

        //set the current selected mime type
        $this->app->view()->set('current_mime', $this->mimeBest);

        // inject javascript
        $this->app->view()->set(
            'javascript',
            '<script src="'. $this->app->request()->getRootUri() . '/assets/js/web-result.js" type="text/javascript"></script>'
        );
    }

    /**
     * Count array dimensions
     *
     * @param array $array Array
     *
     * @return integer $return Number of dimensions of the array
     */
    protected function countdim(array $array)
    {
        if (is_array(reset($array)))
        {
            $return = $this->countdim(reset($array)) + 1;
        }
        else
        {
            $return = 1;
        }
        return $return;
    }


    /**
     * Check if this is a call for RDF or turtle
     *
     * @return boolean $isRDFRequest
     */
    protected function isRDFRequest()
    {
        if (isset($this->mimeBest) && in_array($this->mimeBest, array('application/rdf+xml', 'text/turtle', 'application/x-turtle')))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

}

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
