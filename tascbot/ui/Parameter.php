<?php
/**
 * Define http request parameter handling class
 *
 * Defines standard methods for access http parameters
 * @package DooFramework
 * @subpackage common.util
 */

/**
 * Parameter request parameter access class
 * @package DooFramework
 * @subpackage common.util
 */
class Parameter
{
	private $value;

	public function __construct($value=null)
	{
		if (isset($value)) {
			$this->setValue($value);
		}
	}

	private function returnValueAsType($type='string')
	{
		$value = $this->value;
		// check to see if conversion is valid
		if (!settype($value, $type)) {
			throw new ParameterException("Unable to convert parameter value to $type");
		}
		return $value;	
	}

	/**
	 * Sets the value of the parameter
	 *
	 * @access public
	 * @param string $value The value of the parameter
	 * @return void
	 * @throws ParameterException
	 */
	public function setValue($value) {
		if (!isset($value)) { throw new ParameterException("Missing value in call to setValue()"); }
		$this->value = $value;
	}

	/**
	 * Returns the parameter value as it's original form
	 *
	 * @access public
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Returns the parameter value as a boolean
	 *
	 * @access public
	 * @return bool
	 */
	public function asBool()
	{
		return $this->returnValueAsType("boolean");
	}

	/**
	 * Returns the parameter value as an integer
	 *
	 * @access public
	 * @return int
	 */
	public function asInt()
	{
		$value = 0;
		/* We have to check if the value is an object because objects cannot
		 * be converted to integer
		 */
		if (!is_object($this->value)) {
			$value = $this->returnValueAsType("integer");
		}
		return $value;
	}

	/**
	 * Returns the parameter value as a floating point number
	 *
	 * @access public
	 * @return float
	 */
	public function asFloat()
	{
		$value = 0.0;
		/* We have to check if the value is an object because objects cannot
		 * be converted to float
		 */
		if (!is_object($this->value)) {
			$value = $this->returnValueAsType("float");
		}
		return $value;
	}

	/**
	 * Returns the parameter value as a string
	 *
	 * @access public
	 * @return string
	 */
	public function asString()
	{
		$value = "";
		// use the returnValueAsType if the value is not an array or object
		if (!is_array($this->value) && !is_object($this->value)) {
			$value = $this->returnValueAsType("string");
		}
		else {
		 	// We must serialize the parameter value if the parameter is an array or object
			$value = serialize($this->value);
		}
		return $value;
	}

	/**
	 * Returns the parameter value as an array
	 *
	 * @access public
	 * @return array
	 */
	public function asArray()
	{
		return $this->returnValueAsType("array");
	}

	/**
	 * Returns the parameter value as a member variable in instance of php's StdClass 
	 *
	 * @access public
	 * @return StdClass
	 */
	public function asObject()
	{
		return $this->returnValueAsType("object");
	}

	/**
	 * Returns null
	 *
	 * @access public
	 * @return null
	 */
	public function asNull()
	{
		return $this->returnValueAsType("null");
	}
}

/**
 * Parameter Exception class
 * @package DooFramework
 * @subpackage DooFramework.Exceptions
 */
class ParameterException extends Exception {
	public function __construct($msg,$code=0) {
		parent::__construct($msg,$code);
	}
}
?>
