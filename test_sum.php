<?php

require_once 'sum.class.php';

// [(y3) + (x1x2') + (x1x2) + (x3) + (x1)]
$sum = new Sum();
$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(y,3))))));
$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,1)), new Boolean(new Variable(x,2), true)))));
$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,1)), new Boolean(new Variable(x,2))))));
$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,3))))));
$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,1))))));

//$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,0))))));
//$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,0)), new Boolean(new Variable(y,0))))));
//$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,0), true)))));
//$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x,0), true), new Boolean(new Variable(y,1))))));
//$sum->exprs[] = new BinaryExpression(array(
//    new Term(array(new Boolean(new Variable(x,0)), new Boolean(new Variable(y,0)), new Boolean(new Variable(y,1)) )),
//    new Term(array(new Boolean(new Variable(x,0), true), new Boolean(new Variable(y,1)), new Boolean(new Variable(y,2)) )),
//));
//$sum->exprs[] = new BinaryExpression(array(
//    new Term(array(new Boolean(new Variable(x,0), true), new Boolean(new Variable(y,1)), new Boolean(new Variable(y,2)) )),
//    new Term(array(new Boolean(new Variable(x,0)), new Boolean(new Variable(y,2)), new Boolean(new Variable(y,3)) )),
//));
//$sum->exprs[] = new BinaryExpression(array(new Term(array(new Boolean(new Variable(y,2))))));

echo "Taking mod of: " . $sum->toString() . "\n";
//// echo "Most commonly used variable: " . $sum->most_commonly_used_variable()->toString() . "\n";
// echo "Mod: " . $sum->mod(new Variable(x,0))->toString() . "\n";
echo "Mod: " . $sum->mod()->toString() . "\n";

//echo "Taking div of: " . $sum->toString() . "\n";
// echo "Most commonly used variable: " . $sum->most_commonly_used_variable()->toString() . "\n";
// echo "Div: " . $sum->div(new Variable(x,0))->toString() . "\n";
//echo "Div: " . $sum->div()->toString() . "\n";
