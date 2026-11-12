<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use App\Support\Sueldos\ContratoDocumentoTipos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Contrato_Emision_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_contrato_emision_sueldos';

    protected $fillable = [
        'empleado_id',
        'empleado_contrato_id',
        'tipo',
        'contrato_plantilla_id',
        'fecha_emision',
        'liquidacion',
        'archivo_path',
        'archivo_nombre',
        'usuario_id',
        'datos_merge',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'empleado_contrato_id' => 'integer',
        'contrato_plantilla_id' => 'integer',
        'fecha_emision' => 'date',
        'liquidacion' => 'integer',
        'usuario_id' => 'integer',
        'datos_merge' => 'array',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Empleado_Contrato_Sueldos::class, 'empleado_contrato_id');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Contrato_Plantilla_Sueldos::class, 'contrato_plantilla_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return ContratoDocumentoTipos::label((string) $this->tipo);
    }

    public function rutaAbsoluta(): string
    {
        return storage_path('app/'.$this->archivo_path);
    }
}
