<?php

require_once 'sum.class.php';

// xA + x'B + C
$expr = new BinaryExpression();
//$expr->terms[] = new Term(array(new Boolean(new Variable(x,0)) ));
//$expr->terms[] = new Term(array(new Boolean(new Variable(x,0), true) ));
$expr->terms[] = new Term(array(new Boolean(new Variable(x,0)), new Boolean(new Variable(y,0)) ));
$expr->terms[] = new Term(array(new Boolean(new Variable(x,0), true), new Boolean(new Variable(y,1)) ));
$expr->terms[] = new Term(array(new Boolean(new Variable(y,2))));
echo "Converting: " . $expr->toString() . "\n";
echo "Converted: " . $expr->convert_to_sum(new Variable(x,0))->toString() . "\n";
