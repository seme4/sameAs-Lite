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
    );

    /** @var array $routeInfo Details of the available URL routes */
    protected $routeInfo = array();

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

        // register 404 and error handlers
        $this->app->notFound(array($this, 'outputError404'));
        $this->app->error(array($this, 'outputException'));
        set_exception_handler(array($this, 'outputException'));

        // apply options
        foreach ($options as $k => $v) {
            $this->app->view->set($k, $v);
        }
    }

    /**
     * Add a dataset to this web application
     *
     * @param \SameAsLite\Store $store   The underlying Store containing the dataset
     * @param array             $options Array of configration options describing the dataset
     *
     * @throws \Exception if there are problems with arguments
     */
    public function addDataset(\SameAsLite\Store $store, array $options)
    {

        if (!isset($options['shortName'])) {
            throw new \Exception('The $options array is missing required key/value "name"');
        }

        if (!isset($options['shortName'])) {
            throw new \Exception('The $options array is missing required key/value "name"');
        }

        if (!isset($options['slug'])) {
            throw new \Exception('The $options array is missing required key/value "slug"');
        }

        if (!preg_match('/^[A-Za-z0-9_\-]*$/', $options['slug'])) {
            throw new \Exception(
                'Value for $options["slug"] may contain only characters a-z, A-Z, 0-9, hyphen and undersore'
            );
        }

        if (isset($this->stores[$options['slug']])) {
            throw new \Exception(
                'You have already added a store with $options["slug"] value of ' . $options['slug']
            );
        }

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
            true
        );
        $this->registerURL(
            'GET',
            '/api',
            'api',
            'Overview of the API',
            'Lists all methods available via this API',
            false,
            'application/json,text/html',
            true
        );
        $this->registerURL(
            'GET',
            '/datasets',
            'listStores',
            'Lists available datasets',
            'Returns the available datasets hosted by this service'
        );
        $this->registerURL(
            'GET',
            '/datasets/:store',
            'storeHomepage',
            'Store homepage',
            'Gives an overview of the specific store',
            false,
            'application/json,text/html',
            true
        );
        $this->registerURL(
            'GET',
            '/datasets/:store/api',
            'api',
            'Overview of API for specific store',
            'Gives an API overview of the specific store',
            false,
            'application/json,text/html',
            true
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
            'text/plain,application/json,text/html'
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
            'text/html,text/plain'
        );

        // Pairs
        $this->registerURL(
            'GET',
            '/datasets/:store/pairs',
            'dumpStore',
            'Export list of pairs',
            'This method dumps *all* pairs from the database',
            false,
            'application/json,text/html,text/csv'
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

        // Search
        $this->registerURL(
            'GET',
            '/datasets/:store/search/:string',
            'search',
            'Search',
            'Find symols which contain/match the search string/pattern'
        );

        // Single symbol stuff
        $this->registerURL(
            'GET',
            '/datasets/:store/symbols/:symbol',
            'querySymbol',
            'Retrieve symbol',
            'Return details of the given symbol'
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
            '/datasets/:store/analyse',
            'analyse',
            'Analyse contents of the store'
        );

        // add datasets to template
        $this->app->view()->set('datasets', $this->storeOptions);

        // run
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
        $hidden = false
    ) {

        // ensure the URL path has a leading slash
        if (substr($urlPath, 0, 1) != '/') {
            $urlPath  = '/' . $urlPath;
        }

        // ensure there are no trailing slashes
        if (strlen($urlPath) > 1 && substr($urlPath, -1) == '/') {
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
        if ($mimeTypes != null) {
            $callbacks[] = array($this, 'callbackCheckFormats');
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
        if ($details == null) {
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
            'hidden'
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
        if (count($args) == 0 || (!$args[0] instanceof \Slim\Route)) {
            throw new \InvalidArgumentException('This method should not be invoked outside of the Slim Framework');
        }
        $store = $args[0]->getParam('store');

        // if the store is not valid, skip the current route
        if (!isset($this->stores[$store])) {
            $this->app->pass();
        }

        // display name of store in titlebar
        $u = $this->app->request()->getRootUri() . '/datasets/' . $store;
        $this->app->view()->set(
            'titleSupplementary',
            '<a href="'.$u.'" class="navbar-brand supplementary">' . $this->storeOptions[$store]['shortName'] . '</a>'
        );
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
            $filename = dirname($_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF']) . '/auth.htpasswd';
            $credentials = @file($filename);
            if ($credentials === false || count($credentials) == 0) {
                throw new \Exception('Failed to load valid authorization credentails from ' . $filename);
            }
            foreach ($credentials as $line) {
                $line = trim($line);
                if ($line == '' || strpos($line, ':') === false) {
                    continue;
                }
                list($u, $p) = explode(':', $line, 2);
                if ($u == $_SERVER['PHP_AUTH_USER'] && crypt($_SERVER['PHP_AUTH_PW'], $p) == $p) {
                    $authorized = true;
                    break;
                }
            }
        }

        // missing or invalid credentials
        if (!$authorized && (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']))) {
            header('WWW-Authenticate: Basic realm="SameAs Lite"');
            header('HTTP\ 1.0 401 Unauthorized');
            $this->outputError(
                401,
                'Access Denied',
                'You have failed to supply valid credentials, access to this resource is denied.',
                'Access Denied'
            );
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
        $conneg = new \ptlis\ConNeg\Negotiate();
        $best = $conneg->mimeBest($_SERVER['HTTP_ACCEPT'], $acceptableMime);

        // if the quality is zero, no matches between request and available
        if ($best->getQualityFactor()->getFactor() == 0) {
            $this->outputError(
                406,
                'Not Acceptable',
                '</p><p>This service cannot return information in the format(s) you requested.',
                'Sorry, we cannot serve you data in your requested format'
            );
        }

        // store best match
        $this->mimeBest = $best->getType();

        // store alternative MIME types
        foreach ($conneg->mimeAll('*/*', $acceptableMime) as $type) {
            $type = $type->getAppType()->getType();
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

        if ($title == null) {
            $title = 'Error ' . $status;
        }

        $summary .= '</p><p>Please try returning to <a href="' .
            $this->app->request()->getURL() .
            $this->app->request()->getRootUri() . '">the homepage</a>.';

        if ($extendedTitle == '') {
            $defaultMsg = \Slim\Http\Response::getMessageForCode($status);
            if ($defaultMsg != null) {
                $extendedTitle = substr($defaultMsg, 4);
            } else {
                $extendedTitle = $title;
            }
        }

        $this->app->contentType('text/html');
        $this->app->view()->set('titleHTML', ' - ' . strip_tags($title));
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
     * Application homepage
     */
    public function homepage()
    {
        $this->app->view()->set('titleHTML', ' ');
        $this->app->view()->set('titleHeader', 'Welcome');
        $this->outputHTML('This is the homepage...</p><p>TODO: list the available stores!');
    }

    /**
     * Store homepage
     *
     * @param string $store The URL slug identifying the store
     */
    public function storeHomepage($store)
    {
        $this->app->view()->set('titleHTML', $this->storeOptions[$store]['shortName']);
        $this->app->view()->set('titleHeader', $this->storeOptions[$store]['fullName']);
        $this->outputHTML(
            'This is the homepage for an individual dataset...</p>' .
            '<p>TODO: list statistics? description? license? search box?</p>'
        );
    }

    /**
     * A simple page which gives basic advice on how to use the services
     * @param string|null $store Optional URL slug identifying a store. If present, page is
     * rendered for that specific store, or if null then a generic rendering is produced.
     */
    public function api($store = null)
    {

        $this->app->view()->set('titleHTML', ' - API');
        $this->app->view()->set('titleHeader', 'API overview');

        $output = '<p>TODO Preamble to go here?</p>';

        // iterate over all routes
        foreach ($this->routeInfo as $info) {
            if ($info['hidden']) {
                continue;
            }
            $output .= $this->renderRoute($info, $store);
        }

        $this->outputHTML($output);
    }

    /**
     * Describes a URL route
     *
     * @param array       $info  The routeInfo for the route being described
     * @param string|null $store Optional specific store slug
     * @return string The HTML blob desbribing this route
     */
    protected function renderRoute(array $info, $store = null)
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

        // reverse formats
        $formats = join(', ', array_reverse(explode(',', $info['mimeTypes'])));

        // ensure all variables are in {foo} format, rather than :foo
        $endpointURL = $rootURI . $info['urlPath'];
        $endpointURL = preg_replace('@:([^/]*)@', '{\1}', $endpointURL);
        if ($store != null) {
            $endpointURL = str_replace('{store}', $store, $endpointURL);
        }

        // apply HTML formatting to variables
        $endpointHtml = preg_replace('@(\{[^}]*})@', '<span>\1</span>', $endpointURL);
        $numVariables = substr_count($endpointHtml, '<span>');
        $id = crc32($method . $info['urlPath']);

        // auth required?
        $lock = ($info['authRequired']) ? ' <span class="glyphicon glyphicon-lock"></span>' : '';
        $authString = ($info['authRequired']) ? ' --user username:password' : '';

        // example command line invocation
        $cmdLine = "curl -X $method $authString $host$endpointHtml";

        // sort the "try now" facility
        $tryNow = '';
        if ($method == 'GET' && $numVariables == 0) {
            // GET request without any variables - straight link (ie not submission via the form)
            $tryNow = '<p class=\"form-control-static\">';
            $tryNow .= '<a href="' . $endpointURL . '" class="btn btn-'.$map[$method].'">' . $method . '</a>';
            $tryNow .= '</p>';
        } else {
            // we need a form, either to allow input of variables or to spoof HTTP request type
            preg_match_all('@<span>{(.*?)}</span>@', $endpointHtml, $inputs);
            $tryNow .= '<input type="hidden" name="_METHOD" value="' . $method . '"/>';
            foreach ($inputs[1] as $i) {
                $tryNow .= '<input class="form-control" type="text" name="' . $i . '" placeholder="' . $i . '" />';
            }

            if ($numVariables == 0 && ($method == 'PUT' || $method == 'POST')) {
                $tryNow .= '<textarea class="form-control" name="body" placeholder="Request body..."></textarea>';
                $cmdLine = "curl --upload-file data.tsv $authString $host$endpointHtml";
            }

            $tryNow .= '<input class="form-control btn btn-'.$map[$method].'" type="submit" value="'.$method.'">';
        }

        $this->app->view()->set(
            'javascript',
            '<script src="'. $this->app->request()->getRootUri() . '/assets/js/api.js"></script>'
        );

        return "
<div class=\"panel panel-default panel-api\">
  <div class=\"panel-heading\" data-toggle=\"collapse\" data-target=\"#panel-$id\" \">
    <div class=\"method\"><label class=\"label label-{$map[$method]}\">$method</label>$lock</div>
    <b>$endpointHtml</b><span class=\"pull-right\">{$info['summary']}</span>
  </div>
  <div id=\"panel-$id\" class=\"panel-collapse collapse\">
    <div class=\"panel-body\">
      <form class=\"api form-horizontal\" role=\"form\" method=\""
        . ($method == 'GET' ? 'GET' : 'POST')
        . "\" data-url=\"$endpointURL\">
        <div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Description</label>
          <div class=\"col-sm-10\">
            <p class=\"form-control-static\">{$info['details']}</p>
          </div>
        </div>

        <div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Available formats</label>
          <div class=\"col-sm-10\">
            <p class=\"form-control-static\">$formats</p>
          </div>
        </div>

        <div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Example invocation</label>
          <div class=\"col-sm-10\">
            <p class=\"form-control-static\"><tt>$cmdLine</tt></p>
          </div>
        </div>

        <div class=\"form-group\">
          <label class=\"col-sm-2 control-label\">Try it now</label>
          <div class=\"col-sm-10\">
            $tryNow
          </div>
        </div>

      </form>
    </div>
  </div>
</div>";
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
        $result = $this->stores[$store]->allCanons();
        $this->outputList($result);
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
        $result = $this->stores[$store]->getCanon($symbol);
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
        $result = $this->stores[$store]->dumpStore();
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
     */
    public function assertPairs($store)
    {
        // TODO - if the input is very large, this will probably blow up available memory?
        // $before = $this->stores[$store]->statistics();
        $this->stores[$store]->assertPairs(explode("\n", $this->app->request->getBody()));
        // $after = $this->stores[$store]->statistics();
        // TODO array_merge(array('Before:'), $before, array('After:'), $after));
        $this->outputSuccess('Pairs asserted');
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
        $result = $this->stores[$store]->search($string);
        $this->outputList($result);
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
        $result = $this->stores[$store]->querySymbol($symbol);
        $this->outputHTML($result);
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
        $this->stores[$store]->removeSymbol($symbol);
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
        $result = $this->stores[$store]->statistics();
        $this->outputHTML($result);
    }

    /**
     * Actions the HTTP GET service from /list
     *
     * Simply passes the request on to the listStores sameAsLite Class method.
     * Outputs the results in the format requested
     */
    public function listStores()
    {
        // TODO
        $this->outputHTML('TODO');
    }

    /**
     * Actions the HTTP GET service from /analyse
     *
     * Simply passes the request on to the analyse sameAsLite Class method.
     * Outputs the results in the format requested
     *
     * @param string $store The URL slug identifying the store
     */
    public function analyse($store)
    {
        $result = $this->stores[$store]->analyse();
        $this->outputHTML($result);
    }

    /**
     * Output an HTML page
     *
     * @param mixed $body The information to be displayed
     */
    protected function outputHTML($body)
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

        // fix api pages such that if viewing a particular store
        // then the store name is automatically injected for you
        $params = $this->app->router()->getCurrentRoute()->getParams();
        if (isset($params['store'])) {
            $this->app->view()->set('apiPath', "datasets/{$params['store']}/api");
        } else {
            $this->app->view()->set('apiPath', 'api');
        }


        // fold arrays into PRE blocks
        if (is_array($body)) {
            $body = '<pre>' . join("\n", $body) . "</pre>\n";
        }

        $this->app->view()->set('body', $body);
        print $this->app->view()->fetch('page.twig');
        die;
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
        switch($this->mimeBest) {
            case 'text/plain':
                print $msg;
                break;

            case 'application/json':
                print json_encode(array('ok' => $msg));
                break;

            case 'text/html':
                $this->outputHTML('<h2>Success!</h2><p>' . $msg . '</p>');
                break;

            default:
                throw new \Exception('Could not render success output as ' . $this->mimeBest);
        }
    }

    /**
     * Output data which is an unordered list of items, in the most appropriate
     * MIME type
     *
     * @param array $list The items to output
     * @throws \Exception An exception may be thrown if the requested MIME type
     * is not supported
     */
    protected function outputList(array $list = array())
    {
        $this->app->contentType($this->mimeBest);
        switch($this->mimeBest) {

            case 'text/plain':
                print join("\n", $list);
                break;

            case 'application/json':
                print json_encode($list);
                break;

            case 'text/html':
                if (count($list) == 0) {
                    $op = "<p>No items found</p>\n";
                } else {
                    $op = "<ul>\n";
                    foreach ($list as $item) {
                        $op .= '        <li>' . $this->linkify($item) . "</li>\n";
                    }
                    $op .= "      </ul>\n";
                }
                $this->outputHTML($op);
                break;

            default:
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
    protected function outputTable(array $data, array $headers)
    {

        switch($this->mimeBest) {
            case 'text/csv':
                // TODO - why is there no native array to csv function?!
                // TODO - this is not safe (no escaping)
                print join(',', $headers) . "\n";
                foreach ($data as $row) {
                    print join(',', $row) . "\n";
                }
                break;

            case 'text/tab-separated-values':
                print join("\t", $headers) . "\n";
                foreach ($data as $row) {
                    print join("\t", $row) . "\n";
                }
                break;

            case 'application/json':
                $op = array();
                foreach ($data as $row) {
                    $op[] = array_combine($headers, $row);
                }
                print json_encode($op);
                break;

            case 'text/html':
                $op  = '<table class="table">';
                $op .= '  <thead>';
                $op .= '    <tr>';
                foreach ($headers as $h) {
                    $op .= '      <th>' . $h . '</th>';
                }
                $op .= '    </tr>';
                $op .= '  </thead>';
                $op .= '  <tbody>';
                foreach ($data as $row) {
                    $op .= '    <tr>';
                    foreach ($row as $value) {
                        $op .= '      <td>' . $this->linkify($value) . '</td>';
                    }
                    $op .= '    </tr>';
                }
                $op .= '  </tbody>';
                $op .= '</table>';
                $this->outputHTML($op);
                break;

            default:
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
        if (substr($item, 0, 4) == 'http') {
            return '<a href="' . $item . '">' . $item . '</a>';
        }
        return $item;
    }
}

// vim: set filetype=php expandtab tabstop=4 shiftwidth=4:
