<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salto premium reportes definibles: layouts, rangos, versiones, presentación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_contable_layout', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id')->nullable()->index();
            $table->string('codigo', 30);
            $table->string('nombre', 80);
            $table->boolean('es_default')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_contable_id', 'rd_layout_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
            $table->unique(['reporte_contable_id', 'codigo'], 'rd_layout_rep_codigo_uq');
        });

        Schema::create('reporte_contable_layout_columna', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_layout_id');
            $table->string('key', 40);
            $table->string('label', 80);
            $table->string('tipo', 20)->comment('actual|ytd|anio_ant|plan|var|var_pct');
            $table->unsignedInteger('orden')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('reporte_contable_layout_id', 'rd_layout_col_fk')
                ->references('id')->on('reporte_contable_layout')->cascadeOnDelete();
            $table->index(['reporte_contable_layout_id', 'orden'], 'rd_layout_col_orden_idx');
        });

        Schema::table('reporte_contable', function (Blueprint $table) {
            $table->unsignedBigInteger('layout_default_id')->nullable()->after('observaciones');
            $table->unsignedInteger('version_actual')->default(0)->after('layout_default_id');
            $table->timestamp('publicado_at')->nullable()->after('version_actual');
            $table->unsignedBigInteger('publicado_por')->nullable()->after('publicado_at');
        });

        Schema::create('reporte_contable_version', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->unsignedInteger('version');
            $table->string('nombre', 120)->nullable();
            $table->json('snapshot');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('reporte_contable_id', 'rd_ver_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
            $table->unique(['reporte_contable_id', 'version'], 'rd_ver_rep_version_uq');
        });

        Schema::table('reporte_contable_cuenta', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_hasta')->nullable()->after('codigo_cuenta');
        });

        Schema::table('reporte_contable_conjunto_cuenta', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_hasta')->nullable()->after('codigo_cuenta');
        });

        Schema::table('reporte_contable_rubro', function (Blueprint $table) {
            $table->char('lado_presentacion', 1)->nullable()->after('conjunto_id')->comment('D|H|null');
            $table->boolean('ocultar_si_cero')->default(false)->after('lado_presentacion');
        });

        $this->sembrarPresets();
    }

    private function sembrarPresets(): void
    {
        $now = now();
        $presets = [
            ['codigo' => 'ACTUAL', 'nombre' => 'Solo Actual', 'orden' => 1, 'cols' => [
                ['key' => 'actual', 'label' => 'Actual', 'tipo' => 'actual', 'orden' => 1],
            ]],
            ['codigo' => 'YTD', 'nombre' => 'YTD ejercicio', 'orden' => 2, 'cols' => [
                ['key' => 'ytd', 'label' => 'YTD', 'tipo' => 'ytd', 'orden' => 1],
            ]],
            ['codigo' => 'ACT_PLAN_VAR', 'nombre' => 'Actual / Plan / Var / %', 'orden' => 3, 'cols' => [
                ['key' => 'actual', 'label' => 'Actual', 'tipo' => 'actual', 'orden' => 1],
                ['key' => 'plan', 'label' => 'Plan', 'tipo' => 'plan', 'orden' => 2],
                ['key' => 'var', 'label' => 'Var', 'tipo' => 'var', 'orden' => 3],
                ['key' => 'var_pct', 'label' => 'Var %', 'tipo' => 'var_pct', 'orden' => 4],
            ]],
            ['codigo' => 'ACT_YTD_AA', 'nombre' => 'Actual / YTD / Año ant.', 'orden' => 4, 'cols' => [
                ['key' => 'actual', 'label' => 'Actual', 'tipo' => 'actual', 'orden' => 1],
                ['key' => 'ytd', 'label' => 'YTD', 'tipo' => 'ytd', 'orden' => 2],
                ['key' => 'anio_ant', 'label' => 'Año ant.', 'tipo' => 'anio_ant', 'orden' => 3],
            ]],
            ['codigo' => 'FULL_GERENCIAL', 'nombre' => 'Gerencial completo', 'orden' => 5, 'cols' => [
                ['key' => 'actual', 'label' => 'Actual', 'tipo' => 'actual', 'orden' => 1],
                ['key' => 'ytd', 'label' => 'YTD', 'tipo' => 'ytd', 'orden' => 2],
                ['key' => 'anio_ant', 'label' => 'Año ant.', 'tipo' => 'anio_ant', 'orden' => 3],
                ['key' => 'plan', 'label' => 'Plan', 'tipo' => 'plan', 'orden' => 4],
                ['key' => 'var', 'label' => 'Var', 'tipo' => 'var', 'orden' => 5],
                ['key' => 'var_pct', 'label' => 'Var %', 'tipo' => 'var_pct', 'orden' => 6],
            ]],
        ];

        foreach ($presets as $p) {
            $layoutId = DB::table('reporte_contable_layout')->insertGetId([
                'reporte_contable_id' => null,
                'codigo' => $p['codigo'],
                'nombre' => $p['nombre'],
                'es_default' => $p['codigo'] === 'ACT_PLAN_VAR',
                'activo' => true,
                'orden' => $p['orden'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($p['cols'] as $c) {
                DB::table('reporte_contable_layout_columna')->insert([
                    'reporte_contable_layout_id' => $layoutId,
                    'key' => $c['key'],
                    'label' => $c['label'],
                    'tipo' => $c['tipo'],
                    'orden' => $c['orden'],
                    'meta' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('reporte_contable_rubro', function (Blueprint $table) {
            $table->dropColumn(['lado_presentacion', 'ocultar_si_cero']);
        });
        Schema::table('reporte_contable_conjunto_cuenta', function (Blueprint $table) {
            $table->dropColumn('codigo_hasta');
        });
        Schema::table('reporte_contable_cuenta', function (Blueprint $table) {
            $table->dropColumn('codigo_hasta');
        });
        Schema::dropIfExists('reporte_contable_version');
        Schema::table('reporte_contable', function (Blueprint $table) {
            $table->dropColumn(['layout_default_id', 'version_actual', 'publicado_at', 'publicado_por']);
        });
        Schema::dropIfExists('reporte_contable_layout_columna');
        Schema::dropIfExists('reporte_contable_layout');
    }
};
