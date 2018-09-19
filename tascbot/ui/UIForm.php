<?php
class UIForm {
  protected $options = NULL;
  protected $form_fields = NULL;
  protected $form_name = NULL;
  protected $form_errors = 0;
  protected $form_messages = NULL;

  // class status flags
  protected $form_built = FALSE;
  protected $form_populated = FALSE;
  protected $form_validated = FALSE;
  protected $form_altered = FALSE;
  protected $form_submitted = FALSE;

  // constructor
  public function __construct($options=NULL) {
    // create form array
    $this->form_fields = array(); 

    return $this;
  }

  // set up the form fields and add them to the array
  public function build() { $this->form_built = TRUE; return $this; }

  // add field to the form object
  // takes a list of variable arguments
  protected function addField() {
    foreach(func_get_args() as $argument) {
      if ( (!is_null($argument)) && (is_array($argument)) ) {
        $this->form_fields[$argument['name']] = new UIFormField($argument);
      }
    }
    return $this;
  }

  // populate the form fields form the submitted input
  public function populate($params=NULL) {
    if ((!is_null($params)) && (is_array($params))) {
      foreach($params as $key => $value) {
        if (array_key_exists($key,$this->form_fields)) {
          if (strcasecmp($this->form_fields[$key]->data_type,"array") == 0) {
            if ($value instanceof Parameter) {
              $this->form_fields[$key]->value = implode(',',$value->asArray());
            }
            else {
              $this->form_fields[$key]->value = implode(',',$value);
            }
          }
          else {
            if ($value instanceof Parameter) {
              $this->form_fields[$key]->value = $value->asString();
            }
            else {
              $this->form_fields[$key]->value = $value;
            }
          }
        }
      }
      $this->form_populated = TRUE;
      // run the post populate method to levy modifications against the input
      $this->postPopulate();
    }
  }

  // validate the form fields based on the validators and custom function
  public function validate() {
    // loop through validators and run them
    foreach($this->form_fields as $field) {
      $field->valid = TRUE;
      // validate required items
      if ($field->required) {
         if ((strlen($field->value) == 0) || (empty($field->value)) || (is_null($field->value)) ) {
           $field->valid = FALSE; $this->form_errors++; 
           if ((isset($field->default_error)) && (!empty($field->default_error))) {
             $field->error = $field->default_error;
           }
           else {
             $field->error = "This field is required";
           }
         }
      }
      // run validators for items that are not already failed
      if (($field->valid) && (!empty($field->validator)) && (is_array($field->validator))) {

        // skip empty fields
        if (empty($field->value)) { continue; }

        foreach($field->validator as $validator) {
          // run the function
          if (call_user_func($validator['func'],$field->value)) {
            $field->valid = TRUE;
          }
          else {
            $field->valid = FALSE; $this->form_errors++; 
            if ((isset($validator['message'])) && (!empty($validator['message']))) {
              $field->error = $validator['message'];
            }
            else if ((isset($field->default_error)) && (!empty($field->default_error))) {
              $field->error = $field->default_error;
            }
            else {
              $field->error = "This field contains an invalid value";
            }
            // break out of the loop.  No need to continue with validations if one has failed
            break;
          }
        }
      }
      $this->form_validated = TRUE;
      // run the post validate method to levy modifications against the validated input
      $this->postValidate();
    }
    return;
  }

  public function alter() {
    $this->form_altered = TRUE;
    return;
  }

  // Set a form field into the structure
  public function setField($form_field) {
    $new_form_field = new UIFormField($form_field);
    $this->form_fields[$new_form_field->name] = $new_form_field;
  }

  // If it exists in the data structure, return the named form_field object
  public function getField($field_name) {
    $retval = NULL;
    // if field exists
    if (array_key_exists($field_name,$this->form_fields)) {
      $retval = $this->form_fields[$field_name];
    }
    return $retval;
  }

  public function getFields() { return $this->form_fields; } 

  // return the values of the fields in an associative array 
  public function getFieldValues($exclude_ignored=TRUE) {
    $value_array = array();
    foreach($this->form_fields as $field) {
      if (($field->ignore == TRUE) && ($exclude_ignored)) {
        continue;
      }
      else {
        $value_array[$field->name] = $field->value;
      }
    }
    return $value_array;
  }

  public function clear() {
    foreach ($this->form_fields as $field) {
      $this->form_fields[$field->name]->value = "";
      $this->form_fields[$field->name]->error = "";
    }
  }

  public function isValid() { return ( (($this->form_validated) && ($this->form_errors == 0)) ? TRUE : FALSE); }
  public function isSubmitted() { return $this->form_submitted; }
  public function errors() { return $this->form_errors; }
  protected function postPopulate() { return; }
  protected function postValidate() { return; }

}

// ============================================================
//
// ============================================================
class UIFormField {
  public $name = NULL;
  public $required = FALSE;
  public $ignore = FALSE;
  public $value = NULL;
  public $input_type = NULL;
  public $data_type = "string";
  public $default_value = NULL;
  public $validator = NULL;
  public $default_message = NULL;
  public $default_error = NULL;
  public $error = NULL;
  public function __construct($options=NULL) {
    if ( (!is_null($options)) && (is_array($options)) ) {
      // add all array members as class attributes
      foreach ($options as $key => $value) {
        $this->$key = $value;
      }
      // check for key options
      if (!array_key_exists('name',$options)) {
        throw Exception("UIFormField must have a name");
      }
      if (array_key_exists('validator',$options)) {
        // make this function
        if (is_array($options['validator'])) {
          foreach($options['validator'] as $key => $v) {
            if ( (!is_callable($v['func'])) && (strlen($v['func']) > 0)) {
              $this->validator[$key]['func'] = create_function('$value',$v['func']);
            }
          }
        }
        else {
          throw Exception('The validator field must be an array');
        }
      }
    }
    else {
      throw Exception('You must supply an array of options to create a UIFormField');
    }
    return $this;
  }
}
?>
