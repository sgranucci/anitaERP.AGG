<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_ProductToStore extends Model {   
    public $timestamps  = false;
    public function __construct(){        
        $this->table = env('DB_PREFIX').'product_to_store';
    }
}