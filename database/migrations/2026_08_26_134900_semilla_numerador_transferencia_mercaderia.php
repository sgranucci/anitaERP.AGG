<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semilla stock.transferencia en sistema_numerador.
 * Piso = MAX(id) de transferencia_mercaderia (los TR-YmdHis no son correlativos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sistema_numerador') || ! Schema::hasTable('empresa')) {
            return;
        }

        $codigo = 'stock.transferencia';
        $empresaId = $this->empresaIdNumerador();
        if ($empresaId <= 0) {
            return;
        }

        $piso = 0;
        if (Schema::hasTable('transferencia_mercaderia')) {
            $piso = (int) DB::table('transferencia_mercaderia')->max('id');
        }

        $ahora = now();
        $existe = DB::table('sistema_numerador')
            ->where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->exists();

        if ($existe) {
            DB::table('sistema_numerador')
                ->where('codigo', $codigo)
                ->where('empresa_id', $empresaId)
                ->update([
                    'ultimo_numero' => DB::raw('GREATEST(ultimo_numero, '.$piso.')'),
                    'updated_at' => $ahora,
                ]);

            return;
        }

        DB::table('sistema_numerador')->insert([
            'codigo' => $codigo,
            'nombre' => 'Transferencia de mercadería',
            'empresa_id' => $empresaId,
            'modulo' => 'stock',
            'ultimo_numero' => $piso,
            'anita_sistema' => null,
            'anita_fuente' => null,
            'anita_clave' => null,
            'activo' => true,
            'observacion' => 'Secuencia única global (el código TR- es unique). Semilla = MAX(id).',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sistema_numerador')) {
            return;
        }

        DB::table('sistema_numerador')
            ->where('codigo', 'stock.transferencia')
            ->delete();
    }

    private function empresaIdNumerador(): int
    {
        $cfg = 1;
        if (DB::table('empresa')->where('id', $cfg)->exists()) {
            return $cfg;
        }

        return (int) (DB::table('empresa')->orderBy('id')->value('id') ?: 0);
    }
};
