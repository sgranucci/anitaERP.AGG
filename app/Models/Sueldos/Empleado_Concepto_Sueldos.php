<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Empleado_Concepto_Sueldos extends Model
{
    protected $table = 'empleado_concepto_sueldos';

    protected $fillable = [
        'empleado_id', 'concepto_id', 'accion',
        'fecha_desde', 'fecha_hasta', 'origen', 'usuario_id', 'observacion',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'concepto_id' => 'integer',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'usuario_id' => 'integer',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function accionLabel(): string
    {
        return ConceptoElegibilidadCatalogo::ACCIONES[$this->accion] ?? (string) $this->accion;
    }
}
