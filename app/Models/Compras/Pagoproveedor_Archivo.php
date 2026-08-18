<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Pagoproveedor_Archivo extends Model
{

    protected $table = 'pagoproveedor_archivo';

    protected $fillable = [
        'pagoproveedor_id', 'nombrearchivo',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }
}
