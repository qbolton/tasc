<?php

use Illuminate\Database\Eloquent\Model as Eloquent;

class Feed extends Eloquent {
  protected $table = 'feeds';
  protected $primaryKey = 'fid';
  public $timestamps = FALSE;
  // protected $fillable = [];
  public function __construct( $table=NULL ) {
    parent::__construct();
    $this->table = $table;
  }
}
