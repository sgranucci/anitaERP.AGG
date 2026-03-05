<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OC_ProductAttribute extends Model {
    //protected $primaryKey = 'manufacturer_id'; 
    public $timestamps  = false;
    protected $fillable = [
        'product_id',
        'attribute_id',
        'language_id',
        'text'
    ];
    public function __construct(){        
        $this->table = env('DB_PREFIX').'product_attribute';
    }
}