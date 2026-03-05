<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_AtributeGroup extends Model {   
    public $timestamps  = false;
    public function __construct(){        
        $this->table = env('DB_PREFIX').'attribute_group_description';
    }
}