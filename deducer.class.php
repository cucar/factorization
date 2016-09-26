<?php

require_once 'sum.class.php';
require_once 'equation.class.php';

/*
 * deducer class
*/
class Deducer {

    // deductions from equations
    public $deductions;

    // removed deductions
    public $removed_deductions;

    /*
     * constructor - initialize deductions
    */
    public function __construct() {
        $this->deductions = array();
        $this->removed_deductions = array();
    }

    /*
     * returns the deductions as a string to be printed
     */
    public function toString() {
        $str = "";
        foreach ($this->deductions as $deduction) $str .= $deduction->toString() . "\n";
        // if ($this->removed_deductions) { $str .= "Removed Deductions: \n"; foreach ($this->removed_deductions as $deduction) $str .= $deduction->toString() . "\n"; }
        return $str;
    }

    /*
     * add a new deduction to the deductions and do self reductions
     */
    public function add(Equation $deduction) {

        // add the new equation to the set of deductions if it was not already added before
        if (!$this->add_if_not_deduced($deduction)) return false;

        // now do self deduction until there is no more to deduce
        $this->self_deductions();

        // remove unnecessary equations
        $this->prune_totalities();

        echo "Deductions after merge: " . $this->toString();
    }

    /*
     * add a new deduction to the deduction set if it was not already there
     */
    public function add_if_not_deduced(Equation $new_deduction) {

        // go through the deductions and check if we already have this deduction already - return false if we already have it
        foreach ($this->deductions as $old_deduction) if ($new_deduction->equals($old_deduction)) return false;

        // go through the deductions that were added before and removed later and check if we already removed this deduction - return false if we already have it
        foreach ($this->removed_deductions as $old_deduction) if ($new_deduction->equals($old_deduction)) return false;

        echo "Adding new equation " . $new_deduction->toString() . " to deductions: \n" . $this->toString();

        // add the new equation to the set of deductions
        $this->deductions[] = $new_deduction;

        // return true to indicate that we added a new deduction
        return true;
    }

    /*
     * returns if a deduction is a duplicate or not
     */
    protected function duplicate_deduction($deduction_index) {
        for ($i = 0; $i < count($this->deductions); $i++) if ($i != $deduction_index && $this->deductions[$deduction_index]->equals($this->deductions[$i])) return true;
        return false;
    }

    /*
     * removes a deduction from the deduction set
     */
    protected function remove_deduction($deduction_index) {
        echo "Removing deduction: " . $this->deductions[$deduction_index]->toString() . "\n";
        $this->removed_deductions[] = $this->deductions[$deduction_index];
        unset($this->deductions[$deduction_index]);
        $this->deductions = array_values($this->deductions);
    }

    /*
     * do self deduction - apply each deduction to the other deduction until deductions cannot get reduced anymore
     */
    protected function self_deductions() {

        // we have to have at least 2 deductions to be able to start self deductions
        if (count($this->deductions) < 2) return;

        // do self deduction until there is no more to deduce
        while ($this->self_deduction());
    }

    /*
     * do self deduction - apply each deduction to the other deduction until deductions
     */
    protected function self_deduction() {

        // debug:
        echo "Executing self deduction\n";

        // loop through the deductions and reduce when possible
        for ($i = 0; $i < count($this->deductions); $i++) {

            // get the deduction we have and remove it from the rest of the deductions
            // echo "Self deduction for " . $this->deductions[$i]->toString() . "\n";

            // now reduce the other deductions from this deduction - except itself
            for ($j = 0; $j < count($this->deductions); $j++) {

                // cannot apply the deduction to itself
                if ($i == $j) continue;

                // if the pair of deductions help reduce the system, try again until we reach the stable state where there are no more deductions
                if ($this->reduce_deduction($i, $j)) return true;
            }
        }

        // we checked all deductions with one another - no more reductions - return false to stop the loop
        // echo "Deductions after self deduction: " . $this->toString();
        return false;
    }

    /*
     * reduce a single deduction from a new deduction when possible
     */
    protected function reduce_deduction($from_deduction, $deduction_to_reduce) {

        // debug: echo "Applying deduction " . $this->deductions[$from_deduction]->toString() . " to deduction: " . $this->deductions[$deduction_to_reduce]->toString() . "\n";

        // variable reductions - direct replacements
        if (is_a($this->deductions[$from_deduction]->left, 'Variable')) return $this->reduce_deduction_from_var($from_deduction, $deduction_to_reduce);

        // term reductions - zero products
        if (is_a($this->deductions[$from_deduction]->left, 'Term')) return $this->reduce_deduction_from_zero_product($from_deduction, $deduction_to_reduce);

        // if we get a deduction that is something other than term or direct variable, something's wrong - error out
        throw new Exception('Unknown deduction type: ' . print_r($this->deductions[$from_deduction]->left, true));
    }

    /*
     * reduce a single deduction from a new zero product deduction
     */
    protected function reduce_deduction_from_zero_product($from_deduction, $deduction_to_reduce) {

        // if the value is not zero, something's wrong
        if ($this->deductions[$from_deduction]->right == '1') throw new Exception('Zero product valued at one: ' . $this->deductions[$from_deduction]->toString());

        // deduction is a zero product type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Term')) return $this->reduce_zero_product_deduction_from_zero_product($from_deduction, $deduction_to_reduce);

        // deduction is a variable = binary expression type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'BinaryExpression')) return $this->reduce_var_expr_deduction_from_zero_product($from_deduction, $deduction_to_reduce);

        // deduction is a variable = boolean type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Boolean')) return $this->reduce_var_bool_deduction_from_zero_product($from_deduction, $deduction_to_reduce);

        // deduction is a variable = another variable type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Variable')) return $this->reduce_var_var_deduction_from_zero_product($from_deduction, $deduction_to_reduce);

        // deduction is a variable = constant value type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') && !is_object($this->deductions[$deduction_to_reduce]->right)) return $this->reduce_var_value_deduction_from_zero_product($from_deduction, $deduction_to_reduce);

        // unknown deduction type
        throw new Exception('Unknown deduction type: ' . $this->deductions[$deduction_to_reduce]->toString());
    }

    /*
     * reduce a single variable in a var = constant type deduction from a new var = constant type deduction
    */
    protected function reduce_var_value_deduction_from_zero_product($from_deduction, $deduction_to_reduce) {

        // nothing to change here
        return false;
    }

    /*
     * reduce a single variable in a var = variable type deduction from a new var = constant type deduction
    */
    protected function reduce_var_var_deduction_from_zero_product($from_deduction, $deduction_to_reduce) {

        // if the zero product has only a single variable, it may be applicable but the way our deductions work, that should never happen - error out if that is the case
        if (count($this->deductions[$from_deduction]->left->vars) == 1) throw new Exception('Encountered zero product with single variable: ' . $this->toString());

        // normal zero product deductions cannot be used in reducing var = var type deductions - return false
        return false;
    }

    /*
     * reduce a single variable in a var = expr type deduction from a zero product deduction
    */
    protected function reduce_var_expr_deduction_from_zero_product($from_deduction, $deduction_to_reduce) {

        // get the variable and expression of the deduction
        $var = $this->deductions[$deduction_to_reduce]->left->copy();
        $expr = $this->deductions[$deduction_to_reduce]->right->copy();

        // apply the zero product in the expression - if the deduction has not changed, return false - no new deductions
        if (!$expr->apply_zero_product($this->deductions[$from_deduction]->left)) return false;

        // remove the old deduction
        echo "Var = Expr Deduction reduced from zero product " . $this->deductions[$from_deduction]->toString() . "\n";
        $this->remove_deduction($deduction_to_reduce);

        // if the expression turned into a nothing, set the value as zero
        if (count($expr->terms) == 0) {
            $new_deduction = new Equation($var, 0);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from zero product as Var = Zero " . $new_deduction->toString() . "\n";
            return true;
        }

        // if the expression turned into a value, set the value as that
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 0) {
            $new_deduction = new Equation($var, $expr->terms[0]->val);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from zero product as Var = Value " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a single variable, convert the deduction as such - if it's not negated, just set the variable itself
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 1) {
            $new_deduction = new Equation($var, ($expr->terms[0]->vars[0]->negated ? $expr->terms[0]->vars[0] : $expr->terms[0]->vars[0]->var));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from zero product as Var = Var " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // regular reduction of var = expr to var = expr
        $new_deduction = new Equation($var, $expr);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Var = Expr Deduction reduced from zero product as Var = Expr " . $new_deduction->toString() . "\n";
        return true;
    }

    /*
     * reduce a single variable in a var = boolean variable type deduction from a zero product deduction
    */
    protected function reduce_var_bool_deduction_from_zero_product($from_deduction, $deduction_to_reduce) {

        // if the zero product has only a single variable, it may be applicable but the way our deductions work, that should never happen - error out if that is the case
        if (count($this->deductions[$from_deduction]->left->vars) == 1) throw new Exception('Encountered zero product with single variable: ' . $this->toString());

        // normal zero product deductions cannot be used in reducing var = boolean type deductions - return false
        return false;
    }

    /*
     * reduce a zero product deduction from another zero product deduction
    */
    protected function reduce_zero_product_deduction_from_zero_product($from_deduction, $deduction_to_reduce) {

        // ignore totalities
        if (count($this->deductions[$deduction_to_reduce]->left->vars) == 0) return false;
        if (count($this->deductions[$from_deduction]->left->vars) == 0) return false;

        // existing and new zero products
        $old_zero_product = $this->deductions[$deduction_to_reduce]->left;
        $new_zero_product = $this->deductions[$from_deduction]->left;
        $old_zero_product->sort();
        $new_zero_product->sort();

        // different zero products with the same 2 variables
        // e.g. x1x2 = 0 and x1'x2' = 0 => x1 = x2' or x1x2' = 0 and x1'x2 = 0 => x1 = x2 or x1'x2' = 0 and x1x2 = 0 => x1 = x2' or x1'x2 = 0 and x1x2' = 0 => x1 = x2
        if (count($old_zero_product->vars) == 2 &&
            count($new_zero_product->vars) == 2 &&
            $old_zero_product->vars[0]->toString() == $new_zero_product->vars[0]->negate()->toString() &&
            $old_zero_product->vars[1]->toString() == $new_zero_product->vars[1]->negate()->toString()) {

            echo "Deducing from " . $old_zero_product->toString() . " = 0 and " . $new_zero_product->toString() . " = 0\n";
            if ($old_zero_product->vars[0]->negated) echo $new_zero_product->vars[0]->var->toString() . " = " . $old_zero_product->vars[1]->toString() . "\n";
            else echo $old_zero_product->vars[0]->var->toString() . " = " . $new_zero_product->vars[1]->toString() . "\n";

            // add new deduction and remove old deductions
            if ($old_zero_product->vars[0]->negated) $new_deduction = new Equation($new_zero_product->vars[0]->var->copy(), $old_zero_product->vars[1]->copy());
            else $new_deduction = new Equation($old_zero_product->vars[0]->var->copy(), $new_zero_product->vars[1]->copy());
            $this->remove_deduction($deduction_to_reduce);
            $this->remove_deduction($from_deduction);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // identical deductions - remove one of them to de-dupe
        if ($new_zero_product->toString() == $old_zero_product->toString()) {
            echo "Identical deductions " . $old_zero_product->toString() . " = 0 and " . $new_zero_product->toString() . " = 0 - de-dupe\n";
            $this->remove_deduction($deduction_to_reduce);
            return true;
        }

        // loop through the term variables and see if they can be merged as a new deduction - there needs to be a negated variable and all other variables should appear identical or not appear at all
        // e.g. x1x2x3 = 0 and x1x2' = 0 => x1x3 = 0 or x1x2 = 0 and x1x2' = 0 => x1 = 0
        $new_product = new Term();
        $negated_var = false;
        $subset_product = true;
        foreach ($new_zero_product->vars as $new_var) {

            // search for the variable in the other expression
            $variable_status = 'missing';
            foreach ($old_zero_product->vars as $old_var) {

                // variable appears identical
                if ($old_var->toString() == $new_var->toString()) { $variable_status = 'identical'; break; }

                // variable appears negated
                if ($old_var->var->toString() == $new_var->var->toString()) { $variable_status = 'negated'; break; }
            }

            // if the variable does not appear identically, it cannot be a subset product
            if ($variable_status != 'identical') $subset_product = false;

            // if the variable appears identical in the other expression or does not appear at all, just add it to the new product as-is
            if ($variable_status == 'identical' || $variable_status == 'missing') { $new_product->add($new_var); continue; }

            // if the variable appears negated in the other expression
            if ($variable_status == 'negated') {

                // if we already encountered another negated variable, it means the expressions have more than one variable that appears negated - cannot be used for new deductions - return false
                if ($negated_var !== false) return false;

                // if we have not encountered a negated variable so far, set the negated variable
                $negated_var = $new_var; continue;
            }
        }

        // if all variables of the new zero product appears in the old zero product, old zero product is obsolete - remove
        if ($subset_product) {
            echo $new_zero_product->toString() . " = 0 makes " . $old_zero_product->toString() . " = 0 obsolete - remove\n";
            $this->remove_deduction($deduction_to_reduce);
            return true;
        }

        // if we could not find a negated variable, we cannot use these expressions for new deductions - return false
        if ($negated_var === false) return false;

        // now go through the other expression to include the variables we may have missed
        foreach ($old_zero_product->vars as $old_var) {

            // search for the variable in the other expression
            $variable_status = 'missing';
            foreach ($new_zero_product->vars as $new_var) {

                // variable appears identical
                if ($old_var->toString() == $new_var->toString()) { $variable_status = 'identical'; break; }

                // variable appears negated
                if ($old_var->var->toString() == $new_var->var->toString()) { $variable_status = 'negated'; break; }
            }

            // if the variable is identical or negated, we already processed it in the previous loop - nothing to do
            if ($variable_status == 'identical' || $variable_status == 'negated') continue;

            // if the variable does not appear in the other expression, just add it to the new product as-is
            if ($variable_status == 'missing') { $new_product->add($old_var); continue; }
        }

        // if the term is down to a single variable, convert the deduction to that form - if the variable is negated, it means it's one - otherwise it's zero
        if (count($new_product->vars) == 1) {
            echo "Deducing new value from " . $old_zero_product->toString() . " = 0 and " . $new_zero_product->toString() . " = 0\n";
            $new_deduction = new Equation($new_product->vars[0]->var->copy(), ($new_product->vars[0]->negated ? 1 : 0));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // new zero product deduction
        $new_deduction = new Equation($new_product, 0);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Deduced new zero product from " . $old_zero_product->toString() . " = 0 and " . $new_zero_product->toString() . " = 0\n";
        return true;
    }

    /*
     * reduce a single deduction from a new var = something deduction
     */
    protected function reduce_deduction_from_var($from_deduction, $deduction_to_reduce) {

        // single variable = binary expression
        if (is_object($this->deductions[$from_deduction]->right) && is_a($this->deductions[$from_deduction]->right, 'BinaryExpression')) return $this->reduce_deduction_from_var_expr($from_deduction, $deduction_to_reduce);

        // single variable = boolean variable
        if (is_object($this->deductions[$from_deduction]->right) && is_a($this->deductions[$from_deduction]->right, 'Boolean')) return $this->reduce_deduction_from_var_bool($from_deduction, $deduction_to_reduce);

        // single variable = another variable
        if (is_object($this->deductions[$from_deduction]->right) && is_a($this->deductions[$from_deduction]->right, 'Variable')) return $this->reduce_deduction_from_var_var($from_deduction, $deduction_to_reduce);

        // single variable = value
        if (!is_object($this->deductions[$from_deduction]->right)) return $this->reduce_deduction_from_var_value($from_deduction, $deduction_to_reduce);

        // otherwise unknown deduction
        throw new Exception('Unknown single variable deduction: ' . print_r($this->deductions[$from_deduction]->right, true));
    }

    /*
     * reduce a single deduction from a new var = constant value type deduction
    */
    protected function reduce_deduction_from_var_value($from_deduction, $deduction_to_reduce) {

        // deduction is a zero product type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Term')) return $this->reduce_zero_product_deduction_from_var_value($from_deduction, $deduction_to_reduce);

        // deduction is a variable = binary expression type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'BinaryExpression')) return $this->reduce_var_expr_deduction_from_var_value($from_deduction, $deduction_to_reduce);

        // deduction is a variable = boolean type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Boolean')) return $this->reduce_var_bool_deduction_from_var_value($from_deduction, $deduction_to_reduce);

        // deduction is a variable = another variable type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Variable')) return $this->reduce_var_var_deduction_from_var_value($from_deduction, $deduction_to_reduce);

        // deduction is a variable = constant value type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') && !is_object($this->deductions[$deduction_to_reduce]->right)) return $this->reduce_var_value_deduction_from_var_value($from_deduction, $deduction_to_reduce);

        // unknown deduction type
        throw new Exception('Unknown deduction type: ' . $this->deductions[$deduction_to_reduce]->toString());
    }

    /*
     * reduce a single variable in a var = boolean variable type deduction from a new var = constant type deduction
    */
    protected function reduce_var_bool_deduction_from_var_value($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, reduce the deduction
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = value deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right);
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // if the variable appears negated, reduce the deduction with the negated value
        if ($this->deductions[$deduction_to_reduce]->right->negate()->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = value deduction " . $this->deductions[$from_deduction]->toString() . " (negated)\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), ($this->deductions[$from_deduction]->right == '1' ? 0 : 1));
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // no new deductions - return false
        return false;
    }

    /*
     * reduce a single variable in a var = variable type deduction from a new var = constant type deduction
    */
    protected function reduce_var_var_deduction_from_var_value($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, reduce the deduction
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = var deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = value deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right);
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // no new deductions - return false
        return false;
    }

    /*
     * reduce a single variable in a zero product deduction from a new variable deduction that equals a value
    */
    protected function reduce_zero_product_deduction_from_var_value($from_deduction, $deduction_to_reduce) {

        // if the term does not contain our variable, nothing to do
        if (!$this->deductions[$deduction_to_reduce]->left->has_variable($this->deductions[$from_deduction]->left)) return false;

        // get the zero product and apply the new value in the term
        $zero_product = $this->deductions[$deduction_to_reduce]->left->copy();
        $zero_product->apply_var($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right);

        echo "Reducing zero product: " . $this->deductions[$deduction_to_reduce]->toString() . " from " . $this->deductions[$from_deduction]->toString() . "\n";

        // get rid of the old deduction
        $this->remove_deduction($deduction_to_reduce);

        // if the term is has no variables and has a value, it must be a totality now - cannot be a contradiction - if it's a totality it will be pruned - otherwise error out
        if (count($zero_product->vars) == 0) {

            // if the deduction became a contradiction, error out
            if ($zero_product->val == '1') throw new Exception('Unexpected zero product reduction contradiction');

            // show that the deduction got reduced to totality
            echo "Reduced deduction from zero product to totality\n";
            return true;
        }

        // if the term is down to a single variable, convert the deduction to that form - if the variable is negated, it means it's one - otherwise it's zero
        if (count($zero_product->vars) == 1) {
            $new_deduction = new Equation($zero_product->vars[0]->var, ($zero_product->vars[0]->negated ? 1 : 0));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Reduced deduction from zero product to var = value: " . $new_deduction->toString() . "\n";
            return true;
        }

        // regular zero product deduction
        $new_deduction = new Equation($zero_product, 0);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Reduced deduction: " . $new_deduction->toString() . "\n";
        exit;
        return true;
    }

    /*
     * reduce a single variable in a var = expr type deduction from a new var = value type deduction
    */
    protected function reduce_var_expr_deduction_from_var_value($from_deduction, $deduction_to_reduce) {

        // get the variable and the expression
        $var = $this->deductions[$deduction_to_reduce]->left->copy();
        $expr = $this->deductions[$deduction_to_reduce]->right->copy();

        // apply the variable replacement in the expression - if the expression has not changed, nothing to deduce
        if (!$expr->apply_var($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right)) return false;

        // remove the old deduction
        echo "Var = Expr Deduction reduced from Var = Value Deduction " . $this->deductions[$from_deduction]->toString() . "\n";
        $this->remove_deduction($deduction_to_reduce);

        // if the expression turned into a nothing, set the value as zero
        if (count($expr->terms) == 0) {
            $new_deduction = new Equation($var, 0);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Value Deduction as Var = Zero " . $new_deduction->toString() . "\n";
            return true;
        }

        // if the expression turned into a value, set the value as that
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 0) {
            $new_deduction = new Equation($var, $expr->terms[0]->val);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Value Deduction as Var = Value " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a single variable, convert the deduction as such - if it's not negated, just set the variable itself
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 1) {
            $new_deduction = new Equation($var, ($expr->terms[0]->vars[0]->negated ? $expr->terms[0]->vars[0] : $expr->terms[0]->vars[0]->var));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Value Deduction as Var = Var " . $new_deduction->toString() . "\n";
            return true;
        }

        // regular reduction of var = expr to var = expr
        $new_deduction = new Equation($var, $expr);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Var = Expr Deduction reduced from Var = Value Deduction as Var = Expr " . $new_deduction->toString() . "\n";
        return true;
    }

    /*
     * reduce a single deduction from a new variable deduction that equals another variable
    */
    protected function reduce_deduction_from_var_var($from_deduction, $deduction_to_reduce) {

        // deduction is a zero product type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Term')) return $this->reduce_zero_product_deduction_from_var_var($from_deduction, $deduction_to_reduce);

        // deduction is a variable = binary expression type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'BinaryExpression')) return $this->reduce_var_expr_deduction_from_var_var($from_deduction, $deduction_to_reduce);

        // deduction is a variable = boolean type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Boolean')) return $this->reduce_var_bool_deduction_from_var_var($from_deduction, $deduction_to_reduce);

        // deduction is a variable = another variable type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Variable')) return $this->reduce_var_var_deduction_from_var_var($from_deduction, $deduction_to_reduce);

        // deduction is a variable = constant value type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') && !is_object($this->deductions[$deduction_to_reduce]->right)) return $this->reduce_var_value_deduction_from_var_var($from_deduction, $deduction_to_reduce);

        // unknown deduction type
        throw new Exception('Unknown deduction type: ' . $this->deductions[$deduction_to_reduce]->toString());
    }

    /*
     * reduce a single variable in a var = expr type deduction from a new var = var type deduction
    */
    protected function reduce_var_expr_deduction_from_var_var($from_deduction, $deduction_to_reduce) {

        // get the variable and the expression
        $var = $this->deductions[$deduction_to_reduce]->left->copy();
        $expr = $this->deductions[$deduction_to_reduce]->right->copy();

        // apply the variable replacement in the expression - if the expression has not changed, nothing to deduce
        if (!$expr->apply_var_replace($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right)) return false;

        // remove the old deduction
        echo "Var = Expr Deduction reduced from Var = Var Deduction " . $this->deductions[$from_deduction]->toString() . "\n";
        $this->remove_deduction($this->deductions[$deduction_to_reduce]);

        // if the expression turned into a nothing, set the value as zero
        if (count($expr->terms) == 0) {
            $new_deduction = new Equation($var, 0);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Var Deduction as Var = Zero " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a value, set the value as that
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 0) {
            $new_deduction = new Equation($var, $expr->terms[0]->val);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Var Deduction as Var = Value " . $this->deductions[$deduction_to_reduce]->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a single variable, convert the deduction as such - if it's not negated, just set the variable itself
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 1) {
            $new_deduction = new Equation($var, ($expr->terms[0]->vars[0]->negated ? $expr->terms[0]->vars[0] : $expr->terms[0]->vars[0]->var));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Var Deduction as Var = Var " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // regular reduction of var = expr to var = expr
        $new_deduction = new Equation($var, $expr);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Var = Expr Deduction reduced from Var = Var Deduction as Var = Expr " . $new_deduction->toString() . "\n";
        exit;
        return true;
    }

    /*
     * reduce a single variable in a var = boolean variable type deduction from a new var = var type deduction
    */
    protected function reduce_var_bool_deduction_from_var_var($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, reduce the deduction
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = var deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->copy());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            exit;
            return true;
        }

        // if the variable appears negated, reduce the deduction with the negated value
        if ($this->deductions[$deduction_to_reduce]->right->negate()->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = var deduction " . $this->deductions[$from_deduction]->toString() . " (negated)\n";
            $newval = new Boolean($this->deductions[$from_deduction]->right->copy());
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $newval->negate());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            exit;
            return true;
        }

        // no new deductions - return false
        return false;
    }

    /*
     * reduce a single variable in a zero product deduction from a new variable deduction that equals a value
    */
    protected function reduce_zero_product_deduction_from_var_var($from_deduction, $deduction_to_reduce) {

        // if the term does not contain our variable, no new deductions can be found
        if (!$this->deductions[$deduction_to_reduce]->left->has_variable($this->deductions[$from_deduction]->left)) return false;

        // term does contain our variable - apply deduction
        echo "Reducing zero product deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from " . $this->deductions[$from_deduction]->toString() . "\n";

        // get the zero product and apply the new value in the term
        $zero_product = $this->deductions[$deduction_to_reduce]->left->copy();
        $zero_product->apply_var_replace($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right);

        // get rid of the old deduction
        $this->remove_deduction($deduction_to_reduce);

        // if the term is has no variables and has a value, it must be a totality now - cannot be a contradiction - if it's a totality it will be pruned - otherwise error out
        if (count($zero_product->vars) == 0) {

            // if the deduction became a contradiction, error out
            if ($zero_product->val == '1') throw new Exception('Unexpected zero product reduction contradiction');

            // show that the deduction got reduced to totality
            echo "Reduced deduction from zero product to totality\n";
            return true;
        }

        // if the term is down to a single variable, convert the deduction to that form - if the variable is negated, it means it's one - otherwise it's zero
        if (count($zero_product->vars) == 1) {
            $new_deduction = new Equation($zero_product->vars[0]->var, ($zero_product->vars[0]->negated ? 1 : 0));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Reduced deduction from zero product to var = var: " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // regular zero product deduction
        $new_deduction = new Equation($zero_product, 0);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Reduced deduction: " . $new_deduction->toString() . "\n";
        exit;
        return true;
    }

    /*
     * reduce a single deduction from a new variable deduction that equals another boolean variable
    */
    protected function reduce_deduction_from_var_bool($from_deduction, $deduction_to_reduce) {

        // deduction is a zero product type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Term')) return $this->reduce_zero_product_deduction_from_var_bool($from_deduction, $deduction_to_reduce);

        // deduction is a variable = binary expression type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'BinaryExpression')) return $this->reduce_var_expr_deduction_from_var_bool($from_deduction, $deduction_to_reduce);

        // deduction is a variable = boolean type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Boolean')) return $this->reduce_var_bool_deduction_from_var_bool($from_deduction, $deduction_to_reduce);

        // deduction is a variable = another variable type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Variable')) return $this->reduce_var_var_deduction_from_var_bool($from_deduction, $deduction_to_reduce);

        // deduction is a variable = constant value type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') && !is_object($this->deductions[$deduction_to_reduce]->right)) return $this->reduce_var_value_deduction_from_var_bool($from_deduction, $deduction_to_reduce);

        // unknown deduction type
        throw new Exception('Unknown deduction type: ' . $this->deductions[$deduction_to_reduce]->toString());
    }

    /*
     * reduce a single variable in a var = constant type deduction from a new var = boolean variable type deduction
    */
    protected function reduce_var_value_deduction_from_var_bool($from_deduction, $deduction_to_reduce) {

        // nothing to change here
        return false;
    }

    /*
     * reduce a single variable in a var = constant type deduction from a new var = variable type deduction
    */
    protected function reduce_var_value_deduction_from_var_var($from_deduction, $deduction_to_reduce) {

        // nothing to change here
        return false;
    }

    /*
     * reduce a single variable in a var = variable type deduction from a new var = boolean variable type deduction
    */
    protected function reduce_var_var_deduction_from_var_bool($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, reduce the deduction
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = var deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = bool deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->copy());
            if (!$new_deduction->right->negated) $new_deduction->right = $new_deduction->right->var;
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // if the variable does not appear, no new deductions
        return false;
    }

    /*
     * reduce a single variable in a var = expr type deduction from a new var = boolean variable type deduction
    */
    protected function reduce_var_expr_deduction_from_var_bool($from_deduction, $deduction_to_reduce) {

        // get the variable and the expression
        $var = $this->deductions[$deduction_to_reduce]->left->copy();
        $expr = $this->deductions[$deduction_to_reduce]->right->copy();

        // apply the variable replacement in the expression - if the expression has not changed, nothing to deduce
        if (!$expr->apply_var_replace($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right)) return false;

        // remove the old deduction
        echo "Var = Expr Deduction reduced from Var = Bool Deduction " . $this->deductions[$from_deduction]->toString() . "\n";
        $this->remove_deduction($deduction_to_reduce);

        // if the expression turned into a nothing, set the value as zero
        if (count($expr->terms) == 0) {
            $new_deduction = new Equation($var, 0);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Bool Deduction as Var = Zero " . $new_deduction->toString() . "\n";
            return true;
        }

        // if the expression turned into a value, set the value as that
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 0) {
            $new_deduction = new Equation($var, $expr->terms[0]->val);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Bool Deduction as Var = Value " . $this->deductions[$deduction_to_reduce]->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a single variable, convert the deduction as such - if it's not negated, just set the variable itself
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 1) {
            $new_deduction = new Equation($var, ($expr->terms[0]->vars[0]->negated ? $expr->terms[0]->vars[0] : $expr->terms[0]->vars[0]->var));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Bool Deduction as Var = Var/Bool " . $new_deduction->toString() . "\n";
            return true;
        }

        // regular reduction of var = expr to var = expr
        $new_deduction = new Equation($var, $expr);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Var = Expr Deduction reduced from Var = Bool Deduction as Var = Expr " . $new_deduction->toString() . "\n";
        return true;
    }

    /*
     * reduce a single variable in a var = boolean variable type deduction from a new var = boolean variable type deduction
    */
    protected function reduce_var_bool_deduction_from_var_bool($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, reduce the deduction
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = bool deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->copy());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // if the variable appears negated, reduce the deduction with the negated value
        if ($this->deductions[$deduction_to_reduce]->right->negate()->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = bool deduction " . $this->deductions[$from_deduction]->toString() . " (negated)\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->negate());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // if the variable does not appear, no new deductions
        return false;
    }

    /*
     * reduce a single variable in a zero product deduction from a new var = boolean variable deduction
    */
    protected function reduce_zero_product_deduction_from_var_bool($from_deduction, $deduction_to_reduce) {

        // if the term does not contain our variable, no new deductions can be found
        if (!$this->deductions[$deduction_to_reduce]->left->has_variable($this->deductions[$from_deduction]->left)) return false;

        // term does contain our variable - apply deduction
        echo "Reducing zero product deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from " . $this->deductions[$from_deduction]->toString() . "\n";

        // get the zero product and apply the new value in the term
        $zero_product = $this->deductions[$deduction_to_reduce]->left->copy();
        $zero_product->apply_var_replace($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right);

        // get rid of the old deduction
        $this->remove_deduction($deduction_to_reduce);

        // if the term is has no variables and has a value, it must be a totality now - cannot be a contradiction - if it's a totality it will be pruned - otherwise error out
        if (count($zero_product->vars) == 0) {

            // if the deduction became a contradiction, error out
            if ($zero_product->val == '1') throw new Exception('Unexpected zero product reduction contradiction');

            // show that the deduction got reduced to totality
            echo "Reduced deduction from zero product to totality\n";
            return true;
        }

        // if the term is down to a single variable, convert the deduction to that form - if the variable is negated, it means it's one - otherwise it's zero
        if (count($zero_product->vars) == 1) {
            $new_deduction = new Equation($zero_product->vars[0]->var, ($zero_product->vars[0]->negated ? 1 : 0));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Reduced deduction from zero product to var = bool: " . $new_deduction->toString() . "\n";
            return true;
        }

        // regular zero product deduction
        $new_deduction = new Equation($zero_product, 0);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Reduced deduction: " . $new_deduction->toString() . "\n";
        return true;
    }

    /*
     * reduce a single deduction from a new variable deduction that equals a binary expression
    */
    protected function reduce_deduction_from_var_expr($from_deduction, $deduction_to_reduce) {

        // deduction to reduce is a zero product type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Term')) return $this->reduce_zero_product_deduction_from_var_expr($from_deduction, $deduction_to_reduce);

        // deduction is a variable = binary expression type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'BinaryExpression')) return $this->reduce_var_expr_deduction_from_var_expr($from_deduction, $deduction_to_reduce);

        // deduction is a variable = boolean type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Boolean')) return $this->reduce_var_bool_deduction_from_var_expr($from_deduction, $deduction_to_reduce);

        // deduction is a variable = another variable type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') &&
            is_object($this->deductions[$deduction_to_reduce]->right) && is_a($this->deductions[$deduction_to_reduce]->right, 'Variable')) return $this->reduce_var_var_deduction_from_var_expr($from_deduction, $deduction_to_reduce);

        // deduction is a variable = constant value type deduction
        if (is_object($this->deductions[$deduction_to_reduce]->left) && is_a($this->deductions[$deduction_to_reduce]->left, 'Variable') && !is_object($this->deductions[$deduction_to_reduce]->right)) return $this->reduce_var_value_deduction_from_var_expr($from_deduction, $deduction_to_reduce);

        // unknown deduction type
        throw new Exception('Unknown deduction type: ' . $this->deductions[$deduction_to_reduce]->toString());
    }

    /*
     * reduce a single variable in a var = expr type deduction from a new var = expr variable type deduction
    */
    protected function reduce_var_expr_deduction_from_var_expr($from_deduction, $deduction_to_reduce) {

        // get the variable and the expression
        $var = $this->deductions[$deduction_to_reduce]->left->copy();
        $expr = $this->deductions[$deduction_to_reduce]->right->copy();

        // apply the variable replacement in the expression - if the expression has not changed, nothing to deduce
        if (!$expr->apply_var_expr($this->deductions[$from_deduction]->left, $this->deductions[$from_deduction]->right)) return false;

        // remove the old deduction
        echo "Var = Expr Deduction reduced from Var = Expr Deduction " . $this->deductions[$from_deduction]->toString() . "\n";
        $this->remove_deduction($this->deductions[$deduction_to_reduce]);

        // if the expression turned into a nothing, set the value as zero
        if (count($expr->terms) == 0) {
            $new_deduction = new Equation($var, 0);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Expr Deduction as Var = Zero " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a value, set the value as that
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 0) {
            $new_deduction = new Equation($var, $expr->terms[0]->val);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Expr Deduction as Var = Value " . $this->deductions[$deduction_to_reduce]->toString() . "\n";
            exit;
            return true;
        }

        // if the expression turned into a single variable, convert the deduction as such - if it's not negated, just set the variable itself
        if (count($expr->terms) == 1 && count($expr->terms[0]->vars) == 1) {
            $new_deduction = new Equation($var, ($expr->terms[0]->vars[0]->negated ? $expr->terms[0]->vars[0] : $expr->terms[0]->vars[0]->var));
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            echo "Var = Expr Deduction reduced from Var = Expr Deduction as Var = Var " . $new_deduction->toString() . "\n";
            exit;
            return true;
        }

        // regular reduction of var = expr to var = expr
        $new_deduction = new Equation($var, $expr);
        if (!$this->add_if_not_deduced($new_deduction)) return false;
        echo "Var = Expr Deduction reduced from Var = Expr Deduction as Var = Expr " . $new_deduction->toString() . "\n";
        exit;
        return true;
    }

    /*
     * reduce a single variable in a var = boolean variable type deduction from a new var = expr variable type deduction
    */
    protected function reduce_var_bool_deduction_from_var_expr($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, reduce the deduction
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = expr deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->copy());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            return true;
        }

        // if the variable appears negated, reduce the deduction with the negated value
        if ($this->deductions[$deduction_to_reduce]->right->negate()->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = bool deduction " . $this->deductions[$deduction_to_reduce]->toString() . " from var = expr deduction " . $this->deductions[$from_deduction]->toString() . " (negated)\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->negate()->simplify()->unify()->merge_terms());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            exit;
            return true;
        }

        // if the variable does not appear, return false to indicate no changes
        return false;
    }

    /*
     * reduce a single variable in a zero product deduction from a new var = expr variable deduction
    */
    protected function reduce_zero_product_deduction_from_var_expr($from_deduction, $deduction_to_reduce) {

        // if the zero product does not contain our variable, nothing to do
        if (!$this->deductions[$deduction_to_reduce]->left->has_variable($this->deductions[$from_deduction]->left)) return false;

        // essentially we will do 0 = expr * term_without_var and then deduce further and delete the original one - these are the new deductions we will add
        // if the variable appears in non-negated form, remove the variable and "and" the expression
        $bool = new Boolean($this->deductions[$from_deduction]->left);
        if ($this->deductions[$deduction_to_reduce]->left->has_boolean($bool)) {
            $new_expr = $this->deductions[$from_deduction]->right->and_expr(new BinaryExpression(array($this->deductions[$deduction_to_reduce]->left->remove_variable($bool))));
        }
        // if the variable appears in negated form, remove the negated variable and "and" the negated expression
        elseif ($this->deductions[$deduction_to_reduce]->left->has_boolean($bool->negate())) {
            $new_expr = $this->deductions[$from_deduction]->right->negate()->and_expr(new BinaryExpression(array($this->deductions[$deduction_to_reduce]->left->remove_variable($bool->negate()))));
        }
        else throw new Exception('Should not reach here - found the variable in term but then could not');

        // add the new expression terms as zero product deductions
        echo "Reducing zero product deduction " . $this->deductions[$deduction_to_reduce]->toString() . " with var = expr deduction " . $this->deductions[$from_deduction]->toString() . "\n";
        foreach ($new_expr->terms as $new_term) {
            $new_deduction = new Equation($new_term, 0);
            $this->add_if_not_deduced($new_deduction);
        }

        // remove the original deduction
        $this->remove_deduction($deduction_to_reduce);

        // return true to indicate that we have new deductions
        return true;
    }

    /*
     * reduce a single variable in a var = constant type deduction from a new var = expr variable type deduction
    */
    protected function reduce_var_value_deduction_from_var_expr($from_deduction, $deduction_to_reduce) {

        // nothing to change here  - cannot reduce var = constant ever
        return false;
    }

    /*
    * reduce a single variable in a var = variable type deduction from a new var = var type deduction
   */
    protected function reduce_var_var_deduction_from_var_var($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, set the value
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = var " . $this->deductions[$deduction_to_reduce]->toString() . " from var = var deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->copy());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            exit;
            return true;
        }

        // if the variable does not appear, return it unchanged
        return false;
    }

    /*
      * reduce a single variable in a var = variable type deduction from a new var = expr variable type deduction
     */
    protected function reduce_var_var_deduction_from_var_expr($from_deduction, $deduction_to_reduce) {

        // if the variable is the same, set the value
        if ($this->deductions[$deduction_to_reduce]->right->toString() == $this->deductions[$from_deduction]->left->toString()) {
            echo "Reducing var = var " . $this->deductions[$deduction_to_reduce]->toString() . " from var = expr deduction " . $this->deductions[$from_deduction]->toString() . "\n";
            $new_deduction = new Equation($this->deductions[$deduction_to_reduce]->left->copy(), $this->deductions[$from_deduction]->right->copy());
            $this->remove_deduction($deduction_to_reduce);
            if (!$this->add_if_not_deduced($new_deduction)) return false;
            exit;
            return true;
        }

        // no matches
        return false;
    }

    /*
    * reduce a single variable in a var = constant type deduction from a new var = constant type deduction
   */
    protected function reduce_var_value_deduction_from_var_value($from_deduction, $deduction_to_reduce) {

        // nothing to change here
        return false;
    }

    /*
       * gets rid of totalities
       */
    protected function prune_totalities() {

        // check the totalities and conflicts
        $totalities = array();
        for ($i = 0; $i < count($this->deductions); $i++) {

            // convert zero terms
            if (is_object($this->deductions[$i]->left) && is_a($this->deductions[$i]->left, 'Term') && count($this->deductions[$i]->left->vars) == 0) $this->deductions[$i]->left = (!$this->deductions[$i]->left->val ? 0 : 1);

            // totalities and conflicts have values on both sides
            if (!is_object($this->deductions[$i]->left) && !is_object($this->deductions[$i]->right)) {

                // if both sides equal to each other, it's a totality - otherwise it's a conflict
                if ($this->deductions[$i]->left == $this->deductions[$i]->right) $totalities[] = $i;
                else throw new Exception('Conflict in deduction ' . $i);
            }
        }

        // now get rid of totalities in the array
        if ($totalities) {
            foreach ($totalities as $totality) unset($this->deductions[$totality]);
            $this->deductions = array_values($this->deductions);
        }
    }

    /*
     * get the solution
     */
    public function get_solution() {

        // get rid of totalities first
        $this->prune_totalities();

        // extracted x and y digits
        $x = array();
        $y = array();

        // loop through the deductions
        foreach ($this->deductions as $deduction) {

            // if the deduction is not of var=value type, we can't deduce a solution
            if (!is_a($deduction->left, 'Variable')) {
                // debug: echo "Not a variable!\n"; print_r($deduction[0]);
                return false;
            }
            if (is_object($deduction->right)) {
                // debug: echo "Object!\n"; print_r($deduction[0]); print_r($deduction[1]);
                return false;
            }

            // get the x/y value
            if ($deduction->left->type == x) $x[intval($deduction->left->digit)] = $deduction->right;
            else $y[intval($deduction->left->digit)] = $deduction->right;
        }

        // sort the arrays to put them in right order
        ksort($x);
        ksort($y);

        // looks like all deductions are var=value type - return the solution
        return array(strrev(implode('', $x)), strrev(implode('', $y)));
    }



}