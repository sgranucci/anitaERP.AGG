<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_CategoryDescription extends Model {  
    public $timestamps  = false;
    protected $primaryKey = 'category_id'; 
    public function __construct(){        
        $this->table = env('DB_PREFIX').'category_description';
    }
}