<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo acumulado por cuenta congelado al registrar un cierre de período contable.
 */
class Cuentacontable_Saldo_Cierre extends Model
{
    protected $table = 'cuentacontable_saldo_cierre';

    protected $fillable = [
        'periodo_cierre_id',
        'empresa_id',
        'fecha_hasta',
        'anio_mes',
        'cuentacontable_id',
        'centrocosto_id',
        'moneda_id',
        'monto_acumulado',
        'monto_local_acumulado',
    ];

    protected $casts = [
        'fecha_hasta' => 'date',
        'anio_mes' => 'integer',
        'monto_acumulado' => 'decimal:4',
        'monto_local_acumulado' => 'decimal:4',
    ];

    public function periodoCierre(): BelongsTo
    {
        return $this->belongsTo(PeriodoCierreContable::class, 'periodo_cierre_id');
    }

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function centrocostos(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function monedas(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }
}
