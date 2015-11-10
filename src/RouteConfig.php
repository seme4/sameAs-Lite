<?php
/**
 * SameAs Lite
 *
 * Route configuration
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
class RouteConfig extends \SameAsLite\WebApp
{
    protected $app; // slim app object

    private $routes = [
        // homepage and generic functions
        // access: webapp only
        [
            'GET',
            '/',
            'homepage',
            'Application homepage',
            'Renders the main application homepage',
            false,
            'text/html',
            true, // hide from API
            false //no pagination
        ],
        // access: webapp only
        [
            'GET',
            '/api',
            'api',
            'Overview of the API',
            'Lists all methods available via this API',
            false,
            'text/html',
            true, // hide from API
            false //no pagination
        ],
        // access: API + webapp
        [
            'GET',
            '/datasets',
            'listStores',
            'Lists available datasets',
            'Returns the available datasets hosted by this service',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // enable pagination
        ],
        // access: webapp only
        [
            'GET',
            '/datasets/:store',
            'storeHomepage',
            'Store homepage',
            'Gives an overview of the specific store',
            false,
            'text/html',
            false,
            false // no pagination
        ],
        // access: webapp only
        [
            'GET',
            '/datasets/:store/api',
            'api',
            'Overview of API for specific store',
            'Gives an API overview of the specific store',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        ],

        // access: webapp only
        [
            'GET',
            '/about',
            'aboutPage',
            'About sameAsLite',
            'Renders the about page',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        ],
        // access: webapp only
        [
            'GET',
            '/contact',
            'contactPage',
            'Contact page',
            'Renders the contact page',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        ],
        // access: webapp only
        [
            'GET',
            '/license',
            'licensePage',
            'License page',
            'Renders the SameAsLite license',
            false,
            'text/html',
            true, // hide from API
            false // no pagination
        ],

        // dataset admin actions

        // TODO: this would also need to update the config.ini
        // access: API + webapp
        [
            'DELETE',
            '/datasets/:store',
            'deleteStore',
            'Delete an entire store',
            'Removes an entire store, deleting the underlying database',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        ],

        // Update the store contents
        // Use a PUT request with empty body to remove the contents of the store
        // access: API + webapp
        [
            'PUT',
            '/datasets/:store',
            'updateStore',
            'Update or delete the contents of a store',
            'Updates the store with request body or, if the request body is empty, removes the entire contents of a store, leaving an empty database',
            true,
            'text/plain,application/json,text/html',
            false,
            false //no pagination
        ],

        // access: webapp only
        // $app->registerURL(
        // 'GET',
        // '/datasets/:store/admin/backup/',
        // 'backupStore',
        // 'Backup the database contents',
        // 'You can use this method to download a database backup file',
        // true,
        // 'text/html,text/plain'
        // ],

        // access: API + webapp
        // $app->registerURL(
        //    'PUT',
        //    '/datasets/:store/admin/restore',
        //    'restoreStore',
        //    'Restore database backup',
        //    'You can use this method to restore a previously downloaded database backup',
        //    true,
        //    'text/html,text/plain'
        // ],

        // Canon work
        // access: API + webapp
        [
            'GET',
            '/datasets/:store/canons',
            'allCanons',
            'Returns a list of all canons',
            null,
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        ],
        // access: API + webapp
        [
            'GET',
            '/datasets/:store/canons/:symbol',
            'getCanon',
            'Get canon',
            'Returns the canon for the given :symbol',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            false // no pagination
        ],
        // access: API + webapp
        [
            'PUT',
            '/datasets/:store/canons/:symbol',
            'setCanon',
            'Set the canon', // TODO: Update text
            'Invoking this method ensures that the :symbol becomes the canon', // TODO: Update text
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        ],

        // Pairs
        // access: API + webapp
        [
            'GET',
            '/datasets/:store/pairs',
            'dumpStore',
            'Export list of pairs',
            'This method dumps *all* pairs from the database',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        ],
        // access: API + webapp
        [
            'PUT',
            '/datasets/:store/pairs',
            'assertPairs',
            'Assert multiple pairs',
            'Upload a file of pairs to be inserted into the store',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        ],

        // access: API + webapp
        [
            'PUT',
            '/datasets/:store/pairs/:symbol1/:symbol2',
            'assertPair',
            'Assert single pair',
            'Asserts sameAs between the given two symbols',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        ],

        // access: API + webapp
        [
            'GET',
            '/datasets/:store/pairs/:string',
            'search',
            'Search',
            'Find symbols which contain/match the search string/pattern',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        ],

        // Single symbol stuff
        // access: API + webapp
        [
            'GET',
            '/datasets/:store/symbols/:symbol',
            'querySymbol',
            'Retrieve symbol',
            'Return details of the given symbol',
            false,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            true // pagination
        ],
        // access: API + webapp
        [
            'DELETE',
            '/datasets/:store/symbols/:symbol',
            'removeSymbol',
            'Delete symbol',
            'Delete a symbol from the datastore',
            true,
            'text/plain,application/json,text/html',
            false,
            false // no pagination
        ],

        // Simple status of datastore
        // access: API + webapp
        [
            'GET',
            '/datasets/:store/status',
            'statistics',
            'Statistics',
            'Returns status of the store',
            true,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            false // no pagination
        ],

        // Analyse contents of store
        // access: API + webapp
        [
            'GET',
            '/datasets/:store/analysis',
            'analyse',
            'Analyse store',
            'Analyse contents of the store',
            true,
            'text/html,application/json,text/csv,text/tab-separated-values,text/plain, application/rdf+xml,text/turtle,application/x-turtle',
            false,
            false // no pagination
        ]
    ];

    public function __construct(& $app) {
        $this->app = &$app;
    }

    public function setup() {

        $keys = [
            'httpMethod',
            'urlPath',
            'funcName',
            'summary',
            'details',
            'authRequired',
            'mimeTypes',
            'hidden',
            'paginate'
        ];

        // set up the routes
        foreach ($this->routes as $values) {
            $route = array_combine($keys, $values);
            $this->app->registerURL($route);
        }

        unset($this->routes); //free the memory

    }

}
