#!/usr/bin/php -q
<?php

/**
 *
 * Profiling for the outputlist function or complete curl calls
 *
 * Used to compare the EasyRDF (rdf+xml, turtle) implementation with a plain/text string operation version
 * Result: EasyRDF will add between one and two ms per request
 *
 */

$url = "http://sameaslite.com/datasets/test/pairs/Frankfurt?_METHOD=GET&string=Frankfurt";

$iterations = 10000;

$list_size = 10;
$list = array();


// build the list array
$chars = "abcdefghijklmnopqrstuvwxyz";
$pos = 0;
for ($i = 0; $i < $list_size; $i++) {
    $list[$i] = '';
    
    for ($j = 0; $j < 6; $j++) {
        if ($pos > 25) {
            $pos = 0;
        }
        $list[$i] .= $chars[$pos];
        $pos++;
    }

}


// chdir('../');
// Composer autoload
// require_once 'vendor/autoload.php';
// $app = \SameAsLite\SameAsLiteFactory::createWebApp('config.ini');


// not interested in seeing the output!
// ob_start();

// $time_taken = array();
// $output = '';


$start = microtime(true);


// run the test
for ($i = 0; $i < $iterations; $i++) {
    // $start = microtime(true);

    // unit test
    // $app->outputList($list);


    // test via http
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/rdf+xml'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    $headers = array(
                 "Cache-Control: no-cache",
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $output = curl_exec($ch);
    curl_close($ch);


    // flush();
    // die;

    // $time_taken[] = microtime(true) - $start;
}

$time_taken = ( microtime(true) - $start ) / $iterations;

// discard output
// ob_clean();

// var_dump($output);

// $time_taken = array_sum($time_taken) / count($time_taken);


print $time_taken . PHP_EOL;
