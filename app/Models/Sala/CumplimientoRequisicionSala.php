<?php

namespace App\Models\Sala;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CumplimientoRequisicionSala extends Model
{
    public const ESTADO_ACTIVO = 'A';

    public const ESTADO_REVERTIDO = 'R';

    protected $table = 'cumplimiento_requisicion_sala';

    protected $fillable = [
        'numero',
        'fecha',
        'usuario_id',
        'empresa_id',
        'leyenda',
        'estado',
        'revertido_por_id',
        'revertido_en',
        'observacion_reversion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'revertido_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function revertidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revertido_por_id');
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(CumplimientoRequisicionSalaArticulo::class, 'cumplimiento_requisicion_sala_id');
    }

    public function transferencias(): HasMany
    {
        return $this->hasMany(CumplimientoRequisicionSalaTransferencia::class, 'cumplimiento_requisicion_sala_id');
    }

    public function estaActivo(): bool
    {
        return (string) $this->estado === self::ESTADO_ACTIVO;
    }
}
