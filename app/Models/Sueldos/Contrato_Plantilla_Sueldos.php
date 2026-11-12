<?php

namespace App\Models\Sueldos;

use App\Support\Sueldos\ContratoDocumentoTipos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Contrato_Plantilla_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'contrato_plantilla_sueldos';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'archivo_path',
        'archivo_nombre',
        'version',
        'activo',
        'observacion',
    ];

    protected $casts = [
        'version' => 'integer',
        'activo' => 'boolean',
    ];

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Contrato_Plantilla_Asignacion_Sueldos::class, 'contrato_plantilla_id');
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
