<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Support\Sueldos\ContratoDocumentoTipos;
use App\Support\Sueldos\ContratoModalidadSijp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Contrato_Plantilla_Asignacion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'contrato_plantilla_asignacion_sueldos';

    protected $fillable = [
        'tipo',
        'modalidad',
        'sindicato_id',
        'empresa_id',
        'contrato_plantilla_id',
        'prioridad',
        'activo',
    ];

    protected $casts = [
        'sindicato_id' => 'integer',
        'empresa_id' => 'integer',
        'contrato_plantilla_id' => 'integer',
        'prioridad' => 'integer',
        'activo' => 'boolean',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Contrato_Plantilla_Sueldos::class, 'contrato_plantilla_id');
    }

    public function sindicato(): BelongsTo
    {
        return $this->belongsTo(Sindicato_Sueldos::class, 'sindicato_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return ContratoDocumentoTipos::label((string) $this->tipo);
    }

    public function getModalidadLabelAttribute(): string
    {
        return ContratoModalidadSijp::label($this->modalidad);
    }
}
