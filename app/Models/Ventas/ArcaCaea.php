<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Support\Ventas\CaeaQuincenaSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcaCaea extends Model
{
    public const ESTADO_OK = 'ok';

    public const ESTADO_OBSERVACION = 'observacion';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ORIGEN_AUTOMATICO = 'automatico';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_ANITA = 'import_anita';

    protected $table = 'arca_caea';

    protected $fillable = [
        'empresa_id',
        'periodo',
        'orden',
        'cuit',
        'nro_caea',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'fecha_tope_informe',
        'fecha_proceso',
        'estado',
        'origen',
        'solicitado_por_usuario_id',
        'codigo_error',
        'mensaje_error',
        'observaciones',
    ];

    protected $casts = [
        'periodo' => 'integer',
        'orden' => 'integer',
        'fecha_vigencia_desde' => 'date',
        'fecha_vigencia_hasta' => 'date',
        'fecha_tope_informe' => 'date',
        'fecha_proceso' => 'datetime',
        'observaciones' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por_usuario_id');
    }

    public function getEtiquetaQuincenaAttribute(): string
    {
        return CaeaQuincenaSupport::etiquetaQuincena((int) $this->periodo, (int) $this->orden);
    }

    public function estaAutorizado(): bool
    {
        return in_array($this->estado, [self::ESTADO_OK, self::ESTADO_OBSERVACION], true)
            && $this->nro_caea !== null
            && trim((string) $this->nro_caea) !== '';
    }
}
