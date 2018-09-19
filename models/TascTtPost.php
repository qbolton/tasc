<?php

use Illuminate\Database\Eloquent\Model as Eloquent;

class TascTtPost extends Eloquent {
  protected $table = 'tasc_tt_posts';
  protected $primaryKey = 'tasc_ttp_id';
  public $timestamps = FALSE;
  // protected $fillable = [];
  public function __construct( $table=NULL ) {
    parent::__construct();
    $this->table = $table;
  }
}
