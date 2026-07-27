<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('iibb_presentacion_config')->count() > 0) {
            return;
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('nombre', 'Buenos Aires')
            ->orWhere('codigo', '2')
            ->orWhere('codigoexterno', '2')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($provinciaId <= 0) {
            return;
        }

        $now = now();
        $items = [
            [
                'provincia_id' => $provinciaId,
                'tipo' => 'retenciones',
                'nombre' => 'Retenciones ARBA — Buenos Aires',
                'descripcion' => 'Opción 7 Anita (p-ingbruto). Cuenta retención IIBB a terceros.',
                'codigo_actividad_arba' => 6,
                'frecuencia' => 'quincenal',
                'cuenta_codigo' => '214010014',
            ],
            [
                'provincia_id' => $provinciaId,
                'tipo' => 'percepciones',
                'nombre' => 'Percepciones ARBA — Buenos Aires',
                'descripcion' => 'Opción 8 Anita (p-ingbruto). Cuenta percepción IIBB a terceros.',
                'codigo_actividad_arba' => 7,
                'frecuencia' => 'quincenal',
                'cuenta_codigo' => '214010025',
            ],
        ];

        foreach ($items as $item) {
            $cuentaCodigo = $item['cuenta_codigo'];
            unset($item['cuenta_codigo']);
            $item['activo'] = true;
            $item['created_at'] = $now;
            $item['updated_at'] = $now;

            $configId = (int) DB::table('iibb_presentacion_config')->insertGetId($item);

            foreach (DB::table('empresa')->select('id')->get() as $empresa) {
                $cuentaId = (int) (DB::table('cuentacontable')
                    ->where('empresa_id', $empresa->id)
                    ->where('codigo', $cuentaCodigo)
                    ->value('id') ?? 0);
                if ($cuentaId <= 0) {
                    continue;
                }
                DB::table('iibb_presentacion_config_cuenta')->insert([
                    'iibb_presentacion_config_id' => $configId,
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
        DB::table('iibb_presentacion_config_cuenta')->delete();
        DB::table('iibb_presentacion_config')->delete();
    }
};
