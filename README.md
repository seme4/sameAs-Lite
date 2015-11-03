# sameAs Lite

sameAs Lite is a refactored, open source version of software to provide sameAs&reg; services such as those that power [sameAs.org](http://sameas.org/).

The sameAs services are motivated by the [Semantic Web](https://en.wikipedia.org/wiki/Semantic_Web) and especially [Linked Data](http://linkeddata.org/), although they have wider uses.
Linked Data is a way of joining and relating information in a machine readable way.
One of the principles of Linked Data is that all things can be referred to by a URI; these things may be things such as a person or book, where we are used to having identifiers, or more abstract concepts such as anger, expertise, last week, sub-Saharan Africa or a Charm Quark. [[1]](http://www.w3.org/DesignIssues/LinkedData.html).
Since anyone can publish Linked Data about anything, without universal agreement on identifiers for everything (which will clearly never happen), different sources may use many different IDs that identify the same thing.
sameAs allows users to search for a Linked Data URI and other URIs that are equivalent will be returned.

It should be noted that this identifer management challenge is actually much wider than just Linked Data, and that the sameAs Lite software makes no assumptions or requirements that the identifiers being managed are Linked Data IDs.
Consequently we will refer to IDs, rather than URIs, throughout the documentation.
Linked Data and Semantic Web users should simply consider IDs to be IDs.



### Concepts

---- TODO: Some SIMPLE text without confusing special terms ----

[Read more about the sameAs concepts](#)...


### Sample website

An installation with some sample data can be found [here](#).


### Quick start

The sameAs Lite WebApp is able to work straight out of the box as long as Apache is installed.

1. Edit `config.ini` and add the information for the stores you are using.

2. The default username and password for the restricted areas is 'demo'. To change this run `htpasswd -c auth.htpasswd username`. To add extra users run `htpasswd auth.htpasswd username`.

3. Create an empty sqlite3 database. Update the location parameter of the teststore in the `config.ini` with the path to this database (default is `db/sameaslite-store.db`).

4. Add pairs to the stores using the web interface. Navigate to `/api` to do this.


### Installation

Detailed installation instructions are available [here](#).


### Screenshots

![Store homepage](/path/to/img.jpg)

![Api overview](/path/to/img.jpg)

![Retrieving equivalent symbols](/path/to/img.jpg)


### License

[MIT License](./LICENSE)