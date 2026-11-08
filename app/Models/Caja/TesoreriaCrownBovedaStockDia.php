<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownBovedaStockDia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const FUENTE_ANITA = 'anita';

    public const FUENTE_ERP = 'erp';

    protected $table = 'tesoreria_crown_boveda_stock_dia';

    protected $fillable = [
        'jornada_id',
        'sala_id',
        'empresa_id',
        'fuente',
    ];

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TesoreriaCrownBovedaStockDiaLinea::class, 'stock_dia_id');
    }
}
