<?php

namespace App\Models\Ventas;

use App\Models\Caja\Cuentacaja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Par gateway Tiendanube → cuenta de caja (Excel / Facturante).
 *
 * @property int $id
 * @property string|null $store_id
 * @property string $gateway_key
 * @property int $cuentacaja_id
 * @property int $orden
 */
class TiendanubeGatewayCuentacaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tiendanube_gateway_cuentacaja';

    protected $fillable = [
        'store_id',
        'gateway_key',
        'cuentacaja_id',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}
