<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Articulo_ParteUnica extends Model
{
    protected $table = 'articulo_parte_unica';

    protected $fillable = ['articulo_id', 'numeroparte'];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
