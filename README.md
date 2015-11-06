# sameAs Lite [![Build Status](https://travis-ci.org/joetm/sameAs-Lite.svg?branch=master)](https://travis-ci.org/joetm/sameAs-Lite)

### Synopsis

sameAs Lite is an open source software to provide sameAs&reg; services.

### Motivation

The sameAs services are motivated by the [Semantic Web](https://en.wikipedia.org/wiki/Semantic_Web) and especially [Linked Data](http://linkeddata.org/), although they have wider uses.

Linked Data is a way of joining and relating information in a machine readable way.
One of the principles of Linked Data is that all things can be referred to by a URI; these things may be things such as a person or book, where we are used to having identifiers, or more abstract concepts such as anger, expertise, last week, sub-Saharan Africa or a Charm Quark.
Since anyone can publish Linked Data about anything, without universal agreement on identifiers for everything (which will clearly never happen), different sources may use many different IDs that identify the same thing [[1]](http://www.w3.org/DesignIssues/LinkedData.html).
sameAs allows users to search for a Linked Data URI and other URIs that are equivalent will be returned.

It should be noted that the sameAs Lite software makes no assumptions or requirements that the identifiers being managed are Linked Data URIs.

[Read more about the sameAs concepts and its history](http://sameas.org/about.php)...


### Sample website

A website with some sample data can be found [here](http://sameas.org/).


### Quick start

The sameAs Lite WebApp is able to work straight out of the box as long as Apache is installed.

1. Edit `config.ini` and add the information for the stores you are using.

2. The default username and password for the restricted areas is 'demo'. To change this run `htpasswd -c -m auth.htpasswd username`. To add extra users run `htpasswd -m auth.htpasswd username`.

3. Create an empty sqlite3 database. Update the location parameter of the teststore in the `config.ini` with the path to this database (the default is `db/sameaslite-store.db`).

4. Add pairs to the stores using the web interface. Navigate to `/api` to do this.


### Installation

Please refer to the [Github Wiki](wiki) for detailed installation instructions.


### Screenshots

![Store homepage](/path/to/img.jpg)

![Api overview](/path/to/img.jpg)

![Retrieving equivalent symbols](/path/to/img.jpg)

### Contributors

Read more on how to [contribute to SameAs-Lite](https://github.com/joetm/sameAs-Lite/wiki/Contributing)...

### Support

Support info here

### License and Legal Information

SameAs-Lite is released under the [MIT License](./LICENSE).

[![Seme4 Ltd](http://www.seme4.com/wp-content/uploads/2015/02/Seme4-Logo.png)](http://www.seme4.com/)

SameAs&reg; is a registered trademark of Seme4 Ltd.
