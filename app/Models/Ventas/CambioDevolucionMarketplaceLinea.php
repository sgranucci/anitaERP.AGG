<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CambioDevolucionMarketplaceLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cambio_devolucion_marketplace_linea';

    protected $fillable = [
        'cambio_id',
        'tipo',
        'articulo_id',
        'talle_id',
        'color_id',
        'combinacion_id',
        'cantidad',
        'precio_unitario',
        'venta_emision_id',
        'descripcion',
    ];

    protected $casts = [
        'cambio_id' => 'integer',
        'articulo_id' => 'integer',
        'talle_id' => 'integer',
        'color_id' => 'integer',
        'combinacion_id' => 'integer',
        'cantidad' => 'float',
        'precio_unitario' => 'float',
        'venta_emision_id' => 'integer',
    ];

    public function cambio()
    {
        return $this->belongsTo(CambioDevolucionMarketplace::class, 'cambio_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
