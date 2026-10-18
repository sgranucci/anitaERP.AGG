<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteContableParticipacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'reporte_contable_participacion';

    protected $fillable = [
        'reporte_contable_id',
        'empresa_id',
        'porcentaje',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'reporte_contable_id' => 'integer',
        'empresa_id' => 'integer',
        'porcentaje' => 'float',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }
}
