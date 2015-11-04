/**
 * sameAs Lite
 * jQuery for Datastore page
 */

$(function() {
	$s = $('#symbols');
	$s.css('cursor', 'pointer');
    $s.on('click', function () {
    	// view the pairs in this store
    	window.location = window.location + '/pairs';
    });

	$s = $('#bundles');
	$s.css('cursor', 'pointer');
    $s.on('click', function () {
    	// view the pairs in this store
    	window.location = window.location + '/canons';
    });
});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
