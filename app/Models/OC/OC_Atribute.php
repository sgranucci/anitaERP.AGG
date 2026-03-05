<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_Atribute extends Model {   
    protected $primaryKey = 'attribute_id'; 
    public $timestamps  = false;
    public function __construct(){        
        $this->table = env('DB_PREFIX').'attribute';
    }
}