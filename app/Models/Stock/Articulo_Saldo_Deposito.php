<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo on-line por (articulo, deposito, color, talle) mantenido por el observer
 * Articulo_MovimientoObserver. color_id/talle_id = 0 significa sin variante.
 * La tabla `articulo_saldo_deposito` es fuente única de verdad para consultas
 * rápidas de stock por depósito (y variante).
 */
class Articulo_Saldo_Deposito extends Model
{
    protected $table = 'articulo_saldo_deposito';

    protected $fillable = [
        'articulo_id',
        'deposito_id',
        'color_id',
        'talle_id',
        'cantidad',
        'fecha_ult_movimiento',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'fecha_ult_movimiento' => 'datetime',
        'color_id' => 'integer',
        'talle_id' => 'integer',
    ];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function talle()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }
}
