<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_ManufacturerToStore extends Model {   
    //protected $primaryKey = 'manufacturer_id'; 
    public $timestamps  = false;
    public function __construct(){        
        $this->table = env('DB_PREFIX').'manufacturer_to_store';
    }
}