<?php

declare(strict_types=1);

namespace App\Models\Contable;

use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sicore_Config extends Model
{
    protected $table = 'sicore_config';

    protected $fillable = [
        'codigo_impuesto',
        'codigo_regimen',
        'nombre',
        'descripcion',
        'criterio',
        'codigo_operacion',
        'concilia_con',
        'frecuencia',
        'quincena_1_desde',
        'quincena_1_hasta',
        'quincena_2_desde',
        'quincena_2_hasta',
        'concepto_retencion_sueldos',
        'concepto_devolucion_sueldos',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** @var array<string, string> */
    public static array $enumCriterio = [
        'compras_ganancias' => 'Compras — retenciones ganancias',
        'compras_iva' => 'Compras — retenciones IVA',
        'ventas_perc_iva' => 'Ventas — percepción IVA',
        'ventas_perc_no_categ' => 'Ventas — percepción no categorizada',
        'sueldos' => 'Sueldos — 4ta categoría',
    ];

    /** @var array<string, string> */
    public static array $enumConciliaCon = [
        'sicore' => 'Reporte SICORE',
        '4ta_categoria' => 'Reporte 4ta categoría',
    ];

    /** @var array<string, string> */
    public static array $enumFrecuencia = [
        'quincenal' => 'Quincenal',
        'mensual' => 'Mensual',
    ];

    public function cuentas(): HasMany
    {
        return $this->hasMany(Sicore_Config_Cuenta::class, 'sicore_config_id');
    }

    public function cuentasPorEmpresa(int $empresaId): HasMany
    {
        return $this->cuentas()->where('empresa_id', $empresaId);
    }
}
