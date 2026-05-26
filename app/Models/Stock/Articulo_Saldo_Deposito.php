<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo on-line por (articulo, deposito) mantenido por el observer
 * Articulo_MovimientoObserver. La tabla `articulo_saldo_deposito` es
 * fuente única de verdad para consultas rápidas de stock por depósito.
 */
class Articulo_Saldo_Deposito extends Model
{
    protected $table = 'articulo_saldo_deposito';

    protected $fillable = [
        'articulo_id',
        'deposito_id',
        'cantidad',
        'fecha_ult_movimiento',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'fecha_ult_movimiento' => 'datetime',
    ];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }
}
