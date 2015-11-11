C=/usr/bin/composer.phar

.PHONY: docs tests

default:
	##
	##  Makefile for SameAs Lite
	##
	##   make install	install minimum requirements to run
	##   make install-dev	install tools for development
	##   make checks	code style and formatting checks
	##   make docs		build docs
	##   make clean-docs	remove docs
	##   make clean-dist	remove everything and revert back to distribution state
	##   make tarball	create the tar.gz for distribution
	##

install:
	# install libraries required to run
	$C update --no-dev

install-dev:
	# install libraries to run tests, generate documentation, etc
	$C update --dev

checks:
	# checking code style
	-vendor/bin/phpcbf --ignore=vendor/*,dev-tools/*,components/* --extensions=js,php --standard=dev-tools/CodeStandard ./
	-vendor/bin/phpcs  --ignore=vendor/*,dev-tools/*,components/* --extensions=js,php --standard=dev-tools/CodeStandard --colors --report-width=160 ./

tests:
	# run tests
	vendor/bin/phpunit --bootstrap vendor/autoload.php tests/phpUnit/

docs:
	# produce class documentation
	php vendor/bin/phpdoc -c ./dev-tools/phpdoc-config.xml
	#see docs/index.html &

clean-docs:
	# remove class documentation
	rm -rf docs/ dev-tools/phpdoc-tmp/ /tmp/phpdoc-twig-cache

clean-dist: clean-docs
	# remove everything we installed
	rm -rf vendor composer.lock

tarball: clean-dist
	# create tar.gz
	tar zcvf sameAsLite-dev.tar.gz composer.json index.php Makefile dev-tools src tests .htaccess
