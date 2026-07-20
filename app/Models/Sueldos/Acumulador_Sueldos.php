<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Acumulador dinamico de liquidacion. Agrupa importes por tipo de concepto y
 * se lee en las formulas con acum("CODIGO").
 */
class Acumulador_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'acumulador_sueldos';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'descripcion',
        'tipos_incluye',
        'signo',
        'reservado',
        'activo',
        'orden',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'tipos_incluye' => 'array',
        'signo' => 'integer',
        'reservado' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function overrides()
    {
        return $this->hasMany(Concepto_Acumulador_Sueldos::class, 'acumulador_id');
    }

    /**
     * Devuelve true si un concepto de este tipo alimenta el acumulador.
     */
    public function incluyeTipo(?string $tipo): bool
    {
        $tipos = $this->tipos_incluye ?? [];

        return in_array((string) $tipo, $tipos, true);
    }
}
