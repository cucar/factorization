<?php

require_once 'variable.class.php';

/*
 * boolean variable - same as variable except it may be negated 
*/
class Boolean {

	// actual variable we contain (e.g. x0, y1, etc.) 
	public $var;
	
	// negated or not  
	public $negated; 
	
	/*
	 * constructor - variable and negation 
	*/
	public function __construct($var, $negated = false) {
		$this->var = $var;
		$this->negated = $negated;
	}

	/*
	 * returns the negated version of the variable 
	*/
	public function negate() {
		return new Boolean($this->var, !$this->negated);
	}
	
	/* 
	 * returns the variable as a string 
	 */
	public function toString() { 
		return $this->var->toString() . ($this->negated ? "'" : '');
	}

	/* 
	 * make a copy of the boolean variable 
	 */
	public function copy() { 
		return new Boolean($this->var->copy(), $this->negated);
	}
	
	/* 
	 * checks if the variable equals another variable 
	 */
	public function equals($var) { 
		if ($this->var->equals($var->var) && $this->negated == $var->negated) return true;
		else return false; 
	}

	/* 
	 * checks if the variable equals another variable as negated form 
	 */
	public function equalsNegated($var) { 
		if ($this->var->equals($var->var) && $this->negated == !$var->negated) return true;
		else return false; 
	}
}

