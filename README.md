# SameAs Lite

SameAs Lite is a refactored, open source, version of the software that powers [SameAs.org](http://sameas.org).

Linked data is a way of joining and relating information in a machine readable way, on of the priciples of linked data is that all things are represented by a URI, whether it be a person, book, place, department, etc [[1]](http://www.w3.org/DesignIssues/LinkedData.html).
However this means that many URIs can represent the same thing. SameAs allows users to search for a linked data URI and other URIs that are equivilent will be returned.

SameAs Lite aims to provide a libary that makes it easy to link and search for URIs that represent the thing.



## Concepts
Equivalent URIs are conceptually stored in a bundle. A bundle is a set of URIs referring to resources which are considered to be equivalent, in a given context. A URI can exist in at most one bundle within a linked data set exposed by a sameAs Lite instance.

One URI in each bundle is nominated to be a canonical identifier, or canon, for that bundle. The canon represents a preferred URI for the set of duplicates.

An application that wishes to use data from multiple sources as if they were a single resource can process results by looking up URIs within sameAs Lite and replacing these with their canons on the fly. This reduces the multiplicity of identifiers to a single definitive URI.


## Deploying SameAs Lite
SameAs Lite comes with two methods of Storage: SQLite and MySQL. SameAs Lite can be easily extended to use different storage methods as well (see Extending).
These examples will use MySQL as a storage method.

### Create the Database
The SQL stores only use 1 table (per Store) to store the URIs. This means that SameAs Lite can be used in an existing Database/Schema, or in a new one all together.

It is recommended you give SameAs Lite it's own user and grant it CREATE and DROP permissions on the Schema so that it can create and delete the store(s) as required. 
SameAs Lite will not interfere with any other tables or the Schema itself.

### Download and install dependencies
SameAs Lite uses [Composer](https://getcomposer.org) for dependency management.

---- Instructions here once composer package is made ----

### Creating Stores
All Stores bundled with SameAs Lite are in the *\SameAsLite\Store* namespace and all take a _name_ for the store and an array of _options_.
For MySQL these options are:
```php
[
  'username' => <string>,
  'password' => <string>,
  'dbName' => <string, default=SameAsLite>,
  'host' => <string default=localhost>,
  'port' => <int, optional>,
  'charset' => <string, optional>
]
```

For other Store's options please see the documentation.

#### Using Stores
`$store->assertPair('crisps', 'potato chips');` Create a relationship

`$store->querySymbol('crisps');` Looks up all equivilent symbols. Returns `['crisps', 'potato chips']`

`$store->getCanon('potato chips');` Gets the canon for the symbol.
Canons are created when a pair is asserted and neither of the symbols are in the store, then the first symbol is used as the canon.
Returns `'crisps'`

`$store->removeSymbol('potato chips');` Removes that symbol from the store.



### Using the WebApp
These stores can be used by themselves and accessed directly. However SameAs Lite also comes with a WebApp that can be used to provide a RESTful API and HTML interface to interact with the store.
The WebApp uses [Slim](http://slimframework.com) for routing.

To use the WebApp create a new *\SameAsLite\WebApp* object, passing in an array of options.
```php
"Options described here"
```

Stores can then be added to the app using the `addDataset` function:
```php
$app->addDataset($store);
```

Finally, run the app using `run()`.
```php
$app->run();
```

A full example can be found in index.php

## Extending
It is possible to create new Stores to work with SameAs Lite.
The Store class must impliment *\SameAsLite\StoreInterface*, the functions that must be implimented are described in the docs.

For creating SQL based stores, an abstract class *\SameAsLite\Store\SQLStore* is provided, this impliments most functionality common to SQL databases. Some functions (such as `__construct`, `init` and `deleteStore`) need to be implimented.


