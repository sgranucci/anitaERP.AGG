<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Regla: en liquidación final, unir el grupo de conceptos según empresa + sindicato + motivo.
 */
class Grupo_Liq_Final_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'grupo_liq_final_sueldos';

    protected $fillable = [
        'empresa_id',
        'sindicato_id',
        'motivoegreso_id',
        'motivo_key',
        'grupo_concepto_id',
        'activo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'sindicato_id' => 'integer',
        'motivoegreso_id' => 'integer',
        'motivo_key' => 'integer',
        'grupo_concepto_id' => 'integer',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            $row->motivo_key = $row->motivoegreso_id ? (int) $row->motivoegreso_id : 0;
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sindicato(): BelongsTo
    {
        return $this->belongsTo(Sindicato_Sueldos::class, 'sindicato_id');
    }

    public function motivoegreso(): BelongsTo
    {
        return $this->belongsTo(Motivoegreso_Sueldos::class, 'motivoegreso_id');
    }

    public function grupoConcepto(): BelongsTo
    {
        return $this->belongsTo(Grupo_Concepto_Sueldos::class, 'grupo_concepto_id');
    }
}
