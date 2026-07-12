<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ViandaConsumoLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vianda_consumo_linea';

    protected $fillable = [
        'vianda_consumo_id',
        'articulo_id',
        'combinacion_id',
        'sku',
        'descripcion',
        'tipoarticulo_nombre',
        'cantidad',
        'precio_costo_unitario',
        'precio_venta_unitario',
        'comentario',
        'orden',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_costo_unitario' => 'decimal:4',
        'precio_venta_unitario' => 'decimal:4',
        'orden' => 'integer',
    ];

    public function consumo()
    {
        return $this->belongsTo(ViandaConsumo::class, 'vianda_consumo_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
