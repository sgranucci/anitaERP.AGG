<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleEnvio extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ERROR = 'error';

    protected $table = 'reporte_sueldos_definible_envio';

    protected $fillable = [
        'suscripcion_id',
        'ejecucion_id',
        'destinatario',
        'burst_clave',
        'burst_etiqueta',
        'estado',
        'mensaje',
    ];

    protected $casts = [
        'suscripcion_id' => 'integer',
        'ejecucion_id' => 'integer',
    ];

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleSuscripcion::class, 'suscripcion_id');
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleEjecucion::class, 'ejecucion_id');
    }
}
