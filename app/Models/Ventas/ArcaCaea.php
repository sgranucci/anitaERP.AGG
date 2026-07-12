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

    public const INFORME_ESTADO_PENDIENTE = 'pendiente';

    public const INFORME_ESTADO_PARCIAL = 'parcial';

    public const INFORME_ESTADO_OK = 'ok';

    public const INFORME_ESTADO_OBSERVACION = 'observacion';

    public const INFORME_ESTADO_ERROR = 'error';

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
        'informe_estado',
        'informe_resumen',
        'informe_procesado_at',
        'informe_usuario_id',
    ];

    protected $casts = [
        'periodo' => 'integer',
        'orden' => 'integer',
        'fecha_vigencia_desde' => 'date',
        'fecha_vigencia_hasta' => 'date',
        'fecha_tope_informe' => 'date',
        'fecha_proceso' => 'datetime',
        'observaciones' => 'array',
        'informe_resumen' => 'array',
        'informe_procesado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por_usuario_id');
    }

    public function informadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'informe_usuario_id');
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
