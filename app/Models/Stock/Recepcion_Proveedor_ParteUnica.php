<?php

namespace App\Models\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use Illuminate\Database\Eloquent\Model;

class Recepcion_Proveedor_ParteUnica extends Model
{
    protected $table = 'recepcion_proveedor_parte_unica';

    protected $fillable = [
        'recepcion_proveedor_id',
        'recepcion_proveedor_articulo_id',
        'numeroparte',
    ];

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }

    public function recepcion_proveedor_articulos()
    {
        return $this->belongsTo(Recepcion_Proveedor_Articulo::class, 'recepcion_proveedor_articulo_id');
    }

    public function articulo_parte_unica()
    {
        return $this->belongsTo(Articulo_ParteUnica::class, 'numeroparte', 'numeroparte');
    }
}
