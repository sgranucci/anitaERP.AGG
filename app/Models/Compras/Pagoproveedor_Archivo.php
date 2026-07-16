<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagoproveedor_Archivo extends Model
{
    use SoftDeletes;

    protected $table = 'pagoproveedor_archivo';

    protected $fillable = [
        'pagoproveedor_id', 'nombrearchivo',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }
}
