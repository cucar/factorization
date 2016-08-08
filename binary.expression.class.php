<?php

require_once 'term.class.php';

/*
 * binary expression - contains a sequence of binary terms OR'ed to one another (not regular addition)
 * each term is guaranteed to be zero or one  
*/
class BinaryExpression {

	// terms array 
	public $terms = array();

	/*
	 * constructor - terms in the expression 
	*/
	public function __construct($terms = array()) {
		foreach ($terms as $term) $this->terms[] = $term->binary();
	}

	/* 
	 * add a new terms to the expression (or) 
	 */
	public function add($expr) {
		foreach ($expr->terms as $term) $this->terms[] = $term->binary();
	}

    /*
     * add a new term to the expression (or)
     */
    public function add_term($term) {
        $this->terms[] = $term->binary();
    }

    /*
     * returns the variables in the expression
     */
	public function vars() { 
		$vars = array();
		foreach ($this->terms as $term) foreach ($term->vars as $var) if (!in_array($var->var, $vars)) $vars[] = $var->var;
		return $vars;
	}
	
	/* 
	 * returns the expression as a string 
	 */
	public function toString() { 
		// if there are no terms, return 0 
		if (count($this->terms) == 0) return '0 Expr';
		$retval = '';
		for ($i = 0; $i < count($this->terms); $i++) { 
			$retval .= $this->terms[$i]->toString() . ($i != count($this->terms)-1 ? ' V ' : '');
		}
		return '(' . $retval . ')';
	}

	/* 
	 * copy current expression 
	 */
	public function copy() { 
		return new BinaryExpression(array_map(function($term) { return $term->copy(); }, $this->terms)); 
	}
	
	/* 
	 * apply a variable with a value/expression and return the resulting expression/value 
	 */
	public function apply($var, $val) { 
		
		// new expression to be returned 
		$expr = $this->copy();
		
		// terms applications are not implemented yet 
		if (!is_a($var, 'Boolean') && !is_a($var, 'Variable')) throw new Exception('Term applications not implemented');
		
		// expression applications are not implemented yet 
		if (is_object($val)) throw new Exception('Expression applications not implemented');
		
		// go through the terms and apply variable in those places with the given value 
		return $expr->apply_var($var, $val);
	}

	/* 
	 * applies a zero product value to the expression  
	 */
	public function apply_zero_product(Term $zero_product) { 
		
		// echo "Applying zero product " . $zero_product->toString() . "\n";
		
		// go through the terms and apply the zero product in each of them 
		for ($i = 0; $i < count($this->terms); $i++) $this->terms[$i]->apply_zero_product($zero_product);
		
		// simplify the expression 
		$this->simplify()->unify()->merge_terms();
		
		// debug: echo "Expression after application: " . $this->toString() . "\n";
		
		// return self for further applications 
		return $this;
	}
	
	/* 
	 * applies a variable value to the expression - simplest application  
	 */
	public function apply_var($var, $val) { 
		
		// echo "Applying " . $var->toString() . ' = ' . $val . "\n";
		
		// go through the terms and apply the variable in each of them 
		for ($i = 0; $i < count($this->terms); $i++) $this->terms[$i]->apply_var($var, $val);

        // simplify the expression
		$this->simplify()->unify()->merge_terms();

        // echo "Expression after application: " . $this->toString() . "\n";
		
		// return self for further applications 
		return $this;
	}
	
	/* 
	 * evaluate expression - only works if all the terms are valuated 
	 */
	public function evaluate() { 
		
		// go through all the terms and use their values - if we see a one, it means the entire expression is one - ignore zeros 
		foreach ($this->terms as $term) { 
			
			// if we see a term without a value, this won't work 
			if ($term->val === null) throw new Exception('Term without value in eval');
			
			// if the term value is one, the entire expression becomes one 
			if ($term->val == '1') return 1;
			
			// otherwise it looks like we hit zero value - it's to be ignored 
			if ($term->val == '0') continue; 
		}

		// if all the terms in the expression evaluated to zero, the expression is zero 
		return 0; 
	}

	/* 
	 * checks if the expression evaluates to zero 
	 */
	public function zero() { 
		
		// go through all the terms and use their values - if we see a one, it means the entire expression is one - ignore zeros 
		foreach ($this->terms as $term) { 
			
			// if we see a term without a value, it's not zero 
			if ($term->val === null) return false; 
			
			// if the term value is one, the entire expression becomes one, so it's not zero  
			if ($term->val == '1') return false;
			
			// otherwise it looks like we hit zero value - it's to be ignored 
			if ($term->val == '0') continue; 
		}

		// if all the terms in the expression evaluated to zero, the expression is zero 
		return true; 
	}

	/* 
	 * returns the most commonly used variable in the expression 
	 */
	protected function most_commonly_used_variable() {

		// determine the number of times each variable is used
		$var_usages = array();
		$var_index = array(); 
		foreach ($this->terms as $term) {  
			foreach ($term->vars as $var) {
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

        // if there are no variables, error out
        if (count($var_index) == 0) throw new Exception('No variables found in expr: ' . print_r($this, true));
		
		// return the most commonly used variable
		reset($var_usages);
		return $var_index[key($var_usages)];
	}
	
	/* 
	 * negates the expression - negate all terms and "and" them 
	 */
	public function negate() { 
		
		// if the expression does not have any terms, it's basically a value of 1  
		if (count($this->terms) == 0) return new BinaryExpression(array(new Term(array(), 1)));

        // if the expression has a single 1 term, return empty expression
        if (count($this->terms) == 1 && count($this->terms[0]->vars) == 0 && $this->terms[0]->val == 1) return new BinaryExpression();

        // debug: echo "\n\nNegating expression: " . $this->toString() . "\n";
		
		// determine the split variable - most frequently used variable
		$split_var = $this->most_commonly_used_variable();
		// debug: echo "Most commonly used variable: " . $split_var->toString() . "\n";   
		
		// standard and negated forms of the variable => f(x) = xa + x'b + c
		$x = new Boolean($split_var);
		$x_negated = $x->negate();
		
		// functions we will get as a result of the variable split => f(x) = xa + x'b + c  
		$expr_a = new BinaryExpression();
		$expr_b = new BinaryExpression();
		$expr_c = new BinaryExpression();

		// loop through the terms and split based on variable  
		foreach ($this->terms as $term) {
			// debug: echo "checking term: " . $term->toString() . "\n"; 
			if ($term->has_boolean($x)) $expr_a->terms[] = $term->remove_variable($x);
			elseif ($term->has_boolean($x_negated)) $expr_b->terms[] = $term->remove_variable($x_negated);
			else $expr_c->terms[] = $term->copy();
		}
		
		// debug: echo "Expr a: " . $expr_a->toString() . "\n"; echo "Expr b: " . $expr_b->toString() . "\n"; echo "Expr c: " . $expr_c->toString() . "\n";
		
		// if there are no terms in expression a, it means variable x did not occur at all
		if (count($expr_a->terms) == 0) $expr_a_type = 'zero';
		// if there is only a single term with no variables in it, that means the split variable occurred by itself - in that case, a = 1 and a' = 0 
		elseif (count($expr_a->terms) == 1 && count($expr_a->terms[0]->vars) == 0) $expr_a_type = 'one';
		// otherwise x occurred with some variables along with x  
		else $expr_a_type = 'expr';
		
		// if there are no terms in expression b, it means variable x' did not occur at all
		if (count($expr_b->terms) == 0) $expr_b_type = 'zero';
		// if there is only a single term with no variables in it, that means x' occurred by itself - in that case, b = 1 and b' = 0
		elseif (count($expr_b->terms) == 1 && count($expr_b->terms[0]->vars) == 0) $expr_b_type = 'one';
		// otherwise x' occurred with some variables along with x'
		else $expr_b_type = 'expr';

		// debug: echo "Expr a type: " . $expr_a_type . "\n"; echo "Expr b type: " . $expr_b_type . "\n";
		
		// calculate negations if needed 
		if ($expr_a_type == 'expr') { 
			$expr_a_negated = $expr_a->negate();
			// debug: echo "Negated expr a: " . $expr_a_negated->toString() . "\n";
		}
		if ($expr_b_type == 'expr') {
			$expr_b_negated = $expr_b->negate();
			// debug: echo "Negated expr b: " . $expr_b_negated->toString() . "\n";
		}
		
		// now calculate the negated expression => f'(x) = (xa' + x'b' + a'b')c'  
		$new_expr = new BinaryExpression();
		
		// forget about c for now - calculate the negation with a and b - we will just "and" it with c'
		// (xa + x'b)' = xa' + x'b' + a'b'  
		if ($expr_a_type == 'expr' && $expr_b_type == 'expr') {
			$expr_xa = $expr_a_negated->and_expr(new BinaryExpression(array(new Term(array($x)))));
			// debug: echo "Expression xa' = " . $expr_xa->toString() . "\n"; 
			$new_expr->add($expr_xa);
			$expr_xb = $expr_b_negated->and_expr(new BinaryExpression(array(new Term(array($x_negated)))));
			// debug: echo "Expression x'b' = " . $expr_xb->toString() . "\n";
			$new_expr->add($expr_xb);
			$expr_ab = $expr_a_negated->and_expr($expr_b_negated);
			// debug: echo "Expression a'b' = " . $expr_ab->toString() . "\n";
			$new_expr->add($expr_ab);
			// debug: echo "xa' + x'b' + a'b' = " . $new_expr->toString() . "\n";
			$new_expr->simplify()->unify()->merge_terms();
			// debug: echo "xa' + x'b' + a'b' (simplified) = " . $new_expr->toString() . "\n";
		}
		// (xa + x')' = xa'
		elseif ($expr_a_type == 'expr' && $expr_b_type == 'one') {
			$new_expr->add($expr_a_negated->and_expr(new BinaryExpression(array(new Term(array($x))))));
		}  
		// (xa)' = x' + a'
		elseif ($expr_a_type == 'expr' && $expr_b_type == 'zero') {
			$new_expr->add(new BinaryExpression(array(new Term(array($x_negated)))));
			$new_expr->add($expr_a_negated);
		}
		// (x + x'b)' = x'b'
		elseif ($expr_a_type == 'one' && $expr_b_type == 'expr') {
			$new_expr->add($expr_b_negated->and_expr(new BinaryExpression(array(new Term(array($x_negated))))));
		}
		// (x + x')' = 0 
		elseif ($expr_a_type == 'one' && $expr_b_type == 'one') {
			$new_expr->terms[] = new Term(array(), 0);
		}
		// (x)' = x'
		elseif ($expr_a_type == 'one' && $expr_b_type == 'zero') {
			$new_expr->terms[] = new Term(array($x_negated));
		}
		// (x'b)' = x + b'
		elseif ($expr_a_type == 'zero' && $expr_b_type == 'expr') {
			$new_expr->add(new BinaryExpression(array(new Term(array($x)))));
			$new_expr->add($expr_b_negated);
		}
		// (x')' = x
		elseif ($expr_a_type == 'zero' && $expr_b_type == 'one') {
			$new_expr->terms[] = new Term(array($x));
		}
		// (0)' = 1
		elseif ($expr_a_type == 'zero' && $expr_b_type == 'zero') {
			$new_expr->terms[] = new Term(array(), 1);
		}
		
		// now calculate the negated expression for c and apply it if needed
		if (count($expr_c->terms) > 0) { 
			$expr_c_negated = $expr_c->negate();
			// debug: echo "c' = " . $expr_c_negated->toString() . "\n";
			// debug: echo "xa' + x'b' + a'b' = " . $new_expr->toString() . "\n";
			$new_expr = $new_expr->and_expr($expr_c_negated);
			// debug: echo "(xa' + x'b' + a'b')c' = " . $new_expr->toString() . "\n";
		}
		
		// debug: echo "Negated expression before simplification: " . $new_expr->toString() . "\n";
		$new_expr->simplify()->unify()->merge_terms();
		// debug: echo "Negated expression " . $this->toString() . ": " . $new_expr->toString() . "\n";
		
		// debug: if (count($this->terms) == 3) exit;
		
		// return the new negated expression  
		return $new_expr;
	}
	
	/* 
	 * negates the expression - negate all terms and "and" them - alternative implementation - better than alt 1   
	 */
	public function negate_alt2() { 
		
		// if the expression does not have any terms, it's basically a value of 1  
		if (count($this->terms) == 0) return new BinaryExpression(array(new Term(array(), 1)));
		
		// go through the terms and negate each one and "and" them 
		$expr = $this->terms[0]->negate();
		// debug: echo 'First negated term: ' . $expr->toString() . "\n";
		for ($i = 1; $i < count($this->terms); $i++) {
			// debug: echo 'negating term ' . $i . ' ' . $this->terms[$i]->toString() . "\n";
			$negexpr = $this->terms[$i]->negate(); 
			// debug: echo 'negated term ' . $i . ' ' . $negexpr->toString() . " - applying to expression\n";
			$expr = $expr->and_expr($negexpr);
			// debug: echo 'applied to expression' . "\n";
		}
		
		// return the new negated expression  
		return $expr;
	}
	
	/* 
	 * negates the expression - negate all terms and "and" them - alternative implementation - should take less time but it seems to be slower  
	 */
	public function negate_alt1() { 
		
		// if the expression does not have any terms, it's basically a value of 1  
		if (count($this->terms) == 0) return new BinaryExpression(array(new Term(array(), 1)));

		// debug: 
		// echo "\n\nNegating expression: " . $this->toString() . "\n";
		
		// the new expression we will be returning - start with empty expression - stands for 1 
		$expr = new BinaryExpression();
		
		// initialize the index for the terms we added to the expression 
		$expr_index = array();
		
		// term count - this determines the loop counts  
		$source_term_count = count($this->terms);
		
		// loop through every every term of the expression as source 
		for ($source_term_index = 0; $source_term_index < $source_term_count; $source_term_index++) { 

			// source term 
			$source_term = $this->terms[$source_term_index];
						
			// variable count in the source term 
			$source_term_var_count = count($source_term->vars);
			
			// echo "working on term: " . $source_term->toString() . "\n";
			
			// term count in target expression 
			$target_term_count = count($expr->terms);
					
			// loop through every variable of the source term 
			for ($source_var_index = 0; $source_var_index < $source_term_var_count; $source_var_index++) {
				
				// source variable - negated  
				$source_var = $source_term->vars[$source_var_index]->negate();
				
				// echo "applying negated variable: " . $source_var->toString() . "\n";
			
				// if this is the initial term, just negate and copy it onto the new expression
				if ($source_term_index == 0) {
					$expr->terms[] = new Term(array($source_var));
				}
				// otherwise "and" the negated variable with every term in the target expression 
				else {  
				
					// loop through every term in the target expression  
					for ($target_term_index = 0; $target_term_index < $target_term_count; $target_term_index++) {
						
						// target term 
						$target_term = $expr->terms[$target_term_index];
						
						// variable count in the target term 
						$target_term_var_count = count($target_term->vars);
						
						// loop through the variables of the target term and build the new term 
						$new_term = new Term(array($source_var));
						for ($target_var_index = 0; $target_var_index < $target_term_var_count; $target_var_index++) {

							// target variable  
							$target_var = $target_term->vars[$target_var_index];

							// if we see the same variable in the target term, don't add it again  
							if ($target_var->equals($source_var)) continue; 

							// if we see the negated variable in the target term, it means the term is nullified - nothing to do
							if ($target_var->equalsNegated($source_var)) { $new_term->vars = array(); break; }
								
							// add the target variable to the new term 
							$new_term->vars[] = $target_var;
						}
						
						// add the new term to the expression if it's not nullified    
						if ($new_term->vars) $expr->terms[] = $new_term; 
					}
				}
				
				// echo "expression after variable is applied: " . $expr->toString() . "\n";
			}
			
			// echo "expression after term is applied (before deletions): " . $expr->toString() . "\n";
				
			// now delete the original terms we used for multiplication and re-index 
			$expr->terms = array_slice($expr->terms, $target_term_count);
			
			// echo "expression after term is applied (after deletions): " . $expr->toString() . "\n";
		}
		
		// debug:
		// echo "Negated expression: " . $expr->toString() . "\n";
		
		$expr->simplify()->unify()->merge_terms();
		
		// 	if (count($this->terms) == 3) exit;
		
		// return the new negated expression  
		return $expr;
	}
	
	/* 
	 * "and"s another expression (like multiplication) 
	 */
	public function and_expr($expr) { 
		
		// new expression to be returned 
		$newexpr = $this->copy();
		
		// go through this expression terms and merge them with each of the other expression terms
		$terms = array(); 
		for ($i = 0; $i < count($newexpr->terms); $i++) {  
			for ($j = 0; $j < count($expr->terms); $j++) {
				$terms[] = $this->terms[$i]->copy()->and_term($expr->terms[$j]);
			}
		}
		
		// the merged expression 
		$expr = new BinaryExpression($terms);
		
		// simplify the expression 
		$expr->simplify()->unify()->merge_terms();
		
		// return the merged expression 
		return $expr;
	}

	/* 
	 * go through all terms and simplify as needed 
	 */
	public function simplify() { 

		// simplify - remove the conflicting terms
		$terms = array();  
		for ($i = 0; $i < count($this->terms); $i++) {
            $this->terms[$i]->simplify();
			if ($this->terms[$i]->val === null || $this->terms[$i]->val == 1) $terms[] = $this->terms[$i];
		}
		$this->terms = $terms;

		// return self 
		return $this;
	}
	
	/*
	 * go through all terms and keep them unique
	*/
	public function unify() {
	
		// unify each term variables - keep only one of multiple identical variables  
		for ($i = 0; $i < count($this->terms); $i++) $this->terms[$i]->unify();

		// unify - remove the identical terms (keep one)
		$terms = array();
		for ($i = 0; $i < count($this->terms); $i++) {
		
			// check if the term appears elsewhere 
			$duplicate_term = false; 
			for ($j = 0; $j < count($terms); $j++) if ($i != $j && $this->terms[$i]->equals($terms[$j])) { $duplicate_term = true; break; }
			
			// do not include duplicate terms 
			if (!$duplicate_term) $terms[] = $this->terms[$i];
		}
		$this->terms = $terms;
		
		// return self
		return $this;
	}
	
	/*
	 * go through all terms and check if they can be merged 
	*/
	public function merge_terms() {
	
		// sort the terms by their variable counts 
		$term_var_counts = array_map(function($term) { return count($term->vars); }, $this->terms);
		asort($term_var_counts); 
		$new_terms = array_map(function($term_index) { return $this->terms[$term_index]; }, array_keys($term_var_counts));
		$this->terms = $new_terms;
		
		// check each term and see if it can be merged 
		$terms = array();
		for ($i = 0; $i < count($this->terms); $i++) {
			
			// debug: echo "checking merge for term: " . $this->terms[$i]->toString() . "\n"; 
			
			// check if the term can be merged with another one - if not, simply keep it 
			$merged_term = false; 
			for ($j = 0; $j < count($terms); $j++) {

				// debug: echo "checking merge with term: " . $terms[$j]->toString() . "\n";
				
				if ($terms[$j]->merge($this->terms[$i])) { 
					$merged_term = true; break; 
				}
			}
			
			// if the term could not be merged, copy as-is
			if (!$merged_term) $terms[] = $this->terms[$i];
		}
		$this->terms = $terms;
	
		// return self
		return $this;
	}
	
	/* 
	 * check if the expression is identical to another one 
	 */
	public function equals($expr) { 
		
		// check the object type 
		if (!is_object($expr) || !is_a($expr, 'BinaryExpression')) return false; 
		
		// for the expressions to be equal, they have to have the same number of terms 
		if (count($this->terms) != count($expr->terms)) return false; 
		
		// loop through each term and see if it exists on the other expression 
		foreach ($this->terms as $term) {
			// debug: echo 'checking if term ' . $term->toString() . ' exists in expression' . "\n";
			$term_exists = false; 
			foreach ($expr->terms as $otherterm) { 
				if ($otherterm->equals($term)) { 
					$term_exists = true;
					// debug: echo 'Term exists' . "\n"; 
					break; 
				}
			}
			
			if (!$term_exists) { 
				// debug: echo 'Term does not exist' . "\n";
				return false; 
			}
		}
		
		// all terms exist and the number of terms is the same - expressions are identical
		// debug: echo 'Identical expressions' . "\n"; 
		return true; 
	}

	/* 
	 * applies deductions to the expression 
	 */
	public function apply_deductions($deductions) { 
		
		// debug: 
		echo "Applying deductions for " . $this->toString() . "\n";
		
		// go through the deductions and apply them one by one 
		foreach ($deductions as $deduction) { 
			
			// debug: echo "checking deduction: " . $deduction[0]->toString() . ' = ' . (method_exists($deduction[1], 'toString') ? $deduction[1]->toString() : $deduction[1]) . "\n";
			
			// zero product application 
			if (is_a($deduction[0], 'Term')) { 
				// debug: 
				echo "Applying zero product deduction for " . $deduction[0]->toString() . ' = ' . (method_exists($deduction[1], 'toString') ? $deduction[1]->toString() : $deduction[1]) . "\n";
				$this->apply_zero_product($deduction[0]);
				continue; 
			}

			// expression application
			if (is_object($deduction[1]) && !is_a($deduction[1], 'Boolean') && !is_a($deduction[1], 'Variable')) {
				// debug: echo "Applying expression deduction for " . $deduction[0]->toString() . ' = ' . $deduction[1]->toString() . "\n";
				$this->apply_var_expr($deduction[0], $deduction[1]);
				continue; 
			}
			
			// variable replace 
			if (is_object($deduction[1])) { 
				$this->apply_var_replace($deduction[0], $deduction[1]);
				continue;
			}
				
			// simple deduction application 
			$this->apply_var($deduction[0], $deduction[1]);
		}
		// debug: echo 'Deductions applied: ' . $this->toString() . "\n";
	}
	
	/*
	 * applies a variable replace to the expression - relatively simpler application
	*/
	public function apply_var_replace($oldvar, $newvar) {
	
		// echo "Applying variable replace: " . $oldvar->toString() . ' = ' . $newvar->toString() . "\n";
		
		// go through the terms and replace the variable in each of them
		for ($i = 0; $i < count($this->terms); $i++) $this->terms[$i]->apply_var_replace($oldvar, $newvar);
	
		// now simplify, unify and merge terms 
		$this->simplify()->unify()->merge_terms();
		
		// echo "Expression after variable replace: " . $this->toString() . "\n";
		
		// return self for further applications
		return $this;
	}
	
	/*
	 * applies a deduction of type variable = expression to the expression
	*/
	public function apply_var_expr(Variable $var, BinaryExpression $expr) {
	
		// debug: 
		echo "Applying variable expression: " . $var->toString() . ' = ' . $expr->toString() . "\n";
		
		// simplify to make sure we won't hit any cases where we see the positive and negative of the same variable in the same term 
		$this->simplify()->unify()->merge_terms();
		
		// go through the terms and replace the variable in each of them
		$delete_terms = array();
		$original_term_count = count($this->terms); 
		for ($i = 0; $i < $original_term_count; $i++) { 

			// we found the variable as-is (not negated) 
			if ($this->terms[$i]->has_boolean($var)) {
			
				// debug: 
				echo "Found " . $var->toString() . " in term " . $this->terms[$i]->toString() . "\n";
				
				// this term is being replaced - remove it in a separate loop to not mess with this cycle 
				$delete_terms[] = $i; 
				
				// get a new term without that variable 
				$newterm = $this->terms[$i]->remove_variable($var);
				// debug: echo "Applying the new term " . $newterm->toString() . "\n";

				// if the term had additional variables, and them in the expression
				$newexpr = $expr->copy();
				if (count($newterm->vars) > 0) $newexpr = $newexpr->and_expr(new BinaryExpression(array($newterm))); 
				$newexpr->simplify()->unify()->merge_terms();
				
				// now add the new expression to this one 
				$this->add($newexpr);
				
				// so that we can know that we need to simplify 
				$replacement_made = true;
			}
			// apply the negated variable if it exists 
			elseif ($this->terms[$i]->has_boolean((new Boolean($var))->negate())) {
				
				// debug: echo "Found " . $var->toString() . " as negated in term " . $this->terms[$i]->toString() . "\n";
				
				// this term is being replaced - remove it in a separate loop to not mess with this cycle
				$delete_terms[] = $i;
				
				// get a new term without that variable
				$newterm = $this->terms[$i]->remove_variable((new Boolean($var))->negate());
				// debug: echo "Applying the new term " . $newterm->toString() . "\n";
				
				// if the term had additional variables, and them in the expression
				// debug: echo "Original expression: " . $expr->toString() . "\n"; 
				$newexpr = $expr->negate();
				// debug: echo "Negated expression: " . $newexpr->toString() . "\n"; 
				if (count($newterm->vars) > 0) $newexpr = $newexpr->and_expr(new BinaryExpression(array($newterm)));
				// debug: echo "New expression after application: " . $newexpr->toString() . "\n"; 
				$newexpr->simplify()->unify()->merge_terms();
				// debug: echo "New expression after application (simplified): " . $newexpr->toString() . "\n"; exit;
				
				// now add the new expression to this one
				$this->add($newexpr);
				
				// so that we can know that we need to simplify
				$replacement_made = true;
			}
		}

		// simplify the expression if we made any updates 
		if ($delete_terms) { 
			
			// now delete the terms
			foreach ($delete_terms as $delete_term) unset($this->terms[$delete_term]);
			$this->terms = array_values($this->terms);

			// simplify the expression 
			$this->simplify()->unify()->merge_terms();
			$this->simplify()->unify()->merge_terms();
				
			// debug:
			echo "Expression after expression replace: " . $this->toString() . "\n";
		}
		
		// return self for further applications
		return $this;
	}

	/*
	 * converts the expression to sum of terms by doing xor (mod)
	*/
	public function convert_to_sum($split_var = false) {

        // new sum to be returned
        $sum = new Sum();

        // if the expression does not have any terms, return empty sum
        if (count($this->terms) == 0) return $sum;

        // if there is a single term, just return it as a sum
        if (count($this->terms) == 1) {
            $sum->add(new BinaryExpression(array($this->terms[0]->copy())));
            return $sum;
        }

        // debug: echo "\n\nConverting expression to sum: " . $this->toString() . "\n";

        // determine the split variable if not given - most frequently used variable
        if (!$split_var) $split_var = $this->most_commonly_used_variable();
        // debug: echo "Most commonly used variable: " . $split_var->toString() . "\n";

        // standard and negated forms of the variable => f(x) = xa + x'b + c
        $x = new Boolean($split_var);
        $x_negated = $x->negate();

        // functions we will get as a result of the variable split => f(x) = xa + x'b + c
        $expr_a = new BinaryExpression();
        $expr_b = new BinaryExpression();
        $expr_c = new BinaryExpression();

        // loop through the terms and split based on variable
        foreach ($this->terms as $term) {
            // debug: echo "checking term: " . $term->toString() . "\n";
            if ($term->has_boolean($x)) $expr_a->terms[] = $term->remove_variable($x);
            elseif ($term->has_boolean($x_negated)) $expr_b->terms[] = $term->remove_variable($x_negated);
            else $expr_c->terms[] = $term->copy();
        }

        // debug: echo "Expr a: " . $expr_a->toString() . "\n"; echo "Expr b: " . $expr_b->toString() . "\n"; echo "Expr c: " . $expr_c->toString() . "\n";

        // if there are no terms in expression a, it means variable x did not occur at all
        if (count($expr_a->terms) == 0) $expr_a_type = 'zero';
        // if there is only a single term with no variables in it, that means the split variable occurred by itself - in that case, a = 1 and a' = 0
        elseif (count($expr_a->terms) == 1 && count($expr_a->terms[0]->vars) == 0) $expr_a_type = 'one';
        // otherwise x occurred with some variables along with x
        else $expr_a_type = 'expr';

        // if there are no terms in expression b, it means variable x' did not occur at all
        if (count($expr_b->terms) == 0) $expr_b_type = 'zero';
        // if there is only a single term with no variables in it, that means x' occurred by itself - in that case, b = 1 and b' = 0
        elseif (count($expr_b->terms) == 1 && count($expr_b->terms[0]->vars) == 0) $expr_b_type = 'one';
        // otherwise x' occurred with some variables along with x'
        else $expr_b_type = 'expr';

        // if there are no terms in expression c, it means all terms had x or x'
        if (count($expr_c->terms) > 0) $expr_c_type = 'expr';
        else $expr_c_type = 'zero';

        // debug: echo "Expr a type: " . $expr_a_type . "\n"; echo "Expr b type: " . $expr_b_type . "\n"; echo "Expr c type: " . $expr_c_type . "\n";

        // if A = 0 and B = 0 and C = 0, sum = 0 but that should not happen - it's handled above
        if ($expr_a_type == 'zero' && $expr_b_type == 'zero' && $expr_c_type == 'zero') throw new Exception('All sums zero - should not reach this code.');

        //****************************************************************************************************************************************************************************************
        // first, calculate the part without C - xA + x'B
        //****************************************************************************************************************************************************************************************

        // if A = 0 and B = 1 (e.g. x'), sum = x'
        if ($expr_a_type == 'zero' && $expr_b_type == 'one') {
            $sum->add(new BinaryExpression(array(new Term(array($x_negated)))));
        }
        // if A = 0 and B = expr (e.g. x'B), sum = x'B
        elseif ($expr_a_type == 'zero' && $expr_b_type == 'expr') {
            $sum_b = $expr_b->convert_to_sum();
            $sum->add_sum($sum_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
        }
        // if A = 1 and B = 0 (e.g. x), sum = x
        elseif ($expr_a_type == 'one' && $expr_b_type == 'zero') {
            $sum->add(new BinaryExpression(array(new Term(array($x)))));
        }
        // if A = 1 and B = 1 (e.g. x + x'), sum = x + x' = 1
        elseif ($expr_a_type == 'one' && $expr_b_type == 'one') {
            $sum->add(new BinaryExpression(array(new Term(array(), 1))));
        }
        // if A = 1 and B = expr (e.g. x + x'B), sum = x + x'B
        elseif ($expr_a_type == 'one' && $expr_b_type == 'expr') {
            $sum_b = $expr_b->convert_to_sum();
            $sum->add(new BinaryExpression(array(new Term(array($x)))));
            $sum->add_sum($sum_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
        }
        // if A = expr and B = 0, sum = xA
        elseif ($expr_a_type == 'expr' && $expr_b_type == 'zero') {
            $sum_a = $expr_a->convert_to_sum();
            $sum->add_sum($sum_a->multiply(new BinaryExpression(array(new Term(array($x))))));
        }
        // if A = expr and B = 1, sum = xA + x'
        elseif ($expr_a_type == 'expr' && $expr_b_type == 'one') {
            $sum_a = $expr_a->convert_to_sum();
            $sum->add_sum($sum_a->multiply(new BinaryExpression(array(new Term(array($x))))));
            $sum->add(new BinaryExpression(array(new Term(array($x_negated)))));
        }
        // if A = expr and B = expr, sum = xA + x'B
        elseif ($expr_a_type == 'expr' && $expr_b_type == 'expr') {
            $sum_a = $expr_a->convert_to_sum();
            $sum_b = $expr_b->convert_to_sum();
            $sum->add_sum($sum_a->multiply(new BinaryExpression(array(new Term(array($x))))));
            $sum->add_sum($sum_b->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
        }
        else {
            throw new Exception('Unknown condition - should not have reached here.');
        }

        //****************************************************************************************************************************************************************************************
        // now, calculate the part for C if needed - xA' + x'B'
        //****************************************************************************************************************************************************************************************

        // if C != 0, sum = xA + x'B + C(xA' + x'B') - we already calculated xA + x'B above - now we calculate xA' + x'B' (sum c) and multiply it with C before adding to the main sum
        if ($expr_c_type == 'expr') {

            // calculate xA' + x'B'
            $sum_c = new Sum();

            // if A = 0 and B = 1 (e.g. x' + C), sum c = x
            if ($expr_a_type == 'zero' && $expr_b_type == 'one') {
                $sum_c->add(new BinaryExpression(array(new Term(array($x)))));
            }
            // if A = 0 and B = expr (e.g. x'B + C), sum c = x + x'B'
            elseif ($expr_a_type == 'zero' && $expr_b_type == 'expr') {
                $expr_b_negated = $expr_b->negate();
                $sum_b_negated = $expr_b_negated->convert_to_sum();
                $sum_c->add(new BinaryExpression(array(new Term(array($x)))));
                $sum_c->add_sum($sum_b_negated->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = 1 and B = 0 (e.g. x + C), sum c = x'
            elseif ($expr_a_type == 'one' && $expr_b_type == 'zero') {
                $sum_c->add(new BinaryExpression(array(new Term(array($x_negated)))));
            }
            // if A = 1 and B = 1 (e.g. x + x' + C), sum c = 0
            elseif ($expr_a_type == 'one' && $expr_b_type == 'one') {
                // nothing to add
            }
            // if A = 1 and B = expr (e.g. x + x'B + C), sum c = x'B'
            elseif ($expr_a_type == 'one' && $expr_b_type == 'expr') {
                $expr_b_negated = $expr_b->negate();
                $sum_b_negated = $expr_b_negated->convert_to_sum();
                $sum_c->add_sum($sum_b_negated->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            // if A = expr and B = 0 (e.g. xA + C), sum c = xA' + x'
            elseif ($expr_a_type == 'expr' && $expr_b_type == 'zero') {
                $expr_a_negated = $expr_a->negate();
                $sum_a_negated = $expr_a_negated->convert_to_sum();
                $sum_c->add(new BinaryExpression(array(new Term(array($x_negated)))));
                $sum_c->add_sum($sum_a_negated->multiply(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = 1 (e.g. xA + x' + C), sum c = xA'
            elseif ($expr_a_type == 'expr' && $expr_b_type == 'one') {
                $expr_a_negated = $expr_a->negate();
                $sum_a_negated = $expr_a_negated->convert_to_sum();
                $sum_c->add_sum($sum_a_negated->multiply(new BinaryExpression(array(new Term(array($x))))));
            }
            // if A = expr and B = expr (e.g. xA + x'B + C), sum c = xA' + x'B'
            elseif ($expr_a_type == 'expr' && $expr_b_type == 'expr') {
                $expr_a_negated = $expr_a->negate();
                $expr_b_negated = $expr_b->negate();
                $sum_a_negated = $expr_a_negated->convert_to_sum();
                $sum_b_negated = $expr_b_negated->convert_to_sum();
                $sum_c->add_sum($sum_a_negated->multiply(new BinaryExpression(array(new Term(array($x))))));
                $sum_c->add_sum($sum_b_negated->multiply(new BinaryExpression(array(new Term(array($x_negated))))));
            }
            else {
                throw new Exception('Unknown condition - should not have reached here.');
            }

            // now multiply the sum c with C and add to the main sum
            $sum->add_sum($sum_c->multiply_sum($expr_c->convert_to_sum()));
        }

        // debug: echo "Converted Sum before simplification: " . $sum->toString() . "\n";
        $sum->simplify()->unify()->merge_terms();
        // debug: echo "Converted Sum after simplification " . $this->toString() . ": " . $sum->toString() . "\n";

        // debug: if (count($this->terms) == 3) exit;

        // return the new sum
        return $sum;
    }
	
}

