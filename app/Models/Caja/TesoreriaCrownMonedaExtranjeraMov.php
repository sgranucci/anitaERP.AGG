<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownMonedaExtranjeraMov extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_moneda_extranjera_mov';

    protected $fillable = [
        'moneda_extranjera_id',
        'fecha',
        'monto',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function partida(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownMonedaExtranjera::class, 'moneda_extranjera_id');
    }
}
