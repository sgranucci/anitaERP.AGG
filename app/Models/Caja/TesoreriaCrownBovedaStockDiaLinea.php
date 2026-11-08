<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaStockDiaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_boveda_stock_dia_linea';

    protected $fillable = [
        'stock_dia_id',
        'valor_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function stockDia(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBovedaStockDia::class, 'stock_dia_id');
    }

    public function valor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownValor::class, 'valor_id');
    }
}
