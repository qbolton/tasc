<?php

use Illuminate\Database\Eloquent\Model as Eloquent;

class WpBlog extends Eloquent {
  protected $table = 'wp_blogs';
  public $timestamps = FALSE;
  // protected $fillable = [];
}
