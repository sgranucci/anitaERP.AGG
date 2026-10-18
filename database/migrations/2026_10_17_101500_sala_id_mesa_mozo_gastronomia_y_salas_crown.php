<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: mesas/mozos por sala (casino) además de empresa.
 * AGG: sala_id queda null; filtros por empresa sin cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mesa_gastronomia') && ! Schema::hasColumn('mesa_gastronomia', 'sala_id')) {
            Schema::table('mesa_gastronomia', function (Blueprint $table) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                $table->foreign('sala_id', 'fk_mesa_gastronomia_sala')
                    ->references('id')->on('sala')
                    ->nullOnDelete();
                $table->index(['empresa_id', 'sala_id'], 'idx_mesa_gastronomia_empresa_sala');
            });
        }

        if (Schema::hasTable('mozo_gastronomia') && ! Schema::hasColumn('mozo_gastronomia', 'sala_id')) {
            Schema::table('mozo_gastronomia', function (Blueprint $table) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                $table->foreign('sala_id', 'fk_mozo_gastronomia_sala')
                    ->references('id')->on('sala')
                    ->nullOnDelete();
                $table->index(['empresa_id', 'sala_id'], 'idx_mozo_gastronomia_empresa_sala');
            });
        }

        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('sala')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 1);
        $ahora = now();
        $salas = [
            ['codigo' => 'CIP', 'nombre' => 'Cipolletti'],
            ['codigo' => 'CAT', 'nombre' => 'Catriel'],
            ['codigo' => 'GRO', 'nombre' => 'Gral. Roca'],
            ['codigo' => 'CHO', 'nombre' => 'Choele Choel'],
            ['codigo' => 'VRE', 'nombre' => 'Villa Regina'],
            ['codigo' => 'VIE', 'nombre' => 'Viedma'],
            ['codigo' => 'LGR', 'nombre' => 'Las Grutas'],
            ['codigo' => 'EFA', 'nombre' => 'El Faro'],
            ['codigo' => 'CSA', 'nombre' => 'Cinco Saltos'],
        ];

        foreach ($salas as $sala) {
            $existe = DB::table('sala')->where('codigo', $sala['codigo'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('sala')->insert([
                'codigo' => $sala['codigo'],
                'nombre' => $sala['nombre'],
                'empresa_id' => $empresaId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mesa_gastronomia') && Schema::hasColumn('mesa_gastronomia', 'sala_id')) {
            Schema::table('mesa_gastronomia', function (Blueprint $table) {
                $table->dropForeign('fk_mesa_gastronomia_sala');
                $table->dropIndex('idx_mesa_gastronomia_empresa_sala');
                $table->dropColumn('sala_id');
            });
        }

        if (Schema::hasTable('mozo_gastronomia') && Schema::hasColumn('mozo_gastronomia', 'sala_id')) {
            Schema::table('mozo_gastronomia', function (Blueprint $table) {
                $table->dropForeign('fk_mozo_gastronomia_sala');
                $table->dropIndex('idx_mozo_gastronomia_empresa_sala');
                $table->dropColumn('sala_id');
            });
        }

        if (EntornoEmpresaSupport::esCrown() && Schema::hasTable('sala')) {
            DB::table('sala')->whereIn('codigo', [
                'CIP', 'CAT', 'GRO', 'CHO', 'VRE', 'VIE', 'LGR', 'EFA', 'CSA',
            ])->delete();
        }
    }
};
