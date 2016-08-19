<?php

require_once 'binary.expression.class.php';

/*
 * sum - contains a sequence of binary expressions added to one another 
*/
class Sum {

	// binary expressions array 
	public $exprs;

	/*
	 * constructor - binary expressions in the expression 
	*/
	public function __construct($exprs = array()) {
		$this->exprs = $exprs;
	}

	/* 
	 * add a new binary expression to the sum
	 */
	public function add($expr) {
		$this->exprs[] = $expr;
	}
	
	/* 
	 * copy current sum
	 */
	public function copy() { 
		return new Sum(array_map(function($expr) { return $expr->copy(); }, $this->exprs)); 
	}

    /*
     * adds expressions from a new sum
     */
    function add_sum($sum) {
        foreach ($sum->exprs as $expr) $this->add($expr);
    }

    /*
     * merges two sums and returns a new one (adds them)
     */
	static function merge($sum1, $sum2) { 
		$sum = new Sum($sum1->exprs);
		foreach ($sum2->exprs as $expr) $sum->add($expr);
		return $sum;
	}
	
	/* 
	 * returns the sum as a string 
	 */
	public function toString() { 
		$retval = '';
		for ($i = 0; $i < count($this->exprs); $i++) { 
			$retval .= $this->exprs[$i]->toString() . ($i != count($this->exprs)-1 ? ' + ' : '');
		}
		return '[' . $retval . ']';
	}
	
	/* 
	 * takes mod 2 of a sum - returns a binary expression
	 */
	public function mod_alt1() {

		// debug: 
		echo "Taking mod 2 of " . $this->toString() . "\n";
		
		// apply simplifications before mod
		$this->simplify()->unify()->merge_terms();
		// debug: 
		echo 'after simplifications: ' . $this->toString() . "\n";
		
		// if we have no expressions, nothing to do - return empty binary expression 
		if (count($this->exprs) == 0) return new BinaryExpression();
		
		// if we have only one expression, return binary expression with the same terms 
		if (count($this->exprs) == 1) return $this->exprs[0]->copy();

		// if a term appears twice in the sum, remove it - (x + x + y mod 2) = (y mod 2)
		$duplicate_expressions = $this->duplicate_expressions();
		if ($duplicate_expressions) {
			echo "duplicate expression found in mod - removing: " . $this->exprs[$duplicate_expressions[0]]->toString() . "\n";
			$sum = new Sum();
			for ($i = 0; $i < count($this->exprs); $i++) if ($i != $duplicate_expressions[0] && $i != $duplicate_expressions[1]) $sum->add($this->exprs[$i]->copy());
			$new_sum = $sum->mod();
			echo "duplicate expression mod result of " . $this->toString() . ": " . $new_sum->toString() . "\n";
			return $new_sum;
		}
		
		// if we have only 2 binary expressions, (A + B mod 2) = (A and B') or (A' and B)
		if (count($this->exprs) == 2) { 
			// debug: 
			echo '2 expr mod: ' . $this->toString() . "\n";
			$binary_expr1 = $this->exprs[0]->copy();
			$binary_expr2 = $this->exprs[1]->copy();
			// debug: 
			echo 'initial expression: ' . $binary_expr2->toString() . "\n"; 
			// debug: 
			echo 'negated: ' . $binary_expr2->negate()->toString() . "\n";
			// debug: 
			echo 'expr to be anded: ' . $binary_expr1->toString() . "\n"; 
			// debug: 
			echo 'anded expr: ' . $binary_expr2->negate()->and_expr($binary_expr1)->toString() . "\n";
			$expr = $binary_expr2->negate()->and_expr($binary_expr1);
			$expr2 = $binary_expr1->negate()->and_expr($binary_expr2);
			$expr->add($expr2); 
			
			// apply unifications before mod
			$expr->simplify()->unify()->merge_terms();
			// debug: 
			echo 'after simplifications: ' . $expr->toString() . "\n";
			
			// debug: echo 'expr: ' . $expr->toString() . "\n"; exit;
			return $expr;
		}
		
		// if we have more than 2 terms, calculate the result recursively 
		// debug: 
		echo 'multi-term mod: ' . $this->toString() . "\n";
		$binary_expr1 = $this->exprs[0]->copy();
		$tmpsum = new Sum(array_slice($this->exprs,1));
		// debug: 
		echo 'taking mod of ' . $tmpsum->toString() . "\n";
		$binary_expr2 = $tmpsum->mod();
		// debug: 
		echo 'multi-term mod expr1: ' . $binary_expr1->toString() . "\n";
		// debug: 
		echo 'multi-term mod expr2: ' . $binary_expr2->toString() . "\n";
		$binary_expr1_negated = $binary_expr1->negate();
		$binary_expr2_negated = $binary_expr2->negate();
		// debug: 
		echo 'multi-term mod expr1 negated: ' . $binary_expr1_negated->toString() . "\n";
		// debug: 
		echo 'multi-term mod expr2 negated: ' . $binary_expr2_negated->toString() . "\n";
		$binary_expr1_negated->simplify()->unify()->merge_terms();
		$binary_expr2_negated->simplify()->unify()->merge_terms();
		// debug: 
		echo 'multi-term mod expr1 negated and simplified: ' . $binary_expr1_negated->toString() . "\n";
		// debug: 
		echo 'multi-term mod expr2 negated and simplified: ' . $binary_expr2_negated->toString() . "\n";
		$expr = $binary_expr2_negated->and_expr($binary_expr1);
		// debug: 
		echo 'multi-term mod expr1 sum: ' . $expr->toString() . "\n";
		$expr2 = $binary_expr1_negated->and_expr($binary_expr2);
		// debug: 
		echo 'multi-term mod expr2 sum: ' . $expr2->toString() . "\n";
		$expr->add($expr2);
		// debug: 
		echo 'multi-term mod result before simplifications: ' . $expr->toString() . "\n";
		$expr->simplify()->unify()->merge_terms();
		$expr->simplify()->unify()->merge_terms();
		// debug: 
		echo 'multi-term mod result: ' . $expr->toString() . "\n";
		return $expr;
	}
	
	/* 
	 * applies deductions to the sum 
	 */
	public function apply_deductions($deductions) { 
		
		// go through all expressions and apply deductions in each one  
		for ($i = 0; $i < count($this->exprs); $i++) $this->exprs[$i]->apply_deductions($deductions); 
	}
	
	/* 
	 * applies term merges to the sum 
	 */
	public function merge_terms() { 
		
		// go through all expressions and merge terms in each one  
		for ($i = 0; $i < count($this->exprs); $i++) $this->exprs[$i]->merge_terms();

		return $this; 
	}
	
	/* 
	 * unifies each expression  
	 */
	public function unify() { 
		
		// go through all expressions and merge terms in each one  
		for ($i = 0; $i < count($this->exprs); $i++) $this->exprs[$i]->unify();

		return $this; 
	}
	
	/* 
	 * returns the duplicate expressions in sum - when applicable 
	 */
	protected function duplicate_expressions() { 
		
		// go through the expressions and see if they appear elsewhere in the sum 
		for ($i = 0; $i < count($this->exprs); $i++) {
		
			// check if the expression appears elsewhere
			for ($j = 0; $j < count($this->exprs); $j++) if ($i != $j && $this->exprs[$i]->equals($this->exprs[$j])) return array($i, $j);
		}
		
		// no duplicate expression found - return false 
		return false;
	}

    /*
     * determines the deduction expression - should be an expression that contains a single variable by itself - returns the index of it
    */
    public function determine_deduction_expr() {

        // check the expressions in the sum - pick the first one that appears by itself and nowhere else
        $potentials = array();
        for ($i = 0; $i < count($this->exprs); $i++) if (count($this->exprs[$i]->terms) == 1 && count($this->exprs[$i]->terms[0]->vars) == 1) $potentials[$i] = $this->exprs[$i]->terms[0]->vars[0];

        // now check to see if the potentials appear anywhere else
        foreach ($potentials as $index => $var) {

            $variable_found = false;
            for ($i = 0; $i < count($this->exprs); $i++) {
                if ($i == $index) continue;
                foreach ($this->exprs[$i]->terms as $term) if ($term->has_variable($var->var)) { $variable_found = true; break 2; }
            }
            if (!$variable_found) return $index;
        }

        // if we could not find an expression that contains a single variable, we will try to do zero product deductions
        echo 'Could not find a deduction expression: ' . $this->toString() . "\n";
        return -1;
    }

    /*
     * removes a given expression from the sum and returns the new sum without it
    */
    public function remove_expr($expr_index) {
        $sum = $this->copy();
        unset($sum->exprs[$expr_index]);
        $sum->exprs = array_values($sum->exprs);
        return $sum;
    }

    /*
     * returns the most commonly used variable in the expression - only works if all expressions are single term expressions
     */
    public function most_commonly_used_variable() {

        // determine the number of times each variable is used
        $var_usages = array();
        $var_index = array();
        foreach ($this->exprs as $expr) {
            foreach ($expr->terms[0]->vars as $var) {
                $var_name = $var->var->toString();
                if (isset($var_usages[$var_name])) {
                    $var_usages[$var_name]++;
                }
                else {
                    $var_index[$var_name] = $var->var;
                    $var_usages[$var_name] = 1;
                }
            }
        }

        // reverse sort to get the most commonly used variable
        arsort($var_usages);
        // debug: print_r($var_usages);

        // return the most commonly used variable
        reset($var_usages);
        return $var_index[key($var_usages)];
    }

    /*
     * removes duplicate expressions from the sum
    */
    public function remove_duplicate_expressions() {

        $duplicate_expressions = $this->duplicate_expressions();
        if (!$duplicate_expressions) return;

        echo "duplicate expression found in mod - removing: " . $this->exprs[$duplicate_expressions[0]]->toString() . "\n";
        $sum = new Sum();
        for ($i = 0; $i < count($this->exprs); $i++) if ($i != $duplicate_expressions[0] && $i != $duplicate_expressions[1]) $sum->add($this->exprs[$i]->copy());
        $this->exprs = $sum->exprs;
    }

    /*
     * takes mod 2 of a sum - returns a binary expression
     */
    public function mod($split_var = false) {

        // debug:
        echo "Taking mod 2 of " . $this->toString() . "\n";

        // if we have no expressions, nothing to do - return empty binary expression
        if (count($this->exprs) == 0) return new BinaryExpression();

        // if we have only one expression, return binary expression with the same terms
        if (count($this->exprs) == 1) return $this->exprs[0]->copy();

        // if a term appears twice in the sum, remove it - (x + x + y mod 2) = (y mod 2)
        $duplicate_expressions = $this->duplicate_expressions();
        if ($duplicate_expressions) {
            echo "duplicate expression found in mod - removing: " . $this->exprs[$duplicate_expressions[0]]->toString() . "\n";
            $sum = new Sum();
            for ($i = 0; $i < count($this->exprs); $i++) if ($i != $duplicate_expressions[0] && $i != $duplicate_expressions[1]) $sum->add($this->exprs[$i]->copy());
            $new_sum = $sum->mod();
            echo "duplicate expression mod result of " . $this->toString() . ": " . $new_sum->toString() . "\n";
            return $new_sum;
        }

        // new expression to be returned
        $expr = new BinaryExpression();

        // first, convert all expressions to sums
        $this->convert_expressions_to_sums();
        echo "Converted expressions to sums: " . $this->toString() . "\n";

        // check to make sure that all expressions consist of a single term
        foreach ($this->exprs as $myexpr) if (count($myexpr->terms) != 1) throw new Exception('Expression not single term after conversion to sum: ' . $this->toString());

        // determine the split variable if not given - most frequently used variable
        if (!$split_var) $split_var = $this->most_commonly_used_variable();
        // debug:
        echo "Most commonly used variable: " . $split_var->toString() . "\n";

        // standard and negated forms of the variable => f(x) = xa + x'b + c
        $x = new Boolean($split_var);
        $x_negated = $x->negate();

        // functions we will get as a result of the variable split => f(x) = xa + x'b + c
        $sum_a = new Sum();
        $sum_b = new Sum();
        $sum_c = new Sum();

        // loop through the terms and split based on variable
        foreach ($this->exprs as $myexpr) {
            // debug: echo "checking expr: " . $myexpr->toString() . "\n";
            if ($myexpr->terms[0]->has_boolean($x)) $sum_a->exprs[] = new BinaryExpression(array($myexpr->terms[0]->remove_variable($x)));
            elseif ($myexpr->terms[0]->has_boolean($x_negated)) $sum_b->exprs[] = new BinaryExpression(array($myexpr->terms[0]->remove_variable($x_negated)));
            else $sum_c->exprs[] = new BinaryExpression(array($myexpr->terms[0]->copy()));
        }

        // debug:
        echo "Sum a: " . $sum_a->toString() . "\n";
        echo "Sum b: " . $sum_b->toString() . "\n";
        echo "Sum c: " . $sum_c->toString() . "\n";

        // if there are no terms in sum a, it means variable x did not occur at all
        if (count($sum_a->exprs) == 0) $sum_a_type = 'zero';
        // if there is only a single term with no variables in it, that means the split variable occurred by itself - in that case, a = 1 and a' = 0
        elseif (count($sum_a->exprs) == 1 && count($sum_a->exprs[0]->terms) == 1 && count($sum_a->exprs[0]->terms[0]->vars) == 0) $sum_a_type = 'one';
        // otherwise x occurred with some variables along with x
        else $sum_a_type = 'expr';

        // if there are no terms in sum b, it means variable x' did not occur at all
        if (count($sum_b->exprs) == 0) $sum_b_type = 'zero';
        // if there is only a single term with no variables in it, that means x' occurred by itself - in that case, b = 1 and b' = 0
        elseif (count($sum_b->exprs) == 1 && count($sum_b->exprs[0]->terms) == 1 && count($sum_b->exprs[0]->terms[0]->vars) == 0) $sum_b_type = 'one';
        // otherwise x occurred with some variables along with x
        else $sum_b_type = 'expr';

        // if there are no terms in sum c, it means all terms had x or x'
        if (count($sum_c->exprs) == 0) $sum_c_type = 'zero';
        // if there is only a single term with no variables in it, it means C is zero or one
        elseif (count($sum_c->exprs) == 1 && count($sum_c->exprs[0]->terms) == 1 && count($sum_c->exprs[0]->terms[0]->vars) == 0 && $sum_c->exprs[0]->terms[0]->val == 0) $sum_c_type = 'zero';
        // if there is only a single term with no variables in it, it means C is zero or one
        elseif (count($sum_c->exprs) == 1 && count($sum_c->exprs[0]->terms) == 1 && count($sum_c->exprs[0]->terms[0]->vars) == 0 && $sum_c->exprs[0]->terms[0]->val == 1) $sum_c_type = 'one';
        // otherwise C is an expression
        else $sum_c_type = 'expr';

        // debug:
        echo "Sum a type: " . $sum_a_type . "\n";
        echo "Sum b type: " . $sum_b_type . "\n";
        echo "Sum c type: " . $sum_c_type . "\n";

        // if A = 0 and B = 0 and C = 0, sum = 0 but that should not happen - it's handled above
        if ($sum_a_type == 'zero' && $sum_b_type == 'zero' && $sum_c_type == 'zero') throw new Exception('All sums zero - should not reach this code.');

        // do recursive mods if needed
        if ($sum_a_type == 'expr') { $expr_a = $sum_a->mod(); echo "Mod a: " . $expr_a->toString() . "\n"; }
        if ($sum_b_type == 'expr') { $expr_b = $sum_b->mod(); echo "Mod b: " . $expr_b->toString() . "\n"; }
        if ($sum_c_type == 'expr') { $expr_c = $sum_c->mod(); echo "Mod c: " . $expr_c->toString() . "\n"; }

        // if C = 1, mod = xA' + x'B' + A'B'
        if ($sum_c_type == 'one') {

            // if A = 0 and B = 1 (e.g. x' + 1), mod = x
            if ($sum_a_type == 'zero' && $sum_b_type == 'one') {
                $expr->add_term(new Term(array($x)));
            }
            // if A = 0 and B = expr (e.g. x'B + 1), mod = x + B'
            elseif ($sum_a_type == 'zero' && $sum_b_type == 'expr') {
                $expr_b_negated = $expr_b->negate();
                $expr->add_term(new Term(array($x)));
                $expr->add($expr_b_negated);
            }
            // if A = 1 and B = 0 (e.g. x + 1), mod = x'
            elseif ($sum_a_type == 'one' && $sum_b_type == 'zero') {
                $expr->add_term(new Term(array($x_negated)));
            }
            // if A = 1 and B = 1 (e.g. x + x' + 1), mod = 0
            elseif ($sum_a_type == 'one' && $sum_b_type == 'one') {
                // nothing to add
            }
            // if A = 1 and B = expr (e.g. x + x'B + 1), mod = x'B'
            elseif ($sum_a_type == 'one' && $sum_b_type == 'expr') {
                $expr_b_negated = $expr_b->negate();
                $expr->add($expr_b_negated->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = 0 (e.g. xA + 1), mod = x' + A'
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'zero') {
                $expr_a_negated = $expr_a->negate();
                $expr->add_term(new Term(array($x_negated)));
                $expr->add($expr_a_negated);
            }
            // if A = expr and B = 1 (e.g. xA + x' + 1), mod = xA'
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'one') {
                $expr_a_negated = $expr_a->negate();
                $expr->add($expr_a_negated->and_expr(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = expr, mod = xA' + x'B' + A'B'
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'expr') {
                $expr_a_negated = $expr_a->negate();
                $expr_b_negated = $expr_b->negate();
                $expr->add($expr_a_negated->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_b_negated->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_a_negated->and_expr($expr_b_negated));
            }
            else {
                throw new Exception('Unknown condition - should not have reached here.');
            }
        }
        // if C = 0, mod = xA + x'B
        elseif ($sum_c_type == 'zero') {

            // if A = 0 and B = 1 (e.g. x'), mod = x'
            if ($sum_a_type == 'zero' && $sum_b_type == 'one') {
                $expr->add_term(new Term(array($x_negated)));
            }
            // if A = 0 and B = expr (e.g. x'B), mod = x'B
            elseif ($sum_a_type == 'zero' && $sum_b_type == 'expr') {
                $expr->add($expr_b->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = 1 and B = 0 (e.g. x), mod = x
            elseif ($sum_a_type == 'one' && $sum_b_type == 'zero') {
                $expr->add_term(new Term(array($x)));
            }
            // if A = 1 and B = 1 (e.g. x + x'), mod = x + x' = 1
            elseif ($sum_a_type == 'one' && $sum_b_type == 'one') {
                $expr->add_term(new Term(array(), 1));
            }
            // if A = 1 and B = expr (e.g. x + x'B), mod = x + x'B
            elseif ($sum_a_type == 'one' && $sum_b_type == 'expr') {
                $expr->add_term(new Term(array($x)));
                $expr->add($expr_b->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = 0, mod = xA
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'zero') {
                $expr->add($expr_a->and_expr(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = 1, mod = xA + x'
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'one') {
                $expr->add($expr_a->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add_term(new Term(array($x_negated)));
            }
            // if A = expr and B = expr, mod = xA + x'B
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'expr') {
                $expr->add($expr_a->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_b->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            else {
                throw new Exception('Unknown condition - should not have reached here.');
            }
        }
        // if C != 0, mod = xAC' + xA'C + x'BC' + x'B'C + A'B'C
        else {

            // if A = 0 and B = 0, mod = C but that should not happen - we could not find the split variable?
            if ($sum_a_type == 'zero' && $sum_b_type == 'zero') {

                echo "Sum a: " . $sum_a->toString() . "\n";
                echo "Sum b: " . $sum_b->toString() . "\n";
                echo "Sum c: " . $sum_c->toString() . "\n";

                throw new Exception('Expr C found to be zero - does not make sense.');
            }
            // if A = 0 and B = 1 (e.g. x' + C), mod = xC + x'C'
            if ($sum_a_type == 'zero' && $sum_b_type == 'one') {
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_c->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_c_negated->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = 0 and B = expr (e.g. x'B + C), mod = xC + x'BC' + B'C
            elseif ($sum_a_type == 'zero' && $sum_b_type == 'expr') {
                $expr_c_negated = $expr_c->negate();
                $expr_b_negated = $expr_b->negate();
                $expr->add($expr_c->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_b->and_expr($expr_c_negated)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_b_negated->and_expr($expr_c));
            }
            // if A = 1 and B = 0 (e.g. x + C), mod = xC' + x'C
            elseif ($sum_a_type == 'one' && $sum_b_type == 'zero') {
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_c->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_c_negated->and_expr(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = 1 and B = 1 (e.g. x + x' + C), mod = C'
            elseif ($sum_a_type == 'one' && $sum_b_type == 'one') {
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_c_negated);
            }
            // if A = 1 and B = expr (e.g. x + x'B + C), mod = xC' + x'BC' + x'B'C
            elseif ($sum_a_type == 'one' && $sum_b_type == 'expr') {
                $expr_b_negated = $expr_b->negate();
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_c_negated->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_b->and_expr($expr_c_negated)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_b_negated->and_expr($expr_c)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = 0 (e.g. xA + C), mod = xAC' + x'C + A'C
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'zero') {
                $expr_a_negated = $expr_a->negate();
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_a->and_expr($expr_c_negated)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_c->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_a_negated->and_expr($expr_c));
            }
            // if A = expr and B = 1 (e.g. xA + x' + C), mod = xAC' + xA'C + x'C'
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'one') {
                $expr_a_negated = $expr_a->negate();
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_a->and_expr($expr_c_negated)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_a_negated->and_expr($expr_c)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_c_negated->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = expr (e.g. xA + x'B + C), mod = xAC' + xA'C + x'BC' + x'B'C + A'B'C
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'expr') {
                $expr_a_negated = $expr_a->negate();
                $expr_b_negated = $expr_b->negate();
                $expr_c_negated = $expr_c->negate();
                $expr->add($expr_a->and_expr($expr_c_negated)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_a_negated->and_expr($expr_c)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $expr->add($expr_b->and_expr($expr_c_negated)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_b_negated->and_expr($expr_c)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
                $expr->add($expr_a_negated->and_expr($expr_b_negated)->and_expr($expr_c));
            }
            else {
                throw new Exception('Unknown condition - should not have reached here.');
            }
        }

        // debug: echo "Mod before simplification: " . $expr->toString() . "\n";
        $expr->simplify()->unify()->merge_terms();
        // debug:
        echo "Mod after simplification " . $this->toString() . ": " . $expr->toString() . "\n";

        // debug: if (count($this->terms) == 3) exit;

        // return the new expression
        return $expr;
    }

    /*
     * converts all expressions in the sum to sums so that each one will be single term expressions
    */
    protected function convert_expressions_to_sums() {

        // new expressions we will have
        $exprs = array();

        // loop through each expression and convert expressions to sums
        for ($i = 0; $i < count($this->exprs); $i++) $exprs = array_merge($exprs, $this->exprs[$i]->convert_to_sum()->exprs);

        // set the new expressions
        $this->exprs = $exprs;

        // debug: echo "Converted all expressions to sums: " . $this->toString() . "\n";
    }

    /*
     * takes div 2 of an expression - returns sum (not binary expression)
    */
	public function div($split_var = false) {
	
		// debug:
        echo "Taking div 2 of " . $this->toString() . "\n";

        // new sum to be returned
        $sum = new Sum();

        // if the sum does not have any expressions or a single expression, return empty sum
        if (count($this->exprs) <= 1) return $sum;

        // if a term appears twice in the sum, simplify it - (x + x + y div 2) = x + (y div 2)
        $duplicate_expressions = $this->duplicate_expressions();
        if ($duplicate_expressions) {
            $expr_x = $this->exprs[$duplicate_expressions[0]]->copy();
            $sum_y = new Sum();
            for ($i = 0; $i < count($this->exprs); $i++) if ($i != $duplicate_expressions[0] && $i != $duplicate_expressions[1]) $sum_y->add($this->exprs[$i]->copy());
            // debug:
            echo "duplicate expression found in div - calculating " . $expr_x->toString() . ' + (' . $sum_y->toString() . ' div 2)' . "\n";
            $sum = $sum_y->div();
            $sum->add($expr_x);
            echo "duplicate expression div result of " . $this->toString() . ": " . $sum->toString() . "\n";
            return $sum;
        }

        // first, convert all expressions to sums
        $this->convert_expressions_to_sums();
        echo "Converted expressions to sums: " . $this->toString() . "\n";

        // check to make sure that all expressions consist of a single term
        foreach ($this->exprs as $myexpr) if (count($myexpr->terms) != 1) throw new Exception('Expression not single term after conversion to sum: ' . $this->toString());

        // determine the split variable if not given - most frequently used variable
        if (!$split_var) $split_var = $this->most_commonly_used_variable();
        // debug:
        echo "Most commonly used variable: " . $split_var->toString() . "\n";

        // standard and negated forms of the variable => f(x) = xa + x'b + c
        $x = new Boolean($split_var);
        $x_negated = $x->negate();

        // functions we will get as a result of the variable split => f(x) = xa + x'b + c
        $sum_a = new Sum();
        $sum_b = new Sum();
        $sum_c = new Sum();

        // loop through the terms and split based on variable
        foreach ($this->exprs as $myexpr) {
            // debug:
            echo "checking expr: " . $myexpr->toString() . "\n";
            if ($myexpr->terms[0]->has_boolean($x)) $sum_a->exprs[] = new BinaryExpression(array($myexpr->terms[0]->remove_variable($x)));
            elseif ($myexpr->terms[0]->has_boolean($x_negated)) $sum_b->exprs[] = new BinaryExpression(array($myexpr->terms[0]->remove_variable($x_negated)));
            else $sum_c->exprs[] = new BinaryExpression(array($myexpr->terms[0]->copy()));
        }

        // debug:
        echo "Sum a: " . $sum_a->toString() . "\n";
        echo "Sum b: " . $sum_b->toString() . "\n";
        echo "Sum c: " . $sum_c->toString() . "\n";

        // if there are no terms in sum a, it means variable x did not occur at all
        if (count($sum_a->exprs) == 0) $sum_a_type = 'zero';
        // if there is only a single term with no variables in it, that means the split variable occurred by itself - in that case, a = 1 and a' = 0
        elseif (count($sum_a->exprs) == 1 && count($sum_a->exprs[0]->terms) == 1 && count($sum_a->exprs[0]->terms[0]->vars) == 0) $sum_a_type = 'one';
        // otherwise x occurred with some variables along with x
        else $sum_a_type = 'expr';

        // if there are no terms in sum b, it means variable x' did not occur at all
        if (count($sum_b->exprs) == 0) $sum_b_type = 'zero';
        // if there is only a single term with no variables in it, that means x' occurred by itself - in that case, b = 1 and b' = 0
        elseif (count($sum_b->exprs) == 1 && count($sum_b->exprs[0]->terms) == 1 && count($sum_b->exprs[0]->terms[0]->vars) == 0) $sum_b_type = 'one';
        // otherwise x occurred with some variables along with x
        else $sum_b_type = 'expr';

        // if there are no terms in sum c, it means all terms had x or x'
        if (count($sum_c->exprs) > 0) $sum_c_type = 'expr';
        else $sum_c_type = 'zero';

        // debug:
        echo "Sum a type: " . $sum_a_type . "\n";
        echo "Sum b type: " . $sum_b_type . "\n";
        echo "Sum c type: " . $sum_c_type . "\n";

        // if A = 0 and B = 0 and C = 0, sum = 0 but that should not happen - it's handled above
        if ($sum_a_type == 'zero' && $sum_b_type == 'zero' && $sum_c_type == 'zero') throw new Exception('All sums zero - should not reach this code.');

        // do recursive mods and and divs if needed
        if ($sum_a_type == 'expr') { $mod_a = $sum_a->mod(); $div_a = $sum_a->div(); }
        if ($sum_b_type == 'expr') { $mod_b = $sum_b->mod(); $div_b = $sum_b->div(); }
        if ($sum_c_type == 'expr') { $mod_c = $sum_c->mod(); $div_c = $sum_c->div(); }

        // if C = 0, div = x * div_A + x' * div_B
        if ($sum_c_type == 'zero') {

            // if A = 0 and B = 1 (e.g. x'), div = 0
            if ($sum_a_type == 'zero' && $sum_b_type == 'one') {
                // add nothing
            }
            // if A = 0 and B = expr (e.g. x'B), div = x' * div_B
            elseif ($sum_a_type == 'zero' && $sum_b_type == 'expr') {
                $sum->add_sum($div_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = 1 and B = 0 (e.g. x), div = 0
            elseif ($sum_a_type == 'one' && $sum_b_type == 'zero') {
                // add nothing
            }
            // if A = 1 and B = 1 (e.g. x + x'), div = 0
            elseif ($sum_a_type == 'one' && $sum_b_type == 'one') {
                // add nothing
            }
            // if A = 1 and B = expr (e.g. x + x'B), div = x' * div_B
            elseif ($sum_a_type == 'one' && $sum_b_type == 'expr') {
                $sum->add_sum($div_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = 0, div = x * div_A
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'zero') {
                $sum->add_sum($div_a->multiply(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = 1, div = x * div_A
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'one') {
                $sum->add_sum($div_a->multiply(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = expr, div = x * div_A + x' * div_B
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'expr') {
                $sum->add_sum($div_a->multiply(new BinaryExpression(array(new Term(array($x))))));
                $sum->add_sum($div_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            else {
                throw new Exception('Unknown condition - should not have reached here.');
            }
        }
        // if C != 0, div = x * div_A + x' * div_B + div_C + x * mod_A * mod_C + x' * mod_B * mod_C
        else {

            // if A = 0 and B = 0, mod = C but that should not happen - we could not find the split variable?
            if ($sum_a_type == 'zero' && $sum_b_type == 'zero') throw new Exception('Expr C found to be zero - does not make sense.');

            // if A = 0 and B = 1 (e.g. x' + C), div = div_C + x' * mod_C
            if ($sum_a_type == 'zero' && $sum_b_type == 'one') {
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = 0 and B = expr (e.g. x'B + C), div = x' * div_B + div_C + x' * mod_B * mod_C
            elseif ($sum_a_type == 'zero' && $sum_b_type == 'expr') {
                $sum->add_sum($div_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr($mod_b)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = 1 and B = 0 (e.g. x + C), div = div_C + x * mod_C
            elseif ($sum_a_type == 'one' && $sum_b_type == 'zero') {
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = 1 and B = 1 (e.g. x + x' + C), div = div_C + mod_C
            elseif ($sum_a_type == 'one' && $sum_b_type == 'one') {
                $sum->add_sum($div_c);
                $sum->add($mod_c);
            }
            // if A = 1 and B = expr (e.g. x + x'B + C), div = x' * div_B + div_C + x * mod_C + x' * mod_B * mod_C
            elseif ($sum_a_type == 'one' && $sum_b_type == 'expr') {
                $sum->add_sum($div_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $sum->add($mod_c->and_expr($mod_b)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = 0 (e.g. xA + C), div = x * div_A + div_C + x * mod_A * mod_C
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'zero') {
                $sum->add_sum($div_a->multiply(new BinaryExpression(array(new Term(array($x))))));
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr($mod_a)->and_expr(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = 1 (e.g. xA + x' + C), div = x * div_A + div_C + x * mod_A * mod_C + x' * mod_C
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'one') {
                $sum->add_sum($div_a->multiply(new BinaryExpression(array(new Term(array($x))))));
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr($mod_a)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $sum->add($mod_c->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = expr (e.g. xA + x'B + C), div = x * div_A + x' * div_B + div_C + x * mod_A * mod_C + x' * mod_B * mod_C
            elseif ($sum_a_type == 'expr' && $sum_b_type == 'expr') {
                $sum->add_sum($div_a->multiply(new BinaryExpression(array(new Term(array($x))))));
                $sum->add_sum($div_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
                $sum->add_sum($div_c);
                $sum->add($mod_c->and_expr($mod_a)->and_expr(new BinaryExpression(array(new Term(array($x))))));
                $sum->add($mod_c->and_expr($mod_b)->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            else {
                throw new Exception('Unknown condition - should not have reached here.');
            }
        }

        // debug: echo "Div before simplification: " . $sum->toString() . "\n";
        $sum->simplify()->unify()->merge_terms();
        // debug: echo "Div after simplification " . $this->toString() . ": " . $sum->toString() . "\n";

        // debug: if (count($this->terms) == 3) exit;

        // return the new sum
        return $sum;
	}

    /*
     * takes div 2 of an expression - returns a regular expression (not binary expression) - alternative implementation
    */
    public function div_alt1() {

        // debug: echo "Taking div 2 of " . $this->toString() . "\n";

        // apply simplifications before div
        $this->simplify()->unify()->merge_terms();
        // debug: echo 'after simplifications: ' . $this->toString() . "\n";

        // if a term appears twice in the sum, simplify it - (x + x + y div 2) = x + (y div 2)
        $duplicate_expressions = $this->duplicate_expressions();
        if ($duplicate_expressions) {
            $expr_x = $this->exprs[$duplicate_expressions[0]]->copy();
            $sum_y = new Sum();
            for ($i = 0; $i < count($this->exprs); $i++) if ($i != $duplicate_expressions[0] && $i != $duplicate_expressions[1]) $sum_y->add($this->exprs[$i]->copy());
            // debug:
            echo "duplicate expression found in div - calculating " . $expr_x->toString() . ' + (' . $sum_y->toString() . ' div 2)' . "\n";
            $sum = $sum_y->div();
            $sum->add($expr_x);
            echo "duplicate expression div result of " . $this->toString() . ": " . $sum->toString() . "\n";
            return $sum;
        }

        // if we have no expressions or only one expression, nothing to do - return empty expression (zero)
        if (count($this->exprs) < 2) return new Sum();

        // if we have only 2 binary expressions they must both be one - we can simply multiply them
        if (count($this->exprs) == 2) {
            // debug: echo '2 expr div: ' . $this->toString() . "\n";
            $binary_expr1 = $this->exprs[0]->copy();
            $binary_expr2 = $this->exprs[1]->copy();
            $binary_expr = $binary_expr1->and_expr($binary_expr2)->simplify()->unify();
            $sum = new Sum(array($binary_expr));
            // debug: echo '2 expr div result: ' . $sum->toString() . "\n";
            return $sum;
        }

        // if we have more than 2 terms, calculate the result recursively - where A is a decimal and B is binary:
        // (A + B div 2) = (A div 2) + ((A mod 2) and (B mod 2))
        // debug:
        echo 'multi-term div: ' . $this->toString() . "\n";
        $sum_a = new Sum(array_slice($this->exprs,1));
        // debug:
        echo "Sum A: " . $sum_a->toString() . "\n";
        $expr_a = $sum_a->mod();
        // debug:
        echo "Sum A mod 2: " . $expr_a->toString() . "\n";
        $expr_b = $this->exprs[0]->copy();
        // debug:
        echo "Expr B (already mod 2): " . $expr_b->toString() . "\n";
        $expr = $expr_a->and_expr($expr_b)->simplify()->unify()->merge_terms();
        // debug:
        echo "multi-term div mod part: " . $expr->toString() . "\n";
        $sum = $sum_a->div();
        // debug:
        echo "multi-term div div part: " . $sum->toString() . "\n";
        $sum->add($expr);
        $sum->simplify()->unify()->merge_terms();
        $sum->simplify()->unify()->merge_terms();
        // debug:
        echo "multi-term div result of " . $this->toString() . ": " . $sum->toString() . "\n";
        return $sum;
    }

    /*
     * multiplies with a binary expression
     */
	public function multiply($expr) { 
		
		// new sum to be returned 
		$newsum = new Sum();
		
		// go through this expressions and "and" them
		for ($i = 0; $i < count($this->exprs); $i++) $newsum->exprs[] = $this->exprs[$i]->and_expr($expr);
		
		// return the merged sum
		return $newsum; 
	}

    /*
     * multiplies with another sum
     */
    public function multiply_sum($sum) {

        // new sum to be returned
        $newsum = new Sum();

        // go through the expressions and multipy each one of them
        for ($i = 0; $i < count($this->exprs); $i++) $newsum->add_sum($sum->multiply($this->exprs[$i]));

        // return the new sum
        return $newsum;
    }

    /*
     * go through all expressions and simplify as needed
    */
	public function simplify() {
	
		// simplify - remove the conflicting expressions 
		$exprs = array();
		for ($i = 0; $i < count($this->exprs); $i++) {

			// simplify expression and make it zero if there are conflicting values
			$this->exprs[$i]->simplify();

			// if the expression became zero, we can ignore it now  
			if ($this->exprs[$i]->zero()) continue; 

			// keep the expression 
			$exprs[] = $this->exprs[$i];
		}
		$this->exprs = $exprs;
	
		// return self
		return $this;
	}
	
}

