<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Arbolaprobacion;
use Illuminate\Database\Eloquent\Model;

class Usoarticulo extends Model
{
    protected $fillable = ['nombre', 'aprobacion_modo', 'arbolaprobacion_id'];

    protected $table = 'usoarticulo';

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }

    public function arbolaprobacion()
    {
        return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
    }
}
