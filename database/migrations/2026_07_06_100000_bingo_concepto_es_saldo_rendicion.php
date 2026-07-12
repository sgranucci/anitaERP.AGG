<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca el concepto que muestra el saldo/depósito final de la rendición.
 */
return new class extends Migration
{
    /** @var list<int> */
    private array $empresaIds = [1, 2, 3];

    public function up(): void
    {
        if (! Schema::hasTable('bingo_concepto_rendicion')) {
            return;
        }

        if (! Schema::hasColumn('bingo_concepto_rendicion', 'es_saldo_rendicion')) {
            Schema::table('bingo_concepto_rendicion', function (Blueprint $table) {
                $table->boolean('es_saldo_rendicion')->default(false)->after('monto_fijo');
            });
        }

        $now = now();

        foreach ($this->empresaIds as $empresaId) {
            if (! DB::table('empresa')->where('id', $empresaId)->exists()) {
                continue;
            }

            $existe = DB::table('bingo_concepto_rendicion')
                ->where('empresa_id', $empresaId)
                ->where('codigo', 'DEPOSITO')
                ->exists();

            if ($existe) {
                DB::table('bingo_concepto_rendicion')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', 'DEPOSITO')
                    ->update([
                        'es_saldo_rendicion' => true,
                        'detalle' => 'Depósito (saldo rendición)',
                        'base_calculo' => 'manual',
                        'porcentaje' => null,
                        'monto_fijo' => null,
                        'signo' => '-',
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('bingo_concepto_rendicion')->insert([
                'empresa_id' => $empresaId,
                'codigo' => 'DEPOSITO',
                'signo' => '-',
                'detalle' => 'Depósito (saldo rendición)',
                'porcentaje' => null,
                'base_calculo' => 'manual',
                'monto_fijo' => null,
                'es_saldo_rendicion' => true,
                'orden' => 160,
                'estado' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bingo_concepto_rendicion')) {
            return;
        }

        DB::table('bingo_concepto_rendicion')
            ->whereIn('empresa_id', $this->empresaIds)
            ->where('codigo', 'DEPOSITO')
            ->where('es_saldo_rendicion', true)
            ->delete();

        if (Schema::hasColumn('bingo_concepto_rendicion', 'es_saldo_rendicion')) {
            Schema::table('bingo_concepto_rendicion', function (Blueprint $table) {
                $table->dropColumn('es_saldo_rendicion');
            });
        }
    }
};
