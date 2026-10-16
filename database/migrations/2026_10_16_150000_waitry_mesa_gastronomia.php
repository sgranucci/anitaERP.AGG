<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de mesas Waitry (servicio + tableId) y asignación en cada PC de gastronomía.
 * El alta de Mood queda para cuando Waitry cree el servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('waitry_mesa_gastronomia')) {
            Schema::create('waitry_mesa_gastronomia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('servicio', 40);
                $table->string('nombre', 80);
                $table->unsignedInteger('table_id');
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['empresa_id', 'table_id'], 'waitry_mesa_gastronomia_emp_table_uq');
                $table->foreign('empresa_id', 'fk_waitry_mesa_gastronomia_empresa')
                    ->references('id')->on('empresa');
            });
        }

        if (Schema::hasTable('configuracion_puntoventa_gastronomia')
            && ! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'waitry_mesa_gastronomia_id')) {
            Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
                $table->unsignedBigInteger('waitry_mesa_gastronomia_id')->nullable()->after('waitry_habilitado');
                $table->foreign('waitry_mesa_gastronomia_id', 'fk_cfg_pv_gastro_waitry_mesa')
                    ->references('id')->on('waitry_mesa_gastronomia')
                    ->nullOnDelete();
            });
        }

        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('waitry_mesa_gastronomia')) {
            return;
        }

        $filas = [
            ['empresa_id' => 1, 'servicio' => 'Mostrador', 'nombre' => 'Mesa 1', 'table_id' => 101066],
            ['empresa_id' => 1, 'servicio' => 'VIP', 'nombre' => 'Mesa 1', 'table_id' => 102234],
            ['empresa_id' => 2, 'servicio' => 'Mostrador', 'nombre' => 'Mesa 1', 'table_id' => 101067],
            ['empresa_id' => 2, 'servicio' => 'VIP', 'nombre' => 'Mesa 1', 'table_id' => 101340],
            ['empresa_id' => 3, 'servicio' => 'Mostrador', 'nombre' => 'Mesa 1', 'table_id' => 101068],
            ['empresa_id' => 3, 'servicio' => 'VIP', 'nombre' => 'Mesa 1', 'table_id' => 101695],
        ];

        foreach ($filas as $fila) {
            if (! DB::table('empresa')->where('id', $fila['empresa_id'])->exists()) {
                continue;
            }

            $existe = DB::table('waitry_mesa_gastronomia')
                ->where('empresa_id', $fila['empresa_id'])
                ->where('table_id', $fila['table_id'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('waitry_mesa_gastronomia')->insert($fila + [
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'waitry_mesa_gastronomia_id')) {
            return;
        }

        $mostradores = DB::table('waitry_mesa_gastronomia')
            ->where('servicio', 'Mostrador')
            ->get(['id', 'empresa_id']);

        foreach ($mostradores as $mesa) {
            DB::table('configuracion_puntoventa_gastronomia')
                ->where('empresa_id', $mesa->empresa_id)
                ->whereNull('waitry_mesa_gastronomia_id')
                ->update(['waitry_mesa_gastronomia_id' => $mesa->id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('configuracion_puntoventa_gastronomia')
            && Schema::hasColumn('configuracion_puntoventa_gastronomia', 'waitry_mesa_gastronomia_id')) {
            Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
                $table->dropForeign('fk_cfg_pv_gastro_waitry_mesa');
                $table->dropColumn('waitry_mesa_gastronomia_id');
            });
        }

        Schema::dropIfExists('waitry_mesa_gastronomia');
    }
};
