<?php

namespace App\Models\Ventas;

use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TiendanubeStockDeposito extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tiendanube_stock_deposito';

    protected $fillable = [
        'store_id',
        'deposito_id',
        'orden',
    ];

    protected $casts = [
        'deposito_id' => 'integer',
        'orden' => 'integer',
    ];

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }
}
