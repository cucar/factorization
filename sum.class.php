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
		return $retval;
	}
	
	/* 
	 * takes mod 2 of a sum - returns a binary expression 
	 */
	public function mod() { 

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
	 * takes div 2 of an expression - returns a regular expression (not binary expression) 
	*/
	public function div() {
	
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
		$newsum = $this->copy();
		
		// go through this expressions and "and" them
		for ($i = 0; $i < count($newsum->exprs); $i++) $newsum->exprs[$i]->and_expr($expr);
		
		// return the merged sum
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

