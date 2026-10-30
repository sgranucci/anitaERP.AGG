<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_boveda_linea';

    protected $fillable = [
        'boveda_id',
        'valor_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBoveda::class, 'boveda_id');
    }

    public function valor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownValor::class, 'valor_id');
    }
}
