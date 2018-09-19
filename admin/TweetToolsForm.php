<?php
class TweetToolsForm extends UIForm {
  public function build() {
    parent::build();
    $this->addField(
      array('name'=>'tt_sitelist','required'=>FALSE,'value'=>"",'default_value'=>"2",'default_error'=>"You must select a site",
      'validator'=>array(
        array('func'=>Regex::NUMBER_INTEGER,'message'=>"Your site id must be an integer")
      )),
      array('name'=>'tt_selected','required'=>TRUE,'data_type'=>"array",'value'=>"",'default_value'=>"",'default_error'=>"You must select at least one post to send to social media"),
      array('name'=>'form_submit','required'=>FALSE,'data_type'=>"string",'value'=>"",'default_value'=>"",'default_error'=>"You must click the Submit button to scheduled checked posts for tweeting")
    );
  }

  public function validate() {
    parent::validate();
    foreach ($this->form_fields as $field) {
      if ($field->valid) {
        $this->form_fields[$field->name]->error = "";
      }
    }
  }

  // allows form to have a submitted status set
  public function submitted($b) {
    $this->form_submitted = $b;
  }

  public function alter() {
    parent::alter();
  }
}
