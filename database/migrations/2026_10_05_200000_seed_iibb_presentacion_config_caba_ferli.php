<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: la pantalla de Ingresos Brutos no consulta Anita si no hay
 * iibb_presentacion_config. El seed original solo arma Buenos Aires con
 * cuentas 214010014/214010025, que en Ferli no existen. El agente es CABA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('iibb_presentacion_config') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('jurisdiccion', '901')
            ->orWhere('codigo', '901')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($provinciaId <= 0) {
            return;
        }

        $now = now();
        $items = [
            [
                'tipo' => 'retenciones',
                'nombre' => 'Retenciones CABA (AGIP)',
                'descripcion' => 'Retenciones IIBB practicadas a proveedores. Cuenta 213100017.',
                'frecuencia' => 'mensual',
                'cuenta_codigo' => '213100017',
            ],
            [
                'tipo' => 'percepciones',
                'nombre' => 'Percepciones CABA (AGIP)',
                'descripcion' => 'Percepciones IIBB en ventas. Cuenta 213100016.',
                'frecuencia' => 'mensual',
                'cuenta_codigo' => '213100016',
            ],
        ];

        foreach ($items as $item) {
            $cuentaCodigo = $item['cuenta_codigo'];
            unset($item['cuenta_codigo']);

            $configId = (int) (DB::table('iibb_presentacion_config')
                ->where('provincia_id', $provinciaId)
                ->where('tipo', $item['tipo'])
                ->value('id') ?? 0);

            if ($configId <= 0) {
                $configId = (int) DB::table('iibb_presentacion_config')->insertGetId(array_merge($item, [
                    'provincia_id' => $provinciaId,
                    'codigo_actividad_arba' => null,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            foreach (DB::table('empresa')->select('id')->orderBy('id')->get() as $empresa) {
                $cuentaId = (int) (DB::table('cuentacontable')
                    ->where('empresa_id', $empresa->id)
                    ->where('codigo', $cuentaCodigo)
                    ->value('id') ?? 0);
                if ($cuentaId <= 0) {
                    continue;
                }
                $ya = DB::table('iibb_presentacion_config_cuenta')
                    ->where('iibb_presentacion_config_id', $configId)
                    ->where('empresa_id', $empresa->id)
                    ->where('cuentacontable_id', $cuentaId)
                    ->exists();
                if ($ya) {
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
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('iibb_presentacion_config')) {
            return;
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('jurisdiccion', '901')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($provinciaId <= 0) {
            return;
        }

        $ids = DB::table('iibb_presentacion_config')
            ->where('provincia_id', $provinciaId)
            ->whereIn('tipo', ['retenciones', 'percepciones'])
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('iibb_presentacion_config_cuenta')
            ->whereIn('iibb_presentacion_config_id', $ids)
            ->delete();
        DB::table('iibb_presentacion_config')->whereIn('id', $ids)->delete();
    }
};
