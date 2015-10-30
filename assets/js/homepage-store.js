/**
 * sameAs Lite
 * jQuery for Datastore page
 */

$(function() {
	$s = $('#symbols');
	$s.css('cursor', 'pointer');
    $s.on('click', function () {
    	// view the pairs in this store
    	window.location = 'http://sameaslite.com/datasets/test/pairs';
    });
});

// vim: set filetype=javascript expandtab tabstop=4 shiftwidth=4:
