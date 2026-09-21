<?php

namespace App\Models\Ventas;

use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Par punto de venta online ↔ depósito para emisión Tiendanube.
 *
 * @property int $id
 * @property int $puntoventa_id
 * @property int $deposito_id
 * @property bool $es_default
 * @property int $orden
 */
class TiendanubePuntoventaDeposito extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tiendanube_puntoventa_deposito';

    protected $fillable = [
        'puntoventa_id',
        'deposito_id',
        'es_default',
        'orden',
    ];

    protected $casts = [
        'es_default' => 'boolean',
        'orden' => 'integer',
    ];

    public function puntoventa(): BelongsTo
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }
}
