<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_ProductDescription extends Model {   
    public $timestamps  = false;
    protected $primaryKey = 'product_id';
    public function __construct(){        
        $this->table = env('DB_PREFIX').'product_description';
    }
}