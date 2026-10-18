<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: depósitos por sala (código repetible entre salas).
 * AGG/otros: sala_id queda null; unique app sigue por empresa+código.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('depmae')) {
            return;
        }

        if (! Schema::hasColumn('depmae', 'sala_id')) {
            Schema::table('depmae', function (Blueprint $table) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                $table->index(['empresa_id', 'sala_id'], 'idx_depmae_empresa_sala');
                $table->index(['codigo', 'sala_id'], 'idx_depmae_codigo_sala');
            });

            if (Schema::hasTable('sala')) {
                Schema::table('depmae', function (Blueprint $table) {
                    $table->foreign('sala_id', 'fk_depmae_sala')
                        ->references('id')->on('sala')
                        ->nullOnDelete();
                });
            }
        }

        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('sala')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 1);
        $ahora = now();

        if (! DB::table('sala')->where('codigo', 'AC')->exists()) {
            DB::table('sala')->insert([
                'codigo' => 'AC',
                'nombre' => 'Administración central',
                'empresa_id' => $empresaId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $salaAcId = (int) DB::table('sala')->where('codigo', 'AC')->value('id');
        if ($salaAcId <= 0) {
            return;
        }

        // Duplicados por empresa 2 (mismo maestro Anita AC) sin uso operativo.
        $idsEmp2 = DB::table('depmae')->where('empresa_id', 2)->pluck('id');
        if ($idsEmp2->isNotEmpty()) {
            DB::table('depmae')->whereIn('id', $idsEmp2)->delete();
        }

        DB::table('depmae')
            ->whereNull('sala_id')
            ->update(['sala_id' => $salaAcId, 'updated_at' => $ahora]);
    }

    public function down(): void
    {
        if (EntornoEmpresaSupport::esCrown() && Schema::hasTable('sala')) {
            $salaAcId = (int) (DB::table('sala')->where('codigo', 'AC')->value('id') ?? 0);
            if ($salaAcId > 0 && Schema::hasTable('depmae') && Schema::hasColumn('depmae', 'sala_id')) {
                DB::table('depmae')->where('sala_id', $salaAcId)->update(['sala_id' => null]);
            }
            DB::table('sala')->where('codigo', 'AC')->delete();
        }

        if (! Schema::hasTable('depmae') || ! Schema::hasColumn('depmae', 'sala_id')) {
            return;
        }

        Schema::table('depmae', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_depmae_sala');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('idx_depmae_empresa_sala');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('idx_depmae_codigo_sala');
            } catch (\Throwable) {
            }
            $table->dropColumn('sala_id');
        });
    }
};
