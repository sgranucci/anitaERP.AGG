<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownMonedaExtranjera extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_moneda_extranjera';

    protected $fillable = [
        'anita_id',
        'fecha',
        'moneda',
        'cotizacion',
        'monto_orig',
    ];

    protected $casts = [
        'anita_id' => 'integer',
        'fecha' => 'date',
        'cotizacion' => 'decimal:6',
        'monto_orig' => 'decimal:2',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(TesoreriaCrownMonedaExtranjeraMov::class, 'moneda_extranjera_id');
    }
}
