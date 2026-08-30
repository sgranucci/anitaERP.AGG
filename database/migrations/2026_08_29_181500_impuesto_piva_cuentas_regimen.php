<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('impuesto')) {
            return;
        }

        $ahora = now();
        $impuestoId = (int) (DB::table('impuesto')->where('codigo', 'PIVA')->value('id') ?? 0);
        if ($impuestoId === 0) {
            $impuestoId = (int) DB::table('impuesto')->insertGetId([
                'nombre' => 'Percepcion IVA RI RG 5329',
                'valor' => 3,
                'fechavigencia' => '2000-01-01',
                'codigo' => 'PIVA',
                'codigoarca' => '1',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        if (Schema::hasTable('regimen_percepcion')) {
            DB::table('regimen_percepcion')->where('codigo', 'PIVA')->update([
                'impuesto_id' => $impuestoId,
                'updated_at' => $ahora,
            ]);
            $pncId = DB::table('impuesto')->where('codigo', 'PNC')->value('id');
            if ($pncId) {
                DB::table('regimen_percepcion')->where('codigo', 'PNC')->whereNull('impuesto_id')->update([
                    'impuesto_id' => (int) $pncId,
                    'updated_at' => $ahora,
                ]);
            }
        }

        if (! Schema::hasTable('impuesto_cuentacontable') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $codigoCuenta = trim((string) config('facturacion.CUENTACONTABLE_PERCEPCION_IVA', ''));
        if ($codigoCuenta === '') {
            $codigoCuenta = '211290000';
        }

        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 0);
        if ($usuarioId <= 0) {
            return;
        }

        $cuentas = DB::table('cuentacontable')
            ->where('codigo', $codigoCuenta)
            ->get(['id', 'empresa_id']);

        foreach ($cuentas as $cuenta) {
            $empresaId = (int) $cuenta->empresa_id;
            $cuentaId = (int) $cuenta->id;
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }
            $existe = DB::table('impuesto_cuentacontable')
                ->where('impuesto_id', $impuestoId)
                ->where('empresa_id', $empresaId)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('impuesto_cuentacontable')->insert([
                'impuesto_id' => $impuestoId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'creousuario_id' => $usuarioId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        $impuestoId = (int) (DB::table('impuesto')->where('codigo', 'PIVA')->value('id') ?? 0);
        if ($impuestoId > 0 && Schema::hasTable('impuesto_cuentacontable')) {
            DB::table('impuesto_cuentacontable')->where('impuesto_id', $impuestoId)->delete();
        }
        if (Schema::hasTable('regimen_percepcion')) {
            DB::table('regimen_percepcion')->where('codigo', 'PIVA')->update([
                'impuesto_id' => null,
                'updated_at' => now(),
            ]);
        }
        if ($impuestoId > 0) {
            DB::table('impuesto')->where('id', $impuestoId)->delete();
        }
    }
};
