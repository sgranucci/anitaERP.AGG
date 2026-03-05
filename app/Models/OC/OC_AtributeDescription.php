<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_AtributeDescription extends Model {   
    public $timestamps  = false;
    protected $primaryKey = 'attribute_id'; 
    public function __construct(){        
        $this->table = env('DB_PREFIX').'attribute_description';
    }
}