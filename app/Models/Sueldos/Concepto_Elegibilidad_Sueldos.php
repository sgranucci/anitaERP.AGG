<?php

namespace App\Models\Sueldos;

use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Concepto_Elegibilidad_Sueldos extends Model
{
    protected $table = 'concepto_elegibilidad_sueldos';

    protected $fillable = [
        'concepto_id', 'campo', 'operador', 'valor', 'grupo_or',
        'activo', 'vigente_desde', 'vigente_hasta',
    ];

    protected $casts = [
        'concepto_id' => 'integer',
        'grupo_or' => 'integer',
        'activo' => 'boolean',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function campoLabel(): string
    {
        return ConceptoElegibilidadCatalogo::CAMPOS[$this->campo] ?? (string) $this->campo;
    }

    public function operadorLabel(): string
    {
        return ConceptoElegibilidadCatalogo::OPERADORES[$this->operador] ?? (string) $this->operador;
    }

    public function vigenciaLabel(): string
    {
        $desde = $this->vigente_desde ? $this->vigente_desde->format('d/m/Y') : '…';
        $hasta = $this->vigente_hasta ? $this->vigente_hasta->format('d/m/Y') : '∞';

        return $desde.' – '.$hasta;
    }
}
