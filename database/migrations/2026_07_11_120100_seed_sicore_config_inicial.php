<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sicore_config')->count() > 0) {
            return;
        }

        $now = now();
        $items = [
            [
                'codigo_impuesto' => 217,
                'codigo_regimen' => null,
                'nombre' => 'Ret. impto. gcias. a 3ros (compras)',
                'descripcion' => 'Retenciones de ganancias en pagos a proveedores (retmov)',
                'criterio' => 'compras_ganancias',
                'codigo_operacion' => 1,
                'concilia_con' => 'sicore',
                'frecuencia' => 'quincenal',
                'quincena_1_desde' => 1,
                'quincena_1_hasta' => 15,
                'quincena_2_desde' => 16,
                'quincena_2_hasta' => 31,
                'cuenta_codigo' => '214010013',
            ],
            [
                'codigo_impuesto' => 767,
                'codigo_regimen' => null,
                'nombre' => 'Retención IVA compras (FC M)',
                'descripcion' => 'Retenciones de IVA en comprobantes de compra (retimov)',
                'criterio' => 'compras_iva',
                'codigo_operacion' => 1,
                'concilia_con' => 'sicore',
                'frecuencia' => 'quincenal',
                'quincena_1_desde' => 1,
                'quincena_1_hasta' => 15,
                'quincena_2_desde' => 16,
                'quincena_2_hasta' => 31,
                'cuenta_codigo' => '214010021',
            ],
            [
                'codigo_impuesto' => 767,
                'codigo_regimen' => 493,
                'nombre' => 'Percepción IVA ventas',
                'descripcion' => 'Percepciones de IVA en facturas de venta (venta_impuesto)',
                'criterio' => 'ventas_perc_iva',
                'codigo_operacion' => 2,
                'concilia_con' => 'sicore',
                'frecuencia' => 'quincenal',
                'quincena_1_desde' => 1,
                'quincena_1_hasta' => 15,
                'quincena_2_desde' => 16,
                'quincena_2_hasta' => 31,
                'cuenta_codigo' => null,
            ],
            [
                'codigo_impuesto' => 767,
                'codigo_regimen' => 493,
                'nombre' => 'Percepción no categorizada ventas',
                'descripcion' => 'Percepciones no categorizadas en ventas (venta_impuesto)',
                'criterio' => 'ventas_perc_no_categ',
                'codigo_operacion' => 2,
                'concilia_con' => 'sicore',
                'frecuencia' => 'quincenal',
                'quincena_1_desde' => 1,
                'quincena_1_hasta' => 15,
                'quincena_2_desde' => 16,
                'quincena_2_hasta' => 31,
                'cuenta_codigo' => null,
            ],
            [
                'codigo_impuesto' => 787,
                'codigo_regimen' => 160,
                'nombre' => 'Retención ganancias 4ta categoría (sueldos)',
                'descripcion' => 'Retenciones de ganancias sobre sueldos (auxrec)',
                'criterio' => 'sueldos',
                'codigo_operacion' => 1,
                'concilia_con' => '4ta_categoria',
                'frecuencia' => 'mensual',
                'quincena_1_desde' => null,
                'quincena_1_hasta' => null,
                'quincena_2_desde' => null,
                'quincena_2_hasta' => null,
                'concepto_retencion_sueldos' => null,
                'concepto_devolucion_sueldos' => null,
                'cuenta_codigo' => '214010008',
            ],
        ];

        foreach ($items as $item) {
            $cuentaCodigo = $item['cuenta_codigo'] ?? null;
            unset($item['cuenta_codigo']);

            $item['activo'] = true;
            $item['created_at'] = $now;
            $item['updated_at'] = $now;

            $configId = (int) DB::table('sicore_config')->insertGetId($item);

            if ($cuentaCodigo === null || $cuentaCodigo === '') {
                continue;
            }

            $empresas = DB::table('empresa')->select('id')->get();
            foreach ($empresas as $empresa) {
                $cuentaId = (int) (DB::table('cuentacontable')
                    ->where('empresa_id', $empresa->id)
                    ->where('codigo', $cuentaCodigo)
                    ->value('id') ?? 0);

                if ($cuentaId <= 0) {
                    continue;
                }

                DB::table('sicore_config_cuenta')->insert([
                    'sicore_config_id' => $configId,
                    'empresa_id' => $empresa->id,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('sicore_config_cuenta')->delete();
        DB::table('sicore_config')->delete();
    }
};
