<?php

namespace App\Models\Contable;

use App\Support\Contable\PeriodoContableCierreSupport;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AperturaPeriodoContable extends Model
{
    protected $table = 'contable_apertura_periodo';

    protected $fillable = [
        'empresa_id',
        'usuario_solicitante_id',
        'usuario_habilitado_id',
        'usuario_aprobador_id',
        'fecha_operacion_desde',
        'fecha_operacion_hasta',
        'alcance',
        'duracion_cantidad',
        'duracion_unidad',
        'inicio_en',
        'vence_en',
        'estado',
        'motivo',
        'observacion_aprobacion',
        'aviso_habilitacion_enviado_en',
        'recordatorio_vencimiento_enviado_en',
        'aviso_cierre_enviado_en',
    ];

    protected $casts = [
        'fecha_operacion_desde' => 'date',
        'fecha_operacion_hasta' => 'date',
        'inicio_en' => 'datetime',
        'vence_en' => 'datetime',
        'aviso_habilitacion_enviado_en' => 'datetime',
        'recordatorio_vencimiento_enviado_en' => 'datetime',
        'aviso_cierre_enviado_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_solicitante_id');
    }

    public function habilitado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_habilitado_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_aprobador_id');
    }

    public function etiquetaAlcance(): string
    {
        return PeriodoContableCierreSupport::etiquetaAlcance($this->alcance ?? '');
    }

    public function etiquetaDuracion(): string
    {
        return PeriodoContableCierreSupport::etiquetaDuracion(
            (int) $this->duracion_cantidad,
            (string) ($this->duracion_unidad ?? 'horas')
        );
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'activa'
            && $this->inicio_en !== null
            && $this->vence_en !== null
            && now()->gte($this->inicio_en)
            && now()->lt($this->vence_en);
    }
}
