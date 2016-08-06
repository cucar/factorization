<?php

require_once 'factorizer.class.php';

// for tracking time 
$time_start = microtime(true);

// $product_decimal = '9';
// $product_decimal = '15';
// $product_decimal = '21';
// $product_decimal = '22';
$product_decimal = '25';
// $product_decimal = '33';
// $product_decimal = '35';
// $product_decimal = '49';
// $product_decimal = '732727';
// $product_decimal = '62615533';

$factorizer = new Factorizer();
$factorizer->factorize($product_decimal);

echo 'Total Execution Time: '.(microtime(true) - $time_start).' seconds' . "\n";