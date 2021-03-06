<?php

require_once 'sum.class.php';
require_once 'equation.class.php';
require_once 'deducer.class.php';

/*
 * factorization problem solver
*/
class Factorizer {

    // raw binary sum equations
    protected $sums = array();

    // carry overs calculated for the product
    protected $carryovers = array();

    // deducer helper object
    protected $deducer;

    /*
     * constructor - initialize deducer
    */
    public function __construct() {
        $this->deducer = new Deducer();
    }

    /*
     * main routine to factorize
     */
    public function factorize($product_decimal) {

        // convert product to binary string
        $product_binary = $this->decimal_to_binary($product_decimal);

        // length of x and y numbers - assume maximum (same as product)
        $numlen = strlen($product_binary);

        // adjust product length and digit order based on numbers - pad with zeroes
        $productlen = $numlen * 2;
        $products = strrev($product_binary) . str_repeat('0', $numlen);

        // x0 and y0 has to be one for the algorithm to work
        if ($products[0] != 1) throw new Exception('Factorization algorithm only works for odd numbers.');

        // setup initial deduction to avoid trivial solution x = 1 - x1'x2'...xn' = 0
        $trivial_x = new Term();
        for ($i = 1; $i < $numlen; $i++) $trivial_x->add(new Boolean(new Variable(x, $i), true));
        echo "Trivial solution eliminating deduction for x: " . $trivial_x->toString() . " = 0\n";
        $this->deducer->add(new Equation($trivial_x, 0));

        // setup initial deduction to avoid trivial solution y = 1 - y1'y2'...yn' = 0 - deduced from the product - transformed to x since at this point they are interchangeable
        $trivial_y = new Term();
        for ($i = 1; $i < $numlen; $i++) $trivial_y->add(new Boolean(new Variable(x, $i), $products[$i] == '0'));
        echo "Trivial solution eliminating deduction for y: " . $trivial_y->toString() . " = 0\n";
        $this->deducer->add(new Equation($trivial_y, 0));

        // determine sums
        for ($s = 0; $s < $productlen - 1; $s++) {
            $this->sums[$s] = new Sum();
            for ($x = 0; $x < $numlen; $x++) {
                for ($y = 0; $y < $numlen; $y++) {
                    if ($s == $x + $y) {
                        // when it's multiplication by x0 or y0, we can ignore them - they are both 1
                        $vars = array();
                        if ($x != 0) $vars[] = new Boolean(new Variable(x, $x));
                        if ($y != 0) $vars[] = new Boolean(new Variable(y, $y));
                        $this->sums[$s]->add(new BinaryExpression(array(new Term($vars))));
                    }
                }
            }
        }
        // debug: for ($i = 0; $i < $productlen - 1; $i++) echo $sums[$i]->toString() . " + carryover mod 2 = {$products[$i]}\n";

        // deduce at least one fact from each product
        for ($i = 1; $i < $numlen; $i++) {

            echo 'Working on product digit ' . $i . " (" . $products[$i] . ")\n";

            // calculate the product expression
            $product_sum = Sum::merge($this->sums[$i], $this->carryOver($i));
            echo 'Product sum ' . $i . ": {$products[$i]} = " . $product_sum->toString() . " mod 2\n";

            // apply deductions before mod/div
            $product_sum->apply_deductions($this->deducer->deductions);
            echo 'Product equation ' . $i . ": {$products[$i]} = " . $product_sum->toString() . " mod 2\n";

            // remove duplicate expressions when applicable
            $product_sum->remove_duplicate_expressions();
            echo 'Product sum ' . $i . " (without duplicates): {$products[$i]} = " . $product_sum->toString() . " mod 2\n";

            // deduce from the product expression
            $this->deduce($products[$i], $product_sum);
        }

        // now calculate the zero products based on x
        for ($i = $numlen; $i < $productlen; $i++) {

            echo 'Working on product digit ' . $i . " (" . $products[$i] . ")\n";

            // calculate the product expression - the last one does not contain product sum
            if ($i != $productlen - 1) $product_sum = Sum::merge($this->sums[$i], $this->carryOver($i));
            else $product_sum = $this->carryOver($i);
            echo 'Product sum ' . $i . ": {$products[$i]} = " . $product_sum->toString() . " mod 2\n";

            // apply deductions before mod/div
            $product_sum->apply_deductions($this->deducer->deductions);
            echo 'Product equation ' . $i . ": {$products[$i]} = " . $product_sum->toString() . " mod 2\n";

            // remove duplicate expressions when applicable
            $product_sum->remove_duplicate_expressions();
            echo 'Product sum ' . $i . " (without duplicates): {$products[$i]} = " . $product_sum->toString() . " mod 2\n";

            // deduce from the product expression
            $this->deduce($products[$i], $product_sum);
        }

        // print deductions
        echo "Deductions: " . $this->deducer->toString() . "\n";
        echo "\n\n Stage 4\n\n";

        // add the balance eliminating equations (x >= y - e.g. x'y = 0)
        for ($i = 0; $i < $numlen - 1; $i++) {

            // calculate xn'yn
            $balance_expr = new BinaryExpression(array(new Term(array(new Boolean(new Variable(x, $numlen - 1 - $i), true), new Boolean(new Variable(y, $numlen - 1 - $i))))));
            echo "Balance expression $i before deductions: " . $balance_expr->toString() . "\n";

            // apply deductions
            $balance_expr->apply_deductions($this->deducer->deductions);
            echo "Balance expression $i after deductions: " . $balance_expr->toString() . "\n";

            // add the equal conditions of previous digits
            if ($i > 0) $balance_expr = $balance_expr->and_expr($equal_expr);
            echo "Balance expression $i after deductions and previous equal digits: " . $balance_expr->toString() . "\n";

            // do the deductions from new equation
            $this->deduce(0, new Sum(array($balance_expr)));

            // calculate equal condition of the digits (xnyn V xn'yn')
            $digit_equal_expr = new BinaryExpression(array(
                new Term(array(new Boolean(new Variable(x, $numlen - 1 - $i)), new Boolean(new Variable(y, $numlen - 1 - $i)))),
                new Term(array(new Boolean(new Variable(x, $numlen - 1 - $i), true), new Boolean(new Variable(y, $numlen - 1 - $i), true)))
            ));

            // apply deductins for the digit equal condition
            $digit_equal_expr->apply_deductions($this->deducer->deductions);

            // multiply with previous digit conditions as needed
            if ($i == 0) $equal_expr = $digit_equal_expr; else $equal_expr = $equal_expr->and_expr($digit_equal_expr);
        }

        // print deductions
        echo "Deductions: " . $this->deducer->toString() . "\n";

        // check if the deductions give a complete solution - if so, return it
        $solution = $this->deducer->get_solution();

        // if there is no solution, start to do random assignments to find the solution
        if (!$solution) throw new Exception('No solution found: ' . $this->deducer->toString());

        // convert solution to decimal
        $solution[0] = $this->binary_to_decimal($solution[0] . '1');
        $solution[1] = $this->binary_to_decimal($solution[1] . '1');

        // output the solution :)
        echo "Solution: x = " . $solution[0] . ' y = ' . $solution[1] . "\n";
    }

    /*
     * convert decimal to binary number - using PHP routine for now - we need to change it for bigger numbers
     */
    protected function decimal_to_binary($decimal) {
        return (string) decbin(intval($decimal));
    }

    /*
     * convert binary to decimal number - using PHP routine for now - we need to change it for bigger numbers
     */
    protected function binary_to_decimal($binary) {
        return (string) bindec($binary);
    }

    /*
     * do random assignments to find the best solution among 4 possible (2 trivial, one x>y and one x<y) - pick x<y
     */
    protected function find_solution($deductions) {

        // begin with the top x variable assumption
        $reduction_var = $this->determine_reduction_variable($deductions);

        // try to find solution in the zero branch of that variable
        $branch_solution = $this->find_branch_solution($deductions, $reduction_var, 0);
        if ($branch_solution) return $branch_solution;
        // debug:
        echo $reduction_var->toString() . " = 0 reduction did not work - trying 1\n";

        // zero branch did not work - try to find solution in the one branch of that variable
        return $this->find_branch_solution($deductions, $reduction_var, 1);
    }

    /*
     * determines the reduction variable to choose in a set of deductions
     */
    protected function determine_reduction_variable($deductions) {

        // get the variables that are used in all deductions
        $vars = $this->get_deductions_variables($deductions);

        // if there are no deduction variables, something went wrong - error out
        if (count($vars) == 0) throw new Exception('No deduction variables found.');

        // start with y0 - if we can find x variables, they replace y - if we can find higher order digits, they replace lower order digits
        $reduction_var = new Variable(y, 0);
        foreach ($vars as $var) {

            // if the current variable is y and this is an x, replace it
            if ($reduction_var->type == y && $var->type == x) { $reduction_var = $var; continue; }

            // if the current variable has a lower order, replace it
            if ($reduction_var->digit < $var->digit) { $reduction_var = $var; continue; }
        }

        // return the best reduction variable
        echo "Determined reduction variable as: " . $reduction_var->toString() . "\n";
        return $reduction_var;
    }

    /*
     * returns the variables used in a set of deductions
     */
    protected function get_deductions_variables($deductions) {

        // variables to return
        $deductions_vars = array();

        // loop through the deductions and extract each variable used in it
        foreach ($deductions as $deduction) {

            // if this is a zero product, get the variables on the left side - otherwise get them from right side
            if (is_a($deduction[0], 'Term')) {
                $deduction_vars = $deduction[0]->vars();
                // debug: echo "Got variables from Term: "; print_r($deduction_vars);
            }
            else {

                // if this is an expression, get the variables in it
                if (is_a($deduction[1], 'BinaryExpression')) $deduction_vars = $deduction[1]->vars();
                // if this is a boolean, get its variable
                elseif (is_a($deduction[1], 'Boolean')) $deduction_vars = array($deduction[1]->var);
                // if this is a variable, get itself
                elseif (is_a($deduction[1], 'Variable')) $deduction_vars = array($deduction[1]);
                // otherwise it must be a constant - no variables
                else $deduction_vars = array();
            }

            // now we have all the variables used in the deduction - add them to the global array if they are not already in it
            foreach ($deduction_vars as $deduction_var) {
                $var_exists = false;
                foreach ($deductions_vars as $deductions_var) if ($deductions_var->toString() == $deduction_var->toString()) { $var_exists = true; break; }
                if (!$var_exists) $deductions_vars[] = $deduction_var;
            }
        }

        // return all the variables used in the deductions
        echo "All the variables used in the deductions: " . implode(', ', array_map(function($var) { return $var->toString(); }, $deductions_vars)) . "\n";
        return $deductions_vars;
    }

    /*
     * finds solutions for an assumed value of a variable
     */
    protected function find_branch_solution($deductions, $reduction_var, $reduction_val) {

        echo 'Finding branch solution for ' . $reduction_var->toString() . ' = ' . $reduction_val . "\n";

        // make a copy of given assumptions
        $branch_deductions = $this->clone_deductions($deductions);
        // debug: $this->print_deductions($branch_deductions); exit;

        // add new deduction that assumes value for the reduction variable
        $this->merge_deduction($reduction_var, $reduction_val, $branch_deductions);
        // debug: $this->print_deductions($branch_deductions); exit;

        // check if the deductions give a complete solution - if so, return it
        $branch_solution = $this->get_branch_solution($branch_deductions);
        // debug: print_r($branch_solution);

        // if there is no solution (not a complete branch), continue on to the next variable recursively
        if (!$branch_solution) return $this->find_solution($branch_deductions);

        // if there was a solution, check if it is the one we want - we do not want trivial solutions
        if ($this->binary_one($branch_solution[0])) { echo "Trivial solution\n"; return false; }
        if ($this->binary_one($branch_solution[1])) { echo "Trivial solution\n"; return false; }

        // ignore solutions where x < y
        $comparison_status = $this->compare_binary($branch_solution[0], $branch_solution[1]);
        if ($comparison_status > 0) { echo "X < Y solution\n"; return false; }

        // this seems to be the solution we want - return it
        // debug: print_r($branch_solution);
        return $branch_solution;
    }

    /*
     * compares two binary numbers and returns which one is greater
     */
    protected function compare_binary($num1, $num2) {

        // if the numbers are not of the same length, something's wrong
        if (strlen($num1) != strlen($num2)) throw new Exception('Comparing binary numbers of different length: ' . $num1 . ' vs ' . $num2);

        // loop through the numbers - the first one to have a one is the bigger one
        for ($i = 0; $i < strlen($num1); $i++) {

            // if they have the same number, check further
            if ($num1[$i] == $num2[$i]) continue;

            // first number is bigger - return -1
            if ($num1[$i] == '1') return -1;

            // second number is bigger - return 1
            if ($num2[$i] == '1') return 1;
        }

        // numbers are equal - acceptable solution as well
        return 0;
    }

    /*
     * returns if the binary string equals one or not
     */
    protected function binary_one($numstr) {

        // the last digit has to be one - removed after adding x0=1 and y0=1 implicitly
        // if (substr($numstr, -1) != '1') return false;

        // all digits have to be all zero (except for the implicit one)
        if (str_repeat('0', strlen($numstr)) != $numstr) return false;

        // it's one
        return true;
    }

    /*
     * make a copy of a set of deductions
     */
    protected function clone_deductions($deductions) {

        // new set of deductions to be returned
        $deductions_copy = array();

        // loop through deductions and copy each one
        foreach ($deductions as $deduction) $deductions_copy[] = $this->copy_deduction($deduction);

        // return the copy of deductions
        return $deductions_copy;
    }

    /*
     * makes a copy of a deduction
     */
    protected function copy_deduction($deduction) {

        // if this is a zero product deduction, all we need to do is copy the term
        if (is_a($deduction[0], 'Term')) return array($deduction[0]->copy(), 0);

        // if this is a var = expr/bool/var type if deduction, copy them using their routines
        if (is_a($deduction[0], 'Variable') && is_a($deduction[1], 'BinaryExpression')) return array($deduction[0]->copy(), $deduction[1]->copy());
        if (is_a($deduction[0], 'Variable') && is_a($deduction[1], 'Boolean')) return array($deduction[0]->copy(), $deduction[1]->copy());
        if (is_a($deduction[0], 'Variable') && is_a($deduction[1], 'Variable')) return array($deduction[0]->copy(), $deduction[1]->copy());

        // variable equals = constant type deduction
        if (is_a($deduction[0], 'Variable') && !is_object($deduction[1])) return array($deduction[0]->copy(), $deduction[1]);

        // unknown deduction type
        throw new Exception('Unknown deduction type: ' . print_r($deductio, true));
    }

    /*
     * calculated all deductions
    */
    protected function print_deductions($deductions) {
        echo "Deductions: \n";
        for ($i = 0; $i < count($deductions); $i++) {
            echo $deductions[$i][0]->toString() . " = " . (method_exists($deductions[$i][1], 'toString') ? $deductions[$i][1]->toString() : $deductions[$i][1]) . "\n";
        }
    }

    /*
     * deductions from an equation - only works when we have only two variables in the equation
    */
    protected function deduce($val, $product_sum) {

        return $this->deduce_general($val, $product_sum);

        /* deprecated?
        // get the number of variables in the expression
        $vars = $expr->vars();
        $varcount = count($vars);

        // check the number of variables that appear in the expression - if it's more than 2, apply general deduction
        if ($varcount > 2) return $this->deduce_general($val, $expr, $vars);

        // two variable deduction
        if ($varcount == 2) {

            // calculate the product equation - the last one is different
            $product_expression = $product_sum->mod();
            echo 'Product equation ' . $i . ": {$products[$i]} = " . $product_expression->toString() . "\n";

            return $this->deduce2($val, $product_sum->mod(), $vars);
        }

        // if there is only one variable, it's easy
        if ($varcount == 1) return $this->deduce1($val, $expr, $vars[0]);

        // if there are no variables in the expression, check if it matches the value - error out otherwise
        if ($expr->evaluate() != $val) throw new Exception('Conflict in deductions: ' . $val . ' = ' . $expr->toString());
        */
    }

    /*
     * deductions from an equation with more than 2 variables
    */
    protected function deduce_general($val, Sum $product_sum) {

        echo "Deducing from " . $product_sum->toString() . " = " . $val . "\n";

        // determine the deduction expression
        $deduce_expr = $product_sum->determine_deduction_expr();
        echo "Determined deduction expression index as: " . $deduce_expr . "\n";

        // if we could find a deduction expression, use it - we like deductions of the form var = expr better
        if ($deduce_expr != -1) {

            // get the variable from the sum expression
            $deduce_var = $product_sum->exprs[$deduce_expr]->terms[0]->vars[0]->copy();
            echo "Determined deduction variable as: " . $deduce_var->toString() . "\n";

            // get the sum without the deduction variable
            $deduce_sum = $product_sum->remove_expr($deduce_expr);
            echo "Determined deduction sum as: " . $deduce_sum->toString() . "\n";

            // take the mod of the sum
            $deduce_mod = $deduce_sum->mod();
            echo "Determined deduction mod as: " . $deduce_mod->toString() . "\n";

            // if the variable is negated and value is zero or variable is not negated and value is one, take the negation
            if (($deduce_var->negated && $val == 0) || (!$deduce_var->negated && $val == 1)) {
                $deduce_mod = $deduce_mod->negate();
                echo "Negated deduction mod: " . $deduce_mod->toString() . "\n";
            }

            // now we just equate the deduction mod to the variable as-is
            $deduce_var = $deduce_var->var;
            echo "Deduction variable: " . $deduce_var->toString() . "\n";

            // simplify if the expression is a value
            if (count($deduce_mod->terms) == 0) $deduce_mod = 0;
            if (count($deduce_mod->terms) == 1 && count($deduce_mod->terms[0]->vars) == 0) $deduce_mod = $deduce_mod->terms[0]->val;

            // deduce the equation
            $this->deduction($deduce_var, $deduce_mod);
        }
        // could not find a nice deduction of the form var = expr - use zero product deductions
        else {

            // get the mod of the sum
            $expr = $product_sum->mod();

            // if the equations are not the same, we have to deduce combined - first, convert to zero if needed
            if ($val == '1') {
                $expr = $expr->negate();
                $expr->simplify()->unify()->merge_terms();
                echo "Negated expression to find zero products: 0 = " . $expr->toString() . "\n";
            }

            // each product in the expression must equal to zero
            echo "Deducing zero products: " . $expr->toString() . " = 0\n";
            for ($i = 0; $i < count($expr->terms); $i++) $this->deduction($expr->terms[$i], 0);
        }
    }

    /*
     * saves a simple deduction in the main array
    */
    protected function deduction($var, $val) {

        // add the new deduction to the set of deductions we have
        $this->deducer->add(new Equation($var, $val));
    }

    /*
     * calculates the carry over - carryOver(n) = sum(n-1) + carry_over(n-1) div 2
    */
    public function carryOver($i) {

        // carry over starts at the first digit
        if ($i <= 1) {
            $this->carryovers[$i] = new Sum();
            return $this->carryovers[$i];
        }

        // if the carry over was not calculated before, do it now
        if (!isset($this->carryovers[$i])) {
            echo 'Calculating carry over ' . $i . "\n";
            $this->carryovers[$i] = Sum::merge($this->sums[$i-1], $this->carryovers[$i-1])->div();
        }

        // do a simplification based on new deductions
        echo 'Carry over ' . $i . ' (before deductions): ' . $this->carryovers[$i]->toString() . "\n";
        $this->carryovers[$i]->apply_deductions($this->deducer->deductions);
        $this->carryovers[$i]->simplify()->unify()->merge_terms();
        echo 'Carry over ' . $i . ' (after deductions): ' . $this->carryovers[$i]->toString() . "\n";
        return $this->carryovers[$i];
    }

    /*
     * deductions from an equation with 1 variable
    *
    protected function deduce1($val, $expr, $var) {

        // apply variable combinations and get the results
        $match0 = ($expr->apply($var, 0)->evaluate() == $val);
        $match1 = ($expr->apply($var, 1)->evaluate() == $val);
        // debug: echo 'Match 0: ' . intval($match0) . "\n";
        // debug: echo 'Match 1: ' . intval($match1) . "\n";

        // if there are no matches, it's a conflict - error out
        if (!$match0 && !$match1) throw new Exception('No matches in deduction 1: ' . $val . ' = ' . $expr->toString());

        // if they all match, it's totality? - error out
        if (!$match0 && !$match1) throw new Exception('Totality in deduction 1: ' . $val . ' = ' . $expr->toString());

        // if there is only one match, it's great!
        if ( $match0 && !$match1) { $this->deduction($var, 0); return; }
        if (!$match0 &&  $match1) { $this->deduction($var, 1); return; }

        // should not be reaching this point unless we forgot something
        throw new Exception('Deductions1 unreachable point');
    }
    */

    /*
     * deductions from an equation with more than 2 variables
    *
    protected function deduce_general_alt1($val, BinaryExpression $expr, $vars) {

        // if the value is one, negate the expression to be able to apply the formula
        if ($val == 1) { $expr = $expr->negate(); $val = 0; }

        // pick y variables over x - if there are none, just pick the highest one
        $deduce_var = null;
        foreach ($vars as $var) if ($var->type == y) $deduce_var = $var;
        if ($deduce_var === null) { $deduce_var = $vars[0]; foreach ($vars as $var) if ($var->digit > $deduce_var->digit) $deduce_var = $var; }
        // debug: echo "deduction variable: " . $deduce_var->toString() . "\n";

        // standardize expression to be like f(x) = xa + x'b + c
        $x = new Boolean($deduce_var);
        $x_negated = $x->negate();

        // functions we will get as a result of the variable split => f(x) = xa + x'b + c
        $expr_a = new BinaryExpression();
        $expr_b = new BinaryExpression();
        $expr_c = new BinaryExpression();

        // loop through the terms and split based on variable
        foreach ($expr->terms as $term) {
            // debug: echo "checking term: " . $term->toString() . "\n";
            if ($term->has_boolean($x)) $expr_a->terms[] = $term->remove_variable($x);
            elseif ($term->has_boolean($x_negated)) $expr_b->terms[] = $term->remove_variable($x_negated);
            else $expr_c->terms[] = $term->copy();
        }

        // simplify as needed
        $expr_a->simplify()->unify()->merge_terms();
        $expr_b->simplify()->unify()->merge_terms();
        $expr_c->simplify()->unify()->merge_terms();

        // debug:
        // echo "expr a: " . $expr_a->toString() . "\n";
        // echo "expr b: " . $expr_b->toString() . "\n";
        // echo "expr c: " . $expr_c->toString() . "\n";

        // if there are no terms in expression a, it means variable x did not occur at all
        if (count($expr_a->terms) == 0) $expr_a_type = 'zero';
        // if there is only a single term with no variables in it, that means the variable x occurred by itself
        elseif (count($expr_a->terms) == 1 && count($expr_a->terms[0]->vars) == 0) $expr_a_type = 'one';
        // otherwise x occurred with some variables along with x
        else $expr_a_type = 'expr';

        // if there are no terms in expression b, it means variable x' did not occur at all
        if (count($expr_b->terms) == 0) $expr_b_type = 'zero';
        // if there is only a single term with no variables in it, that means x' occurred by itself - in that case, b = 1 and b' = 0
        elseif (count($expr_b->terms) == 1 && count($expr_b->terms[0]->vars) == 0) $expr_b_type = 'one';
        // otherwise x' occurred with some variables along with x'
        else $expr_b_type = 'expr';

        // if there are no terms in expression c, it means all variables ocurred with x or x'
        if (count($expr_c->terms) == 0) $expr_c_type = 'zero';
        // otherwise there were some terms that did not have x or x'
        else $expr_c_type = 'expr';

        // if there are any terms that appear without x or x', they must all equal to zero
        if ($expr_c_type == 'expr') {
            echo "Deducing zero products from expr c: 0 = " . $expr_c->toString() . "\n";
            for ($i = 0; $i < count($expr_c->terms); $i++) $this->deduction($expr_c->terms[$i], 0);
        }

        // now pull x in terms of others - xa + x'b = 0
        // a = 0, b = 0 => nothing to do 0 = 0
        if ($expr_a_type == 'zero' && $expr_b_type == 'zero') {
            // do nothing
        }
        // a = 1 => x = 0
        if ($expr_a_type == 'one') {
            $this->deduction($deduce_var, 0);
        }
        // b = 1 => x = 1
        elseif ($expr_b_type == 'one') {
            $this->deduction($deduce_var, 1);
        }
        // a = 0 and b = expr => x'b = 0
        elseif ($expr_a_type == 'zero' && $expr_b_type == 'expr') {
            $expr_b = $expr_b->and_expr(new BinaryExpression(array(new Term(array($x_negated)))));
            echo "Deducing zero products from expr b: 0 = " . $expr_b->toString() . "\n";
            for ($i = 0; $i < count($expr_b->terms); $i++) $this->deduction($expr_b->terms[$i], 0);
        }
        // a = expr and b = 0 => xa = 0
        elseif ($expr_a_type == 'expr' && $expr_b_type == 'zero') {
            $expr_a = $expr_a->and_expr(new BinaryExpression(array(new Term(array($x)))));
            echo "Deducing zero products from expr a: 0 = " . $expr_a->toString() . "\n";
            for ($i = 0; $i < count($expr_a->terms); $i++) $this->deduction($expr_a->terms[$i], 0);
        }
        // a = expr and b = expr => check if they are the negation of each other
        else {

            // if b = a', x = b
            $expr_a_negated = $expr_a->negate();
            if (!$expr_a_negated->equals($expr_b)) throw new Exception("encountered undeductable equation. a = " . $expr_a->toString() . " b = " . $expr_b->toString());
            $this->deduction($deduce_var, $expr_b);
        }
    }
    */

    /*
     * deductions from an equation with 2 variables
    *
    protected function deduce2($val, $expr, $vars) {

        // pick y variables over x
        if ($vars[0]->type == x && $vars[1]->type == y) $vars = array($vars[1], $vars[0]);

        // apply each variable combination and get the results
        $match00 = ($expr->apply($vars[0], 0)->apply($vars[1], 0)->evaluate() == $val);
        $match01 = ($expr->apply($vars[0], 0)->apply($vars[1], 1)->evaluate() == $val);
        $match10 = ($expr->apply($vars[0], 1)->apply($vars[1], 0)->evaluate() == $val);
        $match11 = ($expr->apply($vars[0], 1)->apply($vars[1], 1)->evaluate() == $val);
        // debug: echo 'Match 00: ' . intval($match00) . "\n";
        // debug: echo 'Match 01: ' . intval($match01) . "\n";
        // debug: echo 'Match 10: ' . intval($match10) . "\n";
        // debug: echo 'Match 11: ' . intval($match11) . "\n";

        // if there are no matches, it's a conflict - error out
        if (!$match00 && !$match01 && !$match10 && !$match11) throw new Exception('No matches in deduction 2: ' . $val . ' = ' . $expr->toString());

        // if there is only one match, it's great!
        if ( $match00 && !$match01 && !$match10 && !$match11) { $this->deduction($vars[0], 0); $this->deduction($vars[1], 0); return; }
        if (!$match00 &&  $match01 && !$match10 && !$match11) { $this->deduction($vars[0], 0); $this->deduction($vars[1], 1); return; }
        if (!$match00 && !$match01 &&  $match10 && !$match11) { $this->deduction($vars[0], 1); $this->deduction($vars[1], 0); return; }
        if (!$match00 && !$match01 && !$match10 &&  $match11) { $this->deduction($vars[0], 1); $this->deduction($vars[1], 1); return; }

        // if there are 2 matches, we can still deduce something
        if ( $match00 &&  $match01 && !$match10 && !$match11) { $this->deduction($vars[0], 0); return; }
        if ( $match00 && !$match01 &&  $match10 && !$match11) { $this->deduction($vars[1], 0); return; }
        if ( $match00 && !$match01 && !$match10 &&  $match11) { $this->deduction($vars[0], new Boolean($vars[1])); return; }
        if (!$match00 &&  $match01 &&  $match10 && !$match11) { $this->deduction($vars[0], (new Boolean($vars[1]))->negate()); return; }
        if (!$match00 &&  $match01 && !$match10 &&  $match11) { $this->deduction($vars[1], 1); return; }
        if (!$match00 && !$match01 &&  $match10 &&  $match11) { $this->deduction($vars[0], 1); return; }

        // if there are 3 matches, we are pushing it
        if (!$match00 &&  $match01 &&  $match10 &&  $match11) { $this->deduction(new Term(array((new Boolean($vars[0]))->negate(), (new Boolean($vars[1]))->negate())), 0); return; } // x'y'=0
        if ( $match00 && !$match01 &&  $match10 &&  $match11) { $this->deduction(new Term(array((new Boolean($vars[0]))->negate(), (new Boolean($vars[1]))          )), 0); return; } // x'y = 0
        if ( $match00 &&  $match01 && !$match10 &&  $match11) { $this->deduction(new Term(array((new Boolean($vars[0]))          , (new Boolean($vars[1]))->negate())), 0); return; } // xy' = 0
        if ( $match00 &&  $match01 &&  $match10 && !$match11) { $this->deduction(new Term(array((new Boolean($vars[0]))          , (new Boolean($vars[1]))          )), 0); return; } // xy = 0

        // everything matches? never seen that one before - error out
        if ( $match00 &&  $match01 &&  $match10 &&  $match11) throw new Exception('Complete matches in deduction 2: ' . $val . ' = ' . $expr->toString());

        // should not be reaching this point unless we forgot something
        throw new Exception('Deductions unreachable point');
    }
    */

    /*
     * compares 2 deduction sets and returns if they are the same or not
     *
    protected function deductions_equal($deductions1, $deductions2) {

        // debug:
        echo "Comparing deduction sets\n";
        // debug: echo "Comparing deduction sets - deductions set 1\n"; $this->print_deductions($deductions1);
        // debug: echo "Comparing deduction sets - deductions set 2\n"; $this->print_deductions($deductions2);

        // if the number of deductions differ, they are different
        if (count($deductions1) != count($deductions2)) {
            // debug:
            echo "Deductions are different - counts\n";
            return false;
        }

        // loop through the deductions and check if they appear in the other set
        foreach ($deductions1 as $deduction1) {

            // debug: echo "Searching for deduction " . $this->print_deduction($deduction1) . " in the second set\n";

            // check if the deduction appears in the other set
            $deduction_exists = false;
            foreach ($deductions2 as $deduction2) {

                // debug: echo "Comparing to deduction " . $this->print_deduction($deduction2) . "\n";
                if ($this->deduction_equal($deduction1, $deduction2)) {
                    // debug: echo "Deductions equal - found\n";
                    $deduction_exists = true;
                    break;
                }
            }

            // if the deduction does not exist, they cannot be the same
            if (!$deduction_exists) {
                echo "Deduction " . $this->print_deduction($deduction1) . " does not appear in the second set - different\n";
                return false;
            }
        }

        // all deductions exist and their count is the same - sets are identical
        return true;
    }
    */

    /*
     * compares 2 deductions and returns if they are the same or not
     *
    protected function deduction_equal($deduction1, $deduction2) {

        // check both sides of the deductions to see if they are equal or not
        if (!$deduction1[0]->equals($deduction2[0])) {
            // debug: echo "Deductions different (left hand side): " . $this->print_deduction($deduction1) . ' vs ' . $this->print_deduction($deduction2) . "\n";
            return false;
        }

        // if one side is object and the other is not, they are not equal
        if ((is_object($deduction1[1]) && !is_object($deduction2[1])) || (!is_object($deduction1[1]) && is_object($deduction2[1]))) {
            // debug: echo "Deductions different (right hand side type): " . $this->print_deduction($deduction1) . ' vs ' . $this->print_deduction($deduction2) . "\n";
            return false;
        }

        // compare the right side as values
        if (!is_object($deduction1[1]) && !is_object($deduction2[1])) {
            if ($deduction1[1] == $deduction2[1]) return true;
            // debug: echo "Deductions different (right hand side value): " . $this->print_deduction($deduction1) . ' vs ' . $this->print_deduction($deduction2) . "\n";
            return false;
        }

        // compare right side as objects
        if ($deduction1[1]->equals($deduction2[1])) return true;
        // debug: echo "Deductions different (right hand side object): " . $this->print_deduction($deduction1) . ' vs ' . $this->print_deduction($deduction2) . "\n";
        return false;
    }
    */

}