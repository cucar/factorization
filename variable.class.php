<?php

// shorthand constants for x and y strings 
const x = 'x';
const y = 'y';

/*
 * variable - x/y + number
*/
class Variable {

	// type of variable - x or y 
	public $type;
	
	// digit of the variable 
	public $digit; 
	
	/*
	 * constructor - input x/y and digit number
	*/
	public function __construct($type, $digit) {
		$this->type = $type;
		$this->digit = $digit;
	}

	/* 
	 * returns the variable as a string 
	 */
	public function toString() { 
		return $this->type . $this->digit;
	}
	
	/* 
	 * make a copy of the variable 
	 */
	public function copy() { 
		return new Variable($this->type, $this->digit);
	}

	/*
	 * checks if the variable equals another variable
	*/
	public function equals($var) {
		if ($this->type == $var->type && $this->digit == $var->digit) return true;
		else return false;
	}
	
}

