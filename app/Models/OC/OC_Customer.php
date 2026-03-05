<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_Customer extends Model {  
    public $timestamps  = false;
    protected $primaryKey = 'customer_id'; 
    public function __construct(){        
        $this->table = env('DB_PREFIX').'customer';
    }
}