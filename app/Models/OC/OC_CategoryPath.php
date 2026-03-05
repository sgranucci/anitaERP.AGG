<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_CategoryPath extends Model {  
    public $timestamps  = false;
    public function __construct(){        
        $this->table = env('DB_PREFIX').'category_path';
    }
}