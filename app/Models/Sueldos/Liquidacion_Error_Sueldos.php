<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

/**
 * Error u omisión registrada al calcular una corrida (por empleado / concepto).
 */
class Liquidacion_Error_Sueldos extends Model
{
    protected $table = 'liquidacion_error_sueldos';

    protected $fillable = [
        'liquidacion_id',
        'empleado_id',
        'legajo',
        'apellido_nombre',
        'concepto_id',
        'concepto_codigo',
        'concepto_descripcion',
        'formula_pos',
        'formula_campo',
        'etapa',
        'mensaje',
    ];

    protected $casts = [
        'liquidacion_id' => 'integer',
        'empleado_id' => 'integer',
        'legajo' => 'integer',
        'concepto_id' => 'integer',
        'concepto_codigo' => 'integer',
        'formula_pos' => 'integer',
    ];

    public const ETAPAS = [
        'formula' => 'Fórmula',
        'empleado' => 'Empleado',
        'recibo' => 'Recibo',
        'omitido' => 'Omitido',
    ];

    public function liquidacion()
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function concepto()
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function etapaLabel(): string
    {
        return self::ETAPAS[$this->etapa] ?? (string) $this->etapa;
    }

    public function formulaPosicion(): int
    {
        if ($this->formula_pos !== null && (int) $this->formula_pos >= 0) {
            return (int) $this->formula_pos;
        }

        return \App\Support\Sueldos\Formula\FormulaException::extraerPosicion((string) $this->mensaje);
    }

    public function formulaCampoId(): string
    {
        $campo = (string) ($this->formula_campo ?: 'formula');

        return in_array($campo, ['formula', 'formula_cantidad', 'formula_valor'], true)
            ? $campo
            : 'formula';
    }
}
