<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_ProductToCategory extends Model {   
    public $timestamps  = false;
    public function __construct(){        
        $this->table = env('DB_PREFIX').'product_to_category';
    }
}