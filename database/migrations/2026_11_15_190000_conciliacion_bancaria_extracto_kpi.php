<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oleada 1 premium: extracto formal (cabecera+líneas), KPI en ejecución, origen en par.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_extracto')) {
            Schema::create('conciliacion_bancaria_extracto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->string('account_number', 64);
                $table->string('numero_extracto', 80)->nullable();
                $table->date('fecha_extracto')->nullable();
                $table->date('fecha_desde')->nullable();
                $table->date('fecha_hasta')->nullable();
                $table->decimal('saldo_inicial', 18, 2)->nullable();
                $table->decimal('saldo_final', 18, 2)->nullable();
                $table->decimal('control_debitos', 18, 2)->default(0);
                $table->decimal('control_creditos', 18, 2)->default(0);
                $table->unsignedInteger('control_cantidad')->default(0);
                $table->string('moneda', 8)->default('ARS');
                $table->string('formato', 40);
                $table->string('archivo_origen', 255)->nullable();
                $table->string('hash_archivo', 64)->nullable();
                $table->string('estado', 20)->default('importado');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_conc_banc_ext_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('cuentacaja_id', 'fk_conc_banc_ext_cuentacaja')
                    ->references('id')->on('cuentacaja')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->unique(['empresa_id', 'cuentacaja_id', 'hash_archivo'], 'uq_conc_banc_ext_hash');
                $table->index(['cuentacaja_id', 'fecha_desde', 'fecha_hasta'], 'idx_conc_banc_ext_cuenta_periodo');
            });
        }

        if (! Schema::hasTable('conciliacion_bancaria_extracto_linea')) {
            Schema::create('conciliacion_bancaria_extracto_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('extracto_id');
                $table->unsignedInteger('orden')->default(0);
                $table->date('fecha')->nullable();
                $table->string('dh', 1)->nullable();
                $table->decimal('importe', 18, 2)->default(0);
                $table->string('concepto', 255)->nullable();
                $table->string('comprobante', 80)->nullable();
                $table->string('referencia', 120)->nullable();
                $table->string('hash_banco', 64)->nullable();
                $table->unsignedBigInteger('interbanking_movimiento_id')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->foreign('extracto_id', 'fk_conc_banc_ext_lin_ext')
                    ->references('id')->on('conciliacion_bancaria_extracto')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->index(['extracto_id', 'orden'], 'idx_conc_banc_ext_lin_orden');
                $table->index(['hash_banco'], 'idx_conc_banc_ext_lin_hash');
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_ejecucion')) {
            Schema::table('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
                if (! Schema::hasColumn('conciliacion_bancaria_ejecucion', 'kpi_json')) {
                    $table->json('kpi_json')->nullable()->after('resumen_json');
                }
                if (! Schema::hasColumn('conciliacion_bancaria_ejecucion', 'extracto_id')) {
                    $table->unsignedBigInteger('extracto_id')->nullable()->after('cuentacaja_id');
                }
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_par')
            && ! Schema::hasColumn('conciliacion_bancaria_par', 'origen')
        ) {
            Schema::table('conciliacion_bancaria_par', function (Blueprint $table) {
                $table->string('origen', 20)->default('auto')->after('importe');
                $table->unsignedSmallInteger('score')->nullable()->after('origen');
                $table->index(['cuentacaja_id', 'origen'], 'idx_conc_banc_par_origen');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conciliacion_bancaria_par')
            && Schema::hasColumn('conciliacion_bancaria_par', 'origen')
        ) {
            Schema::table('conciliacion_bancaria_par', function (Blueprint $table) {
                $table->dropIndex('idx_conc_banc_par_origen');
                $table->dropColumn(['origen', 'score']);
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_ejecucion')) {
            Schema::table('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
                if (Schema::hasColumn('conciliacion_bancaria_ejecucion', 'kpi_json')) {
                    $table->dropColumn('kpi_json');
                }
                if (Schema::hasColumn('conciliacion_bancaria_ejecucion', 'extracto_id')) {
                    $table->dropColumn('extracto_id');
                }
            });
        }

        Schema::dropIfExists('conciliacion_bancaria_extracto_linea');
        Schema::dropIfExists('conciliacion_bancaria_extracto');
    }
};
