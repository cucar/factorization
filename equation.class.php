<?php

require_once 'sum.class.php';

/*
 * equation class
*/
class Equation {

    // equation left hand side and right hand side
    public $left;
    public $right;

    /*
     * constructor - initialize left and right sides
    */
    public function __construct($left, $right) {

        // if the left side is a single term with a single variable, use the variable itself
        if (is_object($left) && is_a($left, 'Term') && count($left->vars) == 1) $left = $left->vars[0];

        // if the variable is a boolean object, convert it to variable
        if (is_object($left) && is_a($left, 'Boolean')) {

            // debug:
            echo "converting " . $left->toString() . " boolean to variable \n";

            // negate if needed
            if ($left->negated) {
                if (!is_object($right)) {
                    echo "converting " . $left->toString() . " boolean to variable - value: $right \n";
                    if ($right == '1') $right = 0; else $right = 1;
                }
                elseif (method_exists($right, 'negate')) {
                    $right = $right->negate();
                    if (method_exists($right, 'simplify')) $right->simplify()->unify()->merge_terms();
                }
                else throw new Exception('Unknown condition in deduction: ' . print_r($right, true));
            }

            // use the variable directly
            $left = $left->var;
        }

        // if the value is a simple expression with a single variable, just set them as equal
        if (is_object($right) && is_a($right, 'BinaryExpression') && count($right->terms) == 1 && count($right->terms[0]->vars) == 1) $right = $right->terms[0]->vars[0];

        // now assign the simplified values
        $this->left = $left;
        $this->right = $right;
    }

    /*
     * prints the equation
     */
    public function toString() {
        return (method_exists($this->left, 'toString') ? $this->left->toString() : $this->left) . ' = ' . (method_exists($this->right, 'toString') ? $this->right->toString() : $this->right);
    }

    /*
     * returns if the equation is the same as another given equation
     */
    public function equals(Equation $eq) {

        // totality comparison
        if (!is_object($this->left)) return (!is_object($eq->left) && $eq->right === $this->right);

        // if this is a zero product equation, just compare the left sides
        if (is_a($this->left, 'Term')) return (is_object($eq->left) && is_a($eq->left, 'Term') && $eq->right === $this->right && $this->left->equals($eq->left));

        // if this is a variable equation, compare the left and right sides
        if (is_a($this->left, 'Variable')) {

            // if left sides are different, return false
            if ($this->left->toString() != $eq->left->toString()) return false;

            // if the right side is not an object, run regular equality check - otherwise call equals routine on it
            if (!is_object($this->right)) return ($this->right === $eq->right);
            else return $this->right->equals($eq->right);
        }

        // otherwise it is an unknown type of equation
        throw new Exception('Unknown Equation type in comparison: ' . $this->toString() . " " . $eq->toString());
    }

}

