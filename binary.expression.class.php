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
		if (count($this->terms) == 0) return '0';
		$retval = '';
		for ($i = 0; $i < count($this->terms); $i++) { 
			$retval .= '(' . $this->terms[$i]->toString('and') . ')' . ($i != count($this->terms)-1 ? ' or ' : '');
		}
		return $retval;
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
	 * negates the expression - negate all terms and "and" them 
	 */
	public function negate() { 
		
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
	public function new_negate() { 
		
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
			if ($this->terms[$i]->val === null || $this->terms[$i]->val === '1') $terms[] = $this->terms[$i];   
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
	
		// check each term and see if it can be merged 
		$terms = array();
		for ($i = 0; $i < count($this->terms); $i++) {
	
			// check if the term can be merged with another one - if not, simply keep it 
			$merged_term = false; 
			for ($j = 0; $j < count($terms); $j++) if ($terms[$j]->merge($this->terms[$i])) { $merged_term = true; break; }
			
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
				
				// now add the new expression to this one 
				$this->add($newexpr);
				
				// so that we can know that we need to simplify 
				$replacement_made = true; 
			}
			
			// now apply the negated variable if it exists 
			if ($this->terms[$i]->has_boolean((new Boolean($var))->negate())) {
				 throw new Exception('Negated variable expression update not implemented yet');
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
	
}

