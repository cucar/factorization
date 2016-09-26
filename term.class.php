<?php

require_once 'boolean.class.php';

/*
 * term - contains a sequence of boolean variables applied with an operator ("and" or "multiplication" - they are the same for our purposes)
*/
class Term {

	// boolean variables array 
	public $vars = array();
	
	// if the term evaluates to a value, keep it here 
	public $val = null; 

	/*
	 * constructor - variables in the term 
	*/
	public function __construct($vars = array(), $val = null) {
		$this->vars = $vars;
		$this->val = $val;
		$this->sort();
	}

	/* 
	 * add a new variable to the term 
	 */
	public function add($var) { 
		$this->vars[] = $var;
		$this->sort();
	}
	
	/* 
	 * make a copy of the term 
	 */
	public function copy() { 
		return new Term(array_map(function($var) { return $var; }, $this->vars), $this->val);
	}

	/* 
	 * "and"s another term 
	 */
	public function and_term($term) {

	    // if our value is zero, we stay the same
        if (count($this->vars) == 0 && $this->val == 0) return $this;

        // if the term's value is zero, become zero
        if (count($this->vars) == 0 && $this->val == 0) { $this->vars = array(); $this->val = 0; return $this; }

        // if our value is one, we become the term
        if (count($this->vars) == 0 && $this->val == 1) { $this->vars = $term->copy()->vars; $this->val = $term->val; return $this; }

        // otherwise add the term's variables
		foreach ($term->vars as $var) $this->vars[] = $var;

        // sort for better display
		$this->sort();

        // return self
		return $this; 
	}
	
	/* 
	 * convert variables to binary if needed 
	 */
	public function binary() { 
		for ($i = 0; $i < count($this->vars); $i++) {
			if (!is_a($this->vars[$i], 'Boolean')) $this->vars[$i] = new Boolean($this->vars[$i]);
		}
		return $this; 
	}
	
	/* 
	 * sorts the variables in the term 
	 */
	public function sort() { 
		usort($this->vars, function($a, $b) {

			// x before y 
			if ($a->var->type == x && $b->var->type == y) return -1;
			if ($a->var->type == y && $b->var->type == x) return 1;
			
			// sorting by digits within types 
			if ($a->var->digit < $b->var->digit) return -1;
			if ($a->var->digit > $b->var->digit) return 1;
			
			// same variable - negation should come after the positive version
			if (!$a->negated && $b->negated) return -1;
			if ($a->negated && !$b->negated) return 1;
			
			// everything is identical 
			return 0;
		});
	}
	
	/* 
	 * returns the term as a string - we do not have actual knowledge of which function is applied here so it has to be given 
	 */
	public function toString() { 
		$retval = '';
		for ($i = 0; $i < count($this->vars); $i++) $retval .= $this->vars[$i]->toString();
		if ($this->val !== null) $retval .= strval($this->val);
		return $retval;
	}
	
	/* 
	 * apply a variable value to the term - returns if the term has changed or not
	 */
	public function apply_var($var, $val) { 

		// if the term already has a particular value, no need to apply anything 
		if ($this->val !== null) return false;

		// debug: echo 'Applying ' . $var->toString() . ' = ' . $val . " to term " . $this->toString() . "\n";
		
		// string representation of the variable for easy comparison  
		$strval = $var->toString();
		
		// loop through the variables and check if we encounter the variable - replace value if we do 
		for ($i = 0; $i < count($this->vars); $i++) { 
			
			// looks like we found the value - evaluate the term now 
			if ($this->vars[$i]->var->toString() == $strval) { 

				// debug: echo "Found variable\n";
				
				// if the variable in expression is negated, negate the value and set it for the term 
				if ($this->vars[$i]->negated) $apply_value = ($val ? '0' : '1');
				else $apply_value = ($val ? '1' : '0');
				
				// debug: echo "Apply value: $apply_value\n";
				
				// if the value we're applying is zero, it means the entire term becomes zero 
				if ($apply_value == '0') { 
					
					// remove all variables in the term 
					$this->vars = array();
					
					// set the value as zero 
					$this->val = '0';
					
					// no more processing needed
					// echo "Applied 0: " . $this->toString() . "\n"; 
					return true;
				}
				
				// if we're applying value of one, check if we have other variables in the term 
				// if we have other variables, just remove this variable, otherwise term becomes 1 
				if ($apply_value == '1') { 
					if (count($this->vars) > 1) { 
						// debug: echo 'Term before application: ' . $this->toString() . "\n";
						unset($this->vars[$i]);
						$this->vars = array_values($this->vars);
						// debug: echo "Application result: " . $this->toString() . "\n";
                        return true;
					} 
					else { 
						$this->vars = array(); 
						$this->val = '1'; 
						// echo "Applied 1: " . $this->toString() . "\n"; 
						return true;
					}
				}
			}
		}

		// variable not found in term - unchanged
        return false;
	}

	/* 
	 * simplify term - check for conflicting variables 
	 */
	public function simplify() { 
		
		// loop through the variables and check for their conflicting counterparts 
		foreach ($this->vars as $var1) 
			foreach ($this->vars as $var2)
				if ($var1->toString() == $var2->negate()->toString()) { 
					
					// echo "Simplifying term to zero: " . $this->toString() . "\n";
					
					// remove all variables in the term
					$this->vars = array();
						
					// set the value as zero
					$this->val = '0';
						
					// no more processing needed
					return;
				}
	}
	
	/*
	 * returns the variables in the term 
	 */ 
	public function vars() { 
		$vars = array();
		foreach ($this->vars as $var) if (!in_array($var->var, $vars)) $vars[] = $var->var;
		return $vars;
	}
	
	/* 
	 * unify term - remove duplicate variables (keep one of them) 
	 */
	public function unify() { 
		
		// loop through the variables and check for their conflicting counterparts 
		$vars = array();
		for ($i = 0; $i < count($this->vars); $i++) { 
			$duplicate_var = false; 
			for ($j = 0; $j < count($vars); $j++) { 
				if ($i != $j && $this->vars[$i]->toString() == $vars[$j]->toString()) { 
					// echo "Duplicate variable found: " . $this->vars[$i]->toString() . "\n";
					$duplicate_var = true; 
				}
			}
			
			// do not include duplicate vars 
			if (!$duplicate_var) $vars[] = $this->vars[$i];
		}
		$this->vars = $vars; 
	}
	
	/* 
	 * checks if this term equals another term - this should be called after unification of both terms   
	 */
	public function equals($term) { 
		
		// check the object type 
		if (!is_object($term) || !is_a($term, 'Term')) return false; 
		
		// if the variable counts are different, terms are different
		if (count($this->vars) != count($term->vars)) return false; 
		
		// loop through the variables and check for their conflicting counterparts 
		$vars = array();
		for ($i = 0; $i < count($this->vars); $i++) { 
			$var_exists = false; 
			for ($j = 0; $j < count($term->vars); $j++) { 
				if ($this->vars[$i]->toString() == $term->vars[$j]->toString()) { 
					// echo "Variable found: " . $this->vars[$i]->toString() . "\n";
					$var_exists = true; 
				}
			}
			
			// if a variable does not exist, terms are different 
			if (!$var_exists) return false;  
		}
		
		// we found all the variables on the other term and the term count is identical - terms are identical 
		// echo "Identical terms found: " . $this->toString() . "\n";
		return true;  
	}
	
	/* 
	 * returns if a variable exists in this term    
	 */
	public function has_variable(Variable $var) { 
		
		// loop through the variables and check if the variable exists  
		for ($i = 0; $i < count($this->vars); $i++) 
			if ($this->vars[$i]->var->toString() == $var->toString()) return true;  
		
		// variable not found in the term  
		return false;  
	}
	
	/* 
	 * returns if a boolean variable exists in this term    
	 */
	public function has_boolean($bool) { 
		
		// loop through the variables and check if the variable exists  
		for ($i = 0; $i < count($this->vars); $i++) 
			if ($this->vars[$i]->toString() == $bool->toString()) return true;  
		
		// variable not found in the term  
		return false;  
	}
	
	/* 
	 * removes a variable in the term     
	 */
	public function remove_variable($var) { 
		
		// new term to be returned 
		$term = $this->copy();
		
		// loop through the variables and remove the variable
		for ($i = 0; $i < count($term->vars); $i++) {  
			if ($term->vars[$i]->toString() == $var->toString()) { 
				unset($term->vars[$i]);
				$term->vars = array_values($term->vars);
                // if there are no variables left, set the value as 1
                if (count($term->vars) == 0) $term->val = 1;
				return $term; 
			}
		}
		
		// variable not found - error out
		throw new Exception('Error removing variable from Term - not found');
	}
	
	/* 
	 * tries to merge a term to this one - usage cases:  
	 * 1- both terms are identical - that means the given term can simply be discarded - merge without doing anything 
	 * 2- where the given term is more specific than ours and therefore it can be discarded - e.g. - (x2' and x3) or (x2' and x1 and x3) = (x2' and x3)
	 * in that case, the bigger term is the argument - we are the smaller term - so the big term is merged into smaller term 
	 * in that case the bigger term can again be merged into this one without doing anything special - it can simply be discarded 
	 * 3- where the negation appears exactly - e.g. (x2 * x1) + (x2 * x1') => x2 - in that case we remove the conflicting term from us as we merge  
	 */
	public function merge($term) { 

		// the argument has to be at least 2 variables
		if (count($term->vars) < 2) return false;
		
		// the merged term has to be at least 1 variable
		if (count($this->vars) < 1) return false;
		
		// the argument has to be bigger or equal to this one 
		if (count($term->vars) < count($this->vars)) return false; 
		
		// debug: echo "Checking if terms can be merged: (" . $this->toString() . ') and (' . $term->toString() . ")\n";
		
		// loop through the variables and check if they can be found in the other term 
		$diff_var = null; 
		for ($i = 0; $i < count($this->vars); $i++) {

			// debug: echo "Checking if " . $this->vars[$i]->toString() . " can be found in the term: " . $term->toString() . "\n";

			// check if the variable exists in the other term 
			$var_exists = false; 
			for ($j = 0; $j < count($term->vars); $j++) { 
				if ($this->vars[$i]->toString() == $term->vars[$j]->toString()) { 
					// debug: echo "Variable found: " . $this->vars[$i]->toString() . "\n";
					$var_exists = true; 
				}
			}
			
			// if the variable exists in the other term, we don't need to check any further
			if ($var_exists) continue; 
			
			// if there is already a variable that was different, there can't be two - we can't merge this 
			if ($diff_var !== null) return false; 

			// check if the reverse of this variable exists 
			for ($j = 0; $j < count($term->vars); $j++) {
				if ($this->vars[$i]->negate()->toString() == $term->vars[$j]->toString()) {
					// debug: echo "Variable reverse found: " . $this->vars[$i]->toString() . "\n";
					$diff_var = $i;
					break; 
				}
			}
			
			// if the variable reverse does not exist, the terms cannot be merged 
			if ($diff_var === null) { 
				// debug: echo "Variable reverse does not exist\n"; 
				return false; 
			}
		}
		
		// if we did not find a different variable, it's all identical - simply return true without changing anything on our side (case 1 and 2) 
		if ($diff_var === null) { 
			// debug: echo "Ready for identical merge\n"; 
			return true; 
		}  
		
		// so, all variables of this one appears on the other except for one where it appears negated 
		// if the number of variables is not the same, this would not fall into case 3 though
		if (count($term->vars) != count($this->vars)) { 
			// debug: echo "there are more variables\n"; 
			return false; 
		}   
		
		// terms can be merged - keep only the identical variables - remove the different variable
		// debug: echo "Ready to merge: $diff_var\n"; 
		unset($this->vars[$diff_var]);
		$this->vars = array_values($this->vars);
		// debug: echo "Term merged: " . $this->toString() . "\n"; exit;
		return true;  
	}
	
	/* 
	 * applies a zero product to the term - returns if the term changed or not as a result of the application
	 */
	public function apply_zero_product($zero_product) { 

		// the argument has to be at least 1 variable
		if (count($zero_product->vars) < 1) return false;
		
		// the merged term has to be at least 1 variable
		if (count($this->vars) < 1) return false;
		
		// the argument has to be smaller or equal to this one 
		if (count($zero_product->vars) > count($this->vars)) return false;
		
		// debug: echo 'Checking if zero product ' . $zero_product->toString() . ' can be used to reduce the term: ' . $this->toString() . "\n";
		
		// loop through the variables and check if they can be found here
		$diff_var = null; 
		for ($i = 0; $i < count($zero_product->vars); $i++) {

			// debug: echo "Checking if " . $zero_product->vars[$i]->toString() . " can be found in the term: " . $this->toString() . "\n";

			// check if the variable exists in the term 
			$var_exists = false; 
			for ($j = 0; $j < count($this->vars); $j++) { 
				if ($this->vars[$j]->toString() == $zero_product->vars[$i]->toString()) { 
					// debug: echo "Variable found: " . $this->vars[$j]->toString() . "\n";
					$var_exists = true; 
				}
			}
			
			// if the variable is not found, nothing to do - zero product is not applicable to us 
			if (!$var_exists) { 
				// debug: echo 'Variable not found' . "\n"; 
				return false;
			}  
		}
		
		// so, all variables of the zero product appear in this term - it means this term is now zero - update and return true to indicate that the term has changed
		echo "Term is now zero as a result of zero product application.\n";
		$this->val = 0; 
		$this->terms = array();
        return true;
	}
	
	/* 
	 * apply a variable replace to the term - returns if the term changed or not as a result of the application
	 */
	public function apply_var_replace($oldvar, $newvar) { 

		// if the term already has a particular value, no need to apply anything 
		if ($this->val !== null) return false;

		// echo 'Applying ' . $var->toString() . ' = ' . $val . " to term " . $this->toString() . "\n";
		
		// if the given variable is not without boolean, add it 
		if (is_a($newvar, 'Variable')) $newvar = new Boolean($newvar);
		
		// string representation of the variable for easy comparison  
		$oldvarstr = $oldvar->toString();
		
		// loop through the variables and check if we encounter the variable - replace value if we do 
        $var_exists = false;
        for ($i = 0; $i < count($this->vars); $i++) {

			// looks like we found the value as-is - evaluate the term now 
			if ($this->vars[$i]->toString() == $oldvarstr) { 
				// echo "Replacing " . $this->vars[$i]->toString() . ' with ' . $apply_value->toString() . "\n"; 
				$this->vars[$i] = $newvar;
				// echo "Replaced term: " . $this->toString() . "\n"; 
                $var_exists = true;
                continue;
			}
			
			// looks like we found the value negated - evaluate the term now with the negated variable
			if ($this->vars[$i]->negate()->toString() == $oldvarstr) {
				// echo "Replacing " . $this->vars[$i]->toString() . ' with ' . $apply_value->toString() . "\n";
				$this->vars[$i] = $newvar->negate();
				// echo "Replaced term: " . $this->toString() . "\n";
                $var_exists = true;
				continue;
			}
		}

		// if the variable is not found in the term, nothing has changed, nothing to do
        if (!$var_exists) return false;

		// term has changed as a result of the application - now unify and simplify
		$this->unify();
		$this->simplify();
        $this->sort();
        return true;
	}

	/* 
	 * negates a term and returns a binary expression 
	 */
	public function negate() { 
		
		// go through each of the variables and negate them and add them to the expression as terms
		$terms = array();  
		for ($i = 0; $i < count($this->vars); $i++) $terms[] = new Term(array($this->vars[$i]->negate()));
		return new BinaryExpression($terms);
	}
	
}

