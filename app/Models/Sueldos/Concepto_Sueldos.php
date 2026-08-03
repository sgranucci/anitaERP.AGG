<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'tipo',
        'suma_a',
        'momento',
        'factor',
        'formula',
        'formula_cantidad',
        'formula_valor',
        'va_recibo',
        'mes_retroactivo',
        'leyenda_recibo',
        'concepto_afip',
        'rubro_costo_laboral',
        'unidad_medida',
        'activo',
        'orden',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'factor' => 'decimal:4',
        'va_recibo' => 'boolean',
        'mes_retroactivo' => 'integer',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function lineasFormula()
    {
        return $this->hasMany(Concepto_Formula_Sueldos::class, 'concepto_id')
            ->orderBy('nro_linea');
    }

    public function acumuladoresOverride()
    {
        return $this->hasMany(Concepto_Acumulador_Sueldos::class, 'concepto_id');
    }

    public function reglasElegibilidad()
    {
        return $this->hasMany(Concepto_Elegibilidad_Sueldos::class, 'concepto_id');
    }
}
