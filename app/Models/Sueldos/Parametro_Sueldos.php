<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Parametro global de liquidacion (tope SIPA, minimo, alicuota...). Sus valores
 * viven en parametro_valor_sueldos con vigencia temporal. Se lee con param().
 */
class Parametro_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'parametro_sueldos';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'descripcion',
        'tipo',
        'unidad',
        'activo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'activo' => 'boolean',
    ];

    public const TIPOS = [
        'numero' => 'Número',
        'texto' => 'Texto',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function valores()
    {
        return $this->hasMany(Parametro_Valor_Sueldos::class, 'parametro_id')
            ->orderByDesc('fecha_vigencia');
    }

    /**
     * Valor vigente a una fecha dada (o null si no hay vigencia aplicable).
     */
    public function valorVigente(string $fecha): ?Parametro_Valor_Sueldos
    {
        return $this->valores()
            ->where('fecha_vigencia', '<=', $fecha)
            ->orderByDesc('fecha_vigencia')
            ->first();
    }
}
