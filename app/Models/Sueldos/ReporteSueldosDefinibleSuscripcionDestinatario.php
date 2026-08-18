<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleSuscripcionDestinatario extends Model
{
    protected $table = 'reporte_sueldos_definible_suscripcion_destinatario';

    protected $fillable = [
        'suscripcion_id',
        'dimension_clave',
        'dimension_etiqueta',
        'usuario_id',
        'email',
        'activo',
    ];

    protected $casts = [
        'suscripcion_id' => 'integer',
        'usuario_id' => 'integer',
        'activo' => 'boolean',
    ];

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleSuscripcion::class, 'suscripcion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
