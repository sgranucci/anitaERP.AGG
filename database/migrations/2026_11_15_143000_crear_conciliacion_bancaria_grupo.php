<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupos de conciliación multi-match (1:N, N:1, N:N).
 * Los pares 1:1 históricos siguen en conciliacion_bancaria_par.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_grupo')) {
            Schema::create('conciliacion_bancaria_grupo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->decimal('importe_contable', 18, 2)->default(0);
                $table->decimal('importe_banco', 18, 2)->default(0);
                $table->string('modo', 30)->default('manual_multi');
                $table->unsignedBigInteger('conciliado_por_usuario_id')->nullable();
                $table->timestamp('conciliado_at')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_conc_banc_grp_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('cuentacaja_id', 'fk_conc_banc_grp_cuentacaja')
                    ->references('id')->on('cuentacaja')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->index(['cuentacaja_id', 'conciliado_at'], 'idx_conc_banc_grp_cuenta_fecha');
            });
        }

        if (! Schema::hasTable('conciliacion_bancaria_grupo_linea')) {
            Schema::create('conciliacion_bancaria_grupo_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('grupo_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->string('lado', 1);
                $table->string('hash', 64);
                $table->json('movimiento_json');
                $table->date('fecha')->nullable();
                $table->decimal('importe', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('grupo_id', 'fk_conc_banc_grp_lin_grupo')
                    ->references('id')->on('conciliacion_bancaria_grupo')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->unique(['cuentacaja_id', 'lado', 'hash'], 'uq_conc_banc_grp_lin_hash');
                $table->index(['grupo_id', 'lado'], 'idx_conc_banc_grp_lin_grupo_lado');
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_ejecucion')
            && ! Schema::hasColumn('conciliacion_bancaria_ejecucion', 'estado')
        ) {
            Schema::table('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
                $table->string('estado', 20)->default('borrador')->after('usuario_id');
                $table->timestamp('cerrado_at')->nullable()->after('estado');
                $table->unsignedBigInteger('cerrado_por_usuario_id')->nullable()->after('cerrado_at');
                $table->json('cierre_snapshot_json')->nullable()->after('cerrado_por_usuario_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_grupo_linea');
        Schema::dropIfExists('conciliacion_bancaria_grupo');

        if (Schema::hasTable('conciliacion_bancaria_ejecucion')
            && Schema::hasColumn('conciliacion_bancaria_ejecucion', 'estado')
        ) {
            Schema::table('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
                $table->dropColumn(['estado', 'cerrado_at', 'cerrado_por_usuario_id', 'cierre_snapshot_json']);
            });
        }
    }
};
