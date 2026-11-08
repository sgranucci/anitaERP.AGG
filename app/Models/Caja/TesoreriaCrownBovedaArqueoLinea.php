<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaArqueoLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_boveda_arqueo_linea';

    protected $fillable = [
        'arqueo_id',
        'valor_id',
        'inicial',
        'ingresos',
        'egresos',
        'actual',
    ];

    protected $casts = [
        'inicial' => 'decimal:2',
        'ingresos' => 'decimal:2',
        'egresos' => 'decimal:2',
        'actual' => 'decimal:2',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownBovedaArqueo::class, 'arqueo_id');
    }

    public function valor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownValor::class, 'valor_id');
    }
}
