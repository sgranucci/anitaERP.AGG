<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('suss_presentacion_config')->count() > 0) {
            return;
        }

        $now = now();
        $configId = (int) DB::table('suss_presentacion_config')->insertGetId([
            'nombre' => 'Retenciones SUSS — F2004',
            'descripcion' => 'Presentación SIRE F.2004 (impuesto 353). Fuente Anita retsmov / ERP pagoproveedor_retencion tipo S. Cuenta 214010015.',
            'codigo_impuesto' => 353,
            'codigo_regimen' => null,
            'frecuencia' => 'quincenal',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cuentaCodigo = '214010015';
        foreach (DB::table('empresa')->select('id')->get() as $empresa) {
            $cuentaId = (int) (DB::table('cuentacontable')
                ->where('empresa_id', $empresa->id)
                ->where('codigo', $cuentaCodigo)
                ->value('id') ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            DB::table('suss_presentacion_config_cuenta')->insert([
                'suss_presentacion_config_id' => $configId,
                'empresa_id' => $empresa->id,
                'cuentacontable_id' => $cuentaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('suss_presentacion_config_cuenta')->delete();
        DB::table('suss_presentacion_config')->delete();
    }
};
