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
        'text/plain' => 'TXT',
        'text/tab-separated-values' => 'TSV',
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

        $mode = (isset($options['mode']) ? $options['mode'] : 'production');

        if ($mode === 'development') {
            // Dev error reporting
            ini_set('display_errors', 1);
            ini_set('html_errors', 0); // disable xdebug var-dump formatting
            ini_set('display_startup_errors', 1);
            error_reporting(-1); // show all errors for development
        }

        // initialise and configure Slim, using Twig template engine
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

        // register 404 and error handlers
        $this->app->notFound(array($this, 'outputError404'));
        $this->app->error(array($this, 'outputException'));
        set_exception_handler(array($this, 'outputException'));

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

        // This takes the options that should be passed to the view directly

        /*
            Thinking about this, so for now not used
            $viewOptions = array_intersect_key($options, array_flip([
            'footerText'
            ]));
        */

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
     * @throws \Exception if there are problems with arguments
     */
    public function addDataset(\SameAsLite\StoreInterface $store, array $options)
    {

        if (!isset($options['shortName'])) {
            throw new \Exception('The Store array is missing required key/value "shortName" in config.ini');
        }

        if (!isset($options['fullName'])) {
            // throw new \Exception('The Store array is missing required key/value "fullName" in config.ini');
            // as promised in config.ini, if fullName is not defined, it is set to shortName

            // $options['fullName'] = ucwords($options['shortName']);
            $options['fullName'] = $options['shortName'];
        }

        if (!isset($options['slug'])) {
            throw new \Exception('The Store array is missing required key/value "slug" in config.ini');
        }

        if (!preg_match('/^[A-Za-z0-9_\-]*$/', $options['slug'])) {
            throw new \Exception(
                'The value for "slug" in config.ini may contain only characters a-z, A-Z, 0-9, hyphen and underscore'
            );
        }

        if (isset($this->stores[$options['slug']])) {
            throw new \Exception(
                'You have already added a store with "slug" value of ' . $options['slug'] . ' in config.ini.'
            );
        }

        // Connect to the DB
        $store->connect();

        $this->stores[$options['slug']] = $store;
        $this->storeOptions[$options['slug']] = $options;
    }

    /**
     * Run application, using configuration defined by previous method
     * @see addStore
     */
    public function run()
    {
        // homepage and generic functions
        $this->registerURL(
            'GET',
            '/',
            'homepage',
            'Application homepage',
            'Renders the main application homepage',
            false,
            'text/html',
            true,
            false //no pagination
        );
        $this->registerURL(
            'GET',
            '/api',
            'api',
            'Overview of the API',
            'Lists all methods available via this API',
            false,
            'application/json,text/html',
            true,
            false //no pagination
        );
        $this->registerURL(
            'GET',
            '/datasets',
            'listStores',
            'Lists available datasets',
            'Returns the available datasets hosted by this service',
            false,
            'application/json,text/html,text/csv',
            false,
            true // pagination
        );
        $this->registerURL(
            'GET',
            '/datasets/:store',
            'storeHomepage',
            'Store homepage',
            'Gives an overview of the specific store',
            false,
            'application/json,text/html',
            true, // hide from API
            false // no pagination
        );
        $this->registerURL(
            'GET',
            '/datasets/:store/api',
            'api',
            'Overview of API for specific store',
            'Gives an API overview of the specific store',
            false,
            'application/json,text/html',
            true, // hide from API
            false // no pagination
        );

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
        $this->registerURL(
            'DELETE',
            '/datasets/:store',
            'deleteStore',
            'Delete an entire store',
            'Removes an entire store, deleting the underlying database',
            true,
            'text/html,text/plain'
        );
        $this->registerURL(
            'DELETE',
            '/datasets/:store/admin/empty',
            'emptyStore',
            'Delete the contents of a store',
            'Removes the entire contents of a store, leaving an empty database',
            true,
            'text/html,text/plain'
        );
        // $this->registerURL(
        // 'GET',
        // '/datasets/:store/admin/backup/',
        // 'backupStore',
        // 'Backup the database contents',
        // 'You can use this method to download a database backup file',
        // true,
        // 'text/html,text/plain'
        // );
        $this->registerURL(
            'PUT',
            '/datasets/:store/admin/restore',
            'restoreStore',
            'Restore database backup',
            'You can use this method to restore a previously downloaded database backup',
            true,
            'text/html,text/plain'
        );

        // Canon work
        $this->registerURL(
            'GET',
            '/datasets/:store/canons',
            'allCanons',
            'Returns a list of all canons',
            null,
            false,
            'text/plain,application/json,text/html',
            false,
            true // pagination
        );
        $this->registerURL(
            'PUT',
            '/datasets/:store/canons/:symbol',
            'setCanon',
            'Set the canon',
            'Invoking this method ensures that the :symbol becomes the canon',
            true,
            'text/html,text/plain'
        );
        $this->registerURL(
            'GET',
            '/datasets/:store/canons/:symbol',
            'getCanon',
            'Get canon',
            'Returns the canon for the given :symbol',
            false,
            'text/html,text/plain',
            false,
            true // pagination
        );

        // Pairs
        $this->registerURL(
            'GET',
            '/datasets/:store/pairs',
            'dumpStore',
            'Export list of pairs',
            'This method dumps *all* pairs from the database',
            false,
            'application/json,text/html,text/csv',
            false,
            true // pagination
        );
        $this->registerURL(
            'PUT',
            '/datasets/:store/pairs',
            'assertPairs',
            'Assert multiple pairs',
            'Upload a file of pairs to be inserted into the store',
            true,
            'text/html,text/plain'
        );
        $this->registerURL(
            'PUT',
            '/datasets/:store/pairs/:symbol1/:symbol2',
            'assertPair',
            'Assert single pair',
            'Asserts sameAs between the given two symbols',
            true,
            'application/json,text/html,text/plain'
        );

        $this->registerURL(
            'GET',
            '/datasets/:store/pairs/:string',
            'search',
            'Search',
            'Find symbols which contain/match the search string/pattern',
            false,
            'application/json,text/html,text/csv',
            false,
            true // pagination
        );

        // Single symbol stuff
        $this->registerURL(
            'GET',
            '/datasets/:store/symbols/:symbol',
            'querySymbol',
            'Retrieve symbol',
            'Return details of the given symbol',
            false,
            'text/html,application/rdf+xml,text/turtle,application/json,text/csv,text/plain',
            false,
            true // pagination
        );
        $this->registerURL(
            'DELETE',
            '/datasets/:store/symbols/:symbol',
            'removeSymbol',
            'Delete symbol',
            'TBC',
            true,
            'text/html,text/plain'
        );

        // Simple status
        $this->registerURL(
            'GET',
            '/datasets/:store/status',
            'statistics',
            'Returns status of the store'
        );

        // New to the service interaction (not in the Seme4 Platform)
        $this->registerURL(
            'GET',
            '/datasets/:store/analysis',
            'analyse',
            'Analyse contents of the store'
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
            '<a href="'.$u.'" class="navbar-brand supplementary">' .
                $this->storeOptions[$this->store]['shortName'] . '</a>'
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
        /*
        } else {
            // disable pagination, since there are either multiple or no stores queried
            $this->pagination = false;
            $this->app->view->set('pagination', false);
        */
        }
    }

    /**
     * Middleware callback used to check the HTTP authentication is OK.
     * Credentials are read from file named auth.htpasswd in the root directory.
     * It is not intended that you call this function yourself.
     * @throws \Exception An exception is thrown if the credentials file cannot be opened
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
                throw new \Exception('Failed to load valid authorization credentials from ' . $filename);
            }
            foreach ($credentials as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, ':') === false) {
                    continue;
                }
                list($u, $p) = explode(':', $line, 2);

                // Check plaintext password against an APR1-MD5 hash
                // if ($u === $_SERVER['PHP_AUTH_USER'] && crypt($_SERVER['PHP_AUTH_PW'], $p) === $p) {
                if (true === \WhiteHat101\Crypt\APR1_MD5::check($_SERVER['PHP_AUTH_PW'], $p)) {
                    $authorized = true;
                    break;
                }
            }
        }

        // missing or invalid credentials
        if (!$authorized) { // && (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']))) {
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
        if (count($args) == 0 || (!$args[0] instanceof \Slim\Route)) {
            throw new \InvalidArgumentException('This method should not be invoked outside of the Slim Framework');
        }
        $route = $args[0];
        $id = $this->app->request()->getMethod() . $route->getPattern();
        $acceptableMime = $this->routeInfo[$id]['mimeTypes'];

        // perform MIME-type matching on the requested and available formats
        // note that if there are no q-values, the *LAST* type "wins"

        // bugfix 1: change conneg class from Negotiate to Negotation
        // $conneg = new \ptlis\ConNeg\Negotiate();
        $conneg = new \ptlis\ConNeg\Negotiation();
        $best = $conneg->mimeBest($_SERVER['HTTP_ACCEPT'], $acceptableMime);

        // bugfix 2: $best is a string, not an object
        // if the quality is zero, no matches between request and available
        // if ($best->getQualityFactor()->getFactor() == 0) {
        if (!$best) { // TODO: need to verify that this is the expected return if there are no matches
            $this->outputError(
                406,
                'Not Acceptable',
                '</p><p>This service cannot return information in the format(s) you requested.',
                'Sorry, we cannot serve you data in your requested format'
            );
        }

        // store best match
        // bugfix 3: $best is a string, not an object
        // $this->mimeBest = $best->getType();
        $this->mimeBest = $best;

        // store alternative MIME types
        foreach ($conneg->mimeAll('*/*', $acceptableMime) as $type) {
            // bugfix 4
            // $type = $type->getAppType()->getType();
            $type = $type->getClientPreference();

            if ($type != $this->mimeBest) {
                $this->mimeAlternatives[] = $type;
            }
        }

        // print "<pre>available: $acceptableMime</pre>\n";
        // print "<pre>requested: {$_SERVER['HTTP_ACCEPT']}</pre>\n";
        // print "<pre>best: {$this->mimeBest}</pre>\n";
        // print "<pre>others: " . join(' , ', $this->mimeAlternatives) . "</pre>\n";

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
                if (is_array($value)) {
                    $op .= "<dt>$key</dt><dd><pre>" . print_r($value, true) . "</pre></dd>\n";
                } else {
                    $op .= "          <dt>$key</dt>\n            <dd>$value</dd>\n";
                }
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
    protected function outputError($status, $title = null, $summary = '', $extendedTitle = '', $extendedDetails = '')
    {
        if (!is_integer($status)) {
            throw new \InvalidArgumentException('The $status parameter must be a valid integer HTTP status code');
        }

        if ($title === null) {
            $title = 'Error ' . $status;
        }

        $summary .= '</p><p>Please try returning to <a href="' .
            $this->app->request()->getURL() .
            $this->app->request()->getRootUri() . '">the homepage</a>.';

        if ($extendedTitle === '') {
            $defaultMsg = \Slim\Http\Response::getMessageForCode($status);
            if ($defaultMsg != null) {
                $extendedTitle = substr($defaultMsg, 4);
            } else {
                $extendedTitle = $title;
            }
        }

        $this->app->contentType('text/html');
        $this->app->response->setStatus($status);

        $this->app->render('error.twig', [
            'titleHTML'    => ' - ' . strip_tags($title),
            'titleHeader'  => 'Error ' . $status,
            'title'        => $extendedTitle,
            'summary'      => $summary,
            'details'      => $extendedDetails
        ]);

        exit; // execution must end here
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
        header('WWW-Authenticate: Basic realm="SameAs Lite"');
        header($_SERVER["SERVER_PROTOCOL"].' 401 Unauthorized');
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

        $this->app->render('homepage-store.twig', $viewData);
    }

    /**
     * Render the about page
     */
    public function aboutPage()
    {

        $this->app->render('page-about.twig', [
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

        $this->app->render('page-contact.twig', [
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

        $this->app->render('page-license.twig', [
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
                $routes[] = $this->getRouteInfoForTemplate($info, $store);
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
        $this->app->render('api-index.twig', [
            'titleHTML' => ' - API',
            'titleHeader' => 'API overview',
            'routes' => $routes
        ]);
    }

    /**
     * Returns the information needed to render a route in api-index.twig
     *
     * @param array       $info  The routeInfo for the route being described
     * @param string|null $store Optional specific store slug
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

        if ($method == 'GET') {
            $formMethod = 'GET';
        } else {
            $formMethod = 'POST';
        }

        // reverse formats
        $formats = join(', ', array_reverse(explode(',', $info['mimeTypes'])));

        // ensure all variables are in {foo} format, rather than :foo
        $endpointURL = $rootURI . $info['urlPath'];
        $endpointURL = preg_replace('@:([^/]*)@', '{\1}', $endpointURL);
        if ($store != null) {
            $endpointURL = str_replace('{store}', $store, $endpointURL);
        }

        // apply HTML formatting to variables
        $endpointHTML = preg_replace('@(\{[^}]*})@', '<span class="api-parameter">\1</span>', $endpointURL);

        preg_match_all('@<span class="api-parameter">{(.*?)}</span>@', $endpointHTML, $inputs);
        $parameters = $inputs[1];
        // echo "<pre>" . print_r($inputs, true) . "</pre>";

        $id = crc32($method . $info['urlPath']);


        // auth required?
        $authString = ($info['authRequired']) ? ' --user username:password' : '';

        if (count($parameters) == 0 && ($method == 'PUT' || $method == 'POST')) {
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
            'parameters'    => $parameters
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
    public function restoreStore($store, $file)
    {
        $this->stores[$store]->restoreStore($file);
        $this->outputSuccess("Store restored from file '$file'");
    }

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
        $this->outputList($results);
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
        $this->app->view()->set('titleHeader', 'Canon for &ldquo;' . $symbol . '&rdquo;');
        $result = [$this->stores[$store]->getCanon($symbol)];
        $this->outputList($result);
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

        // pagination check
        if ($this->stores[$store]->isPaginated()) {
            // add pagination buttons to the template
            $this->app->view()->set('currentPage', $this->stores[$store]->getCurrentPage());
            // var_dump(ceil($this->stores[$store]->getMaxResults() / $this->appOptions['num_per_page']));die;
            $this->app->view()->set('maxPageNum', (int) ceil($this->stores[$store]->getMaxResults() / $this->appOptions['num_per_page']));
        }

        $this->outputTable(
            $result,
            array('canon', 'symbol')
        );
    }

    /**
     * Actions the HTTP PUT service from /pairs
     *
     * Simply passes the request on to the assertPairs sameAsLite Class method.
     * Reports the before and after statistics; failure will have caused an exception
     *
     * @param string $store The URL slug identifying the store
     *
     * @throws \Exception An exception is thrown if the request body is empty
     */
    public function assertPairs($store)
    {
        // TODO - if the input is very large, this will probably blow up available memory?
        // $before = $this->stores[$store]->statistics();

        // if request body is empty, there is nothing to assert
        $body = $this->app->request->getBody();
        if (!$body) { // body no longer exists in request obj
            throw new \Exception('Empty request body. Nothing to assert.'); // TODO : this would also require HTTP status
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
        // bugfix
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
                $this->app->render('snippet-bundle.twig', [
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
        $this->outputSuccess($result);
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
        // $accept = $this->app->request->headers->get('Accept');
        if (isset($this->mimeBest) && $this->mimeBest !== 'text/html') {
            // non-HTML output
            $this->outputList($result, 200, false);
        } else {
            // HTML output

            // add the alternate formats for ajax query and pagination buttons
            $this->prepareWebResultView();

            $res = array();
            foreach ($result as $header => $value) {
                $res[] = $value;
                $headers[] = $header;
            }

            $this->outputTable(array($res), $headers);

        }//end if

    }//end statistics()

    /**
     * Actions the HTTP GET service from /datasets
     *
     * Simply passes the request on to the listStores sameAsLite Class method.
     * Outputs the results in the format requested
     */
    public function listStores()
    {

        $this->app->contentType($this->mimeBest);

        switch ($this->mimeBest) {
            case 'text/turtle':

                $out = [];
                $url = $this->app->request->getUrl();

                foreach ($this->storeOptions as $i) {
                    $out[] = [
                        'rdf:type' => 'Store',
                        'name' => $i['shortName'],
                        'url' => $url . '/datasets/' . $i['slug']
                    ];
                }

                $this->outputArbitrary($out);

                break;

            case 'text/plain':

                $out = fopen('php://output', 'w');

                $url = $this->app->request->getUrl();

                foreach ($this->storeOptions as $i) {
                    $o = $i['shortName'] . ' => ' . $url . '/datasets/' . $i['slug'] . PHP_EOL;
                    fwrite($out, $o);
                }
                fclose($out);

                break;

            case 'text/csv':
            case 'text/tab-separated-values':
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

                exit;

                break;

            case 'application/json':
                $out = [];
                $url = $this->app->request->getUrl();

                foreach ($this->storeOptions as $i) {
                    $out[] = [
                        'name' => $i['shortName'],
                        'url' => $url . '/datasets/' . $i['slug']
                    ];
                }

                echo json_encode($out, JSON_PRETTY_PRINT); // PHP 5.4+

                exit;

                break;

            case 'application/rdf+xml':

                $out = [];
                $url = $this->app->request->getUrl();

                foreach ($this->storeOptions as $i) {
                    $out[] = [
                        'name' => $i['shortName'],
                        'url' => $url . '/datasets/' . $i['slug']
                    ];
                }

                $this->outputArbitrary($out);

                exit;

                break;

            case 'text/html':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                $this->app->render('page-storeList.twig', [
                    'titleHTML' => ' ',
                    'titleHeader' => 'Datasets',
                    'stores' => $this->storeOptions
                ]);

                exit;

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
     */
    public function analyse($store)
    {
        $shortName = $this->storeOptions[$store]['shortName'];
        $this->app->view()->set('titleHTML', ' - Analyse ' . $shortName);
        $this->app->view()->set('titleHeader', 'Analyse ' . $shortName);

        $result = $this->stores[$store]->analyse();

        $this->app->contentType($this->mimeBest);

        switch ($this->mimeBest) {

            case 'text/plain':
                print_r($result, false);

                exit;
                break;

            case 'application/json':
                print json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

                exit;
                break;

            case 'text/html':
                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                // old way:
                // $this->outputHTML('<pre>' . print_r($result, true) . '</pre>');
                $this->outputTable($result); // headers are contained in the multidimensional array

                exit;
                break;

            default:
                throw new \Exception('Could not render analysis as ' . $this->mimeBest);
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

        if (isset($status)) {
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
     * @throws \Exception An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputSuccess($msg)
    {
        $this->app->contentType($this->mimeBest);
        switch ($this->mimeBest) {
            case 'text/plain':
            case 'text/csv':
                print $msg . PHP_EOL;
                exit;
                break;

            case 'application/json':
                print json_encode(array('ok' => $msg)) . PHP_EOL;
                exit;
                break;

            case 'text/html':
                $this->outputHTML('<h2>Success!</h2><p>' . $msg . '</p>') . PHP_EOL;
                exit;
                break;

            default:
                throw new \Exception('Could not render success output as ' . $this->mimeBest);
        }
    }

    /**
     * Output data which is an keyed list of items, in the most appropriate
     * MIME type
     *
     * @param array   $list          The items to output
     * @param integer $status        HTTP status code
     *
     * @throws \Exception An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputArbitrary(array $list = array(), $status = null)
    {

        if (!is_null($status)) {
            $this->app->response->setStatus($status);
        }//end if

        // set the content-type response header
        $this->app->contentType($this->mimeBest);

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

                foreach ($list as $arr) {

                    $store = $graph->resource($domain . $_SERVER['REQUEST_URI']);

                    foreach ($arr as $key => $value) {

                        if (strpos($value, 'http') === 0) {
                            $graph->addResource($store, $key, $graph->resource(urldecode($value)));
                        } else {
                            $graph->addLiteral($store, $key, $value);
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
                print trim($data);

                exit;

                break;


            case 'text/plain':

                    echo print_r($list, true);

                exit;

                break;

            case 'application/json':

                    print json_encode($list, JSON_PRETTY_PRINT); // PHP 5.4+

                exit;

                break;

            case 'text/html':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                $list = array_map([ $this, 'linkify' ], $list); // Map array to linkify the contents

                $this->app->render('page-output.twig', [
                    'list' => '<pre>' . print_r($list, true) . '</pre>'
                ]);
                exit;

                break;

            default:
                // TODO - this requires a response header
                throw new \Exception('Could not render list output as ' . $this->mimeBest);
        }
    }


    /**
     * Output data which is an unordered list of items, in the most appropriate
     * MIME type
     *
     * @param array   $list          The items to output
     * @param integer $status        HTTP status code
     * @param boolean $numeric_array Convert the array into a numerically-keyed array, if true
     *
     * @throws \Exception An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputList(array $list = array(), $status = null, $numeric_array = true)
    {

// bug: list contains each item four times!
// var_dump($list);
// die;

        // single results are converted into array
        // if (!is_array($list)) {
        //     $list = [$list];
        // }//end if

        // Convert into numeric array, if required
        if ($numeric_array) {
            $list = array_values($list);
        }//end if

        // open world assumption (unRESTful)
        // 404 header response
        // if (empty($list)) {
        // $status = 404;
        // }//end if

        if (!is_null($status)) {
            $this->app->response->setStatus($status);
        }//end if

        // set the content-type response header
        $this->app->contentType($this->mimeBest);

        switch ($this->mimeBest) {
            case 'text/plain':
            case 'text/tab-separated-values':
                print join(PHP_EOL, $list);
                exit;

                break;

            case 'text/csv':
                $csv = '';
                // the array keys become the header row
                $csv .= '"' . implode(array_keys($list), '","') . '"' . PHP_EOL;
                // the array values become the content rows
                $vals = array_values($list);
                $csv_vals = '';
                foreach ($vals as $v) {
                    if (is_numeric($v)) {
                        $csv_vals .= strval($v) . ',';
                    } else {
                        $csv_vals .= '"' . strval($v) . '",';
                    }
                }//end foreach
                $csv_vals = rtrim($csv_vals, ',');
                $csv .= $csv_vals . PHP_EOL;
                print $csv;
                exit;

                break;

            case 'application/json':
                print json_encode($list, JSON_PRETTY_PRINT); // PHP 5.4+
                exit;

                break;

            case 'application/rdf+xml':
            case 'application/x-turtle':
            case 'text/turtle':

                // get the parameter
                $symbol = $this->app->request()->params('string');
                if (!$symbol) {
                    $symbol = $this->app->request()->params('symbol');
                }//end if


                // meta info
                $domain = 'http://';
                if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]) {
                    $domain = "https://";
                }//end if
                $domain .= $_SERVER["SERVER_NAME"];
                if ($_SERVER["SERVER_PORT"] != "80") {
                    $domain .= ":" . $_SERVER["SERVER_PORT"];
                }//end if


// EASY RDF version

/*
    // new EasyRdf graph
                $graph = new \EasyRdf_Graph();

                $meta_block = $graph->resource($domain . $_SERVER['REQUEST_URI']);
                // TODO: maybe also add info about store (storename, URI)?
                $meta_block->set('dc:creator', 'sameAsLite');
                $meta_block->set('dc:title', 'Co-references from sameAs.org for ' . $symbol);
                if (isset($this->appOptions['license']['url'])) {
                    $meta_block->add('dct:license', $graph->resource($this->appOptions['license']['url']));
                }

                if (strpos($symbol, 'http') === 0) {
                    $symbol_block = $graph->resource($symbol);
                    $meta_block->add('foaf:primaryTopic', $graph->resource(urldecode($symbol)));
                } else {
                    $symbol_block = $graph->newBNode();
                    $meta_block->add('foaf:primaryTopic', $graph->resource('_:' . $symbol_block->getBNodeId()));
                }

                // list
                foreach ($list as $s) {
                    // TODO: check if it's a URI or literal
                    // if it's a URI, add it as a resource
                    // if it's a text symbol, add it as literal

                    if (strpos($s, 'http') === 0) {
                        // resource
                        $symbol_block->add('owl:sameAs', $graph->resource(urldecode($s)));
                    } else {
                        // literal values - not technically correct, because sameAs expects a resource
                        // but validates in W3C Validator
                        $symbol_block->add('owl:sameAs', $s);
                    }
                }

                $format = 'turtle';

                $data = $graph->serialise($format);
                if (!is_scalar($data)) {
                    $data = var_export($data, true);
                }
                print $data;
*/

// plain text version

                // XML output as text/plain

                $out = '<?xml version="1.0" encoding="utf-8" ?>' . PHP_EOL .
                '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . PHP_EOL .
                '         xmlns:dct="http://purl.org/dc/terms/"' . PHP_EOL .
                '         xmlns:dc="http://purl.org/dc/elements/1.1/"' . PHP_EOL .
                '         xmlns:foaf="http://xmlns.com/foaf/0.1/"' . PHP_EOL .
                '         xmlns:owl="http://www.w3.org/2002/07/owl#">' . PHP_EOL;


                $out .= '  <rdf:Description rdf:about="' . $domain . $_SERVER['REQUEST_URI'] . '">' . PHP_EOL;

                $out .= '      <dc:creator>sameAsLite</dc:creator>' . PHP_EOL;
                $out .= '      <dc:title>Co-references from sameAs.org for ' . $symbol . '</dc:title>' . PHP_EOL;

                if (isset($this->appOptions['license']['url'])) {
                    $out .= '      <dct:license rdf:resource="' . $this->appOptions['license']['url'] . '"></dct:license>' . PHP_EOL;
                }

                if (strpos($symbol, 'http') === 0) {
                    // queried symbol is a URI
                    $symbol = urldecode($symbol);
                    $out .= '      <foaf:primaryTopic rdf:resource="' . $symbol . '" />'  . PHP_EOL .
                            '  </rdf:Description>' . PHP_EOL;
                    $out .= '  <rdf:Description rdf:about="' . $symbol . '">'  . PHP_EOL;
                } else {
                    // queried symbol is a literal
                    $out .= '      <foaf:primaryTopic rdf:resource="_:genid1" />'  . PHP_EOL .
                            '  </rdf:Description>' . PHP_EOL;
                    $out .= '  <rdf:Description rdf:nodeID="genid1">' . PHP_EOL;
                }

                foreach ($list as $s) {
                    if (strpos($s, 'http') === 0) {
                        $out .= '    <owl:sameAs rdf:resource="' . urldecode($s) . '" />' . PHP_EOL;
                    } else {
                        $out .= '    <owl:sameAs>' . $s . '</owl:sameAs>' . PHP_EOL;
                    }
                }

                $out .= '  </rdf:Description>' . PHP_EOL;
                $out .= '</rdf:RDF>' . PHP_EOL;

                print $out;



                exit;

                break;

            case 'text/turtle':
                // TODO ?
                break;

            case 'text/html':
                $list = array_map([ $this, 'linkify' ], $list); // Map array to linkify the contents

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

                $this->app->render('page-list.twig', [
                    'list' => $list
                ]);
                exit;

                break;

            default:
                // TODO - this requires a response header
                throw new \Exception('Could not render list output as ' . $this->mimeBest);
        }
    }

    /**
     * Output tabular data, in the most appropriate MIME type
     *
     * @param array $data    The rows to output
     * @param array $headers Column headers
     * @throws \Exception An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputTable(array $data, array $headers = array())
    {

        // 404 header response
        if (empty($data)) {
            $status = 404;
            $this->app->response->setStatus($status);
        }

        $this->app->contentType($this->mimeBest);

        switch ($this->mimeBest) {
            case 'text/csv':
                $out = fopen('php://output', 'w');

                fputcsv($out, $headers);
                foreach ($data as $i) {
                    fputcsv($out, $i);
                }
                fclose($out);

                exit;

                break;

            case 'text/turtle':
                $this->outputArbitrary(array_merge($headers, $data));
                break;

            case 'text/tab-separated-values':

                $out = fopen('php://output', 'w');
                // fputcsv($out, $headers, "\t");
                foreach ($data as $k => $i) {
                    fputcsv($out, array($headers[$k], $i), "\t");
                }
                fclose($out);

                exit;

                break;

            case 'application/json':
                $op = array();
                foreach ($data as $row) {
                    $op[] = array_combine($headers, $row);
                }
                print json_encode($op, JSON_PRETTY_PRINT); // PHP 5.4+

                exit;

                break;

            // full webpage output
            case 'text/html':

                // add the alternate formats for ajax query and pagination buttons
                $this->prepareWebResultView();

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



                $this->app->render('page-table.twig', array('tables' => $tables));

                exit;

                break;

            default:
                $this->app->contentType('text/html');

                // TODO - this needs response headers that point to available formats
                throw new \Exception('Could not render tabular output as ' . $this->mimeBest);
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
}

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
