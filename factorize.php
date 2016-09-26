<?php

require_once 'factorizer.class.php';

// for tracking time 
$time_start = microtime(true);

// $product_decimal = '9';
// $product_decimal = '15';
// $product_decimal = '21';
// $product_decimal = '25';
// $product_decimal = '33';
// $product_decimal = '35';
// $product_decimal = '49';
// $product_decimal = '51';
// $product_decimal = '55';
// $product_decimal = '77'; // takes about 5 minutes
$product_decimal = '732727'; // too long
// $product_decimal = '62615533'; // too long

$factorizer = new Factorizer();
$factorizer->factorize($product_decimal);

echo 'Total Execution Time: '.(microtime(true) - $time_start).' seconds' . "\n";