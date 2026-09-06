<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dual-rama RE: columna rama en niveles, circuito_re en movimientos,
 * allowlist de cuentas por árbol/CC/empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arbolaprobacion_nivel')
            && ! Schema::hasColumn('arbolaprobacion_nivel', 'rama')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->char('rama', 1)->nullable()->after('doble_aprobacion')
                    ->comment('A=allowlist/auto, B=autorización real; null=circuito único');
            });
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && ! Schema::hasColumn('arbolaprobacion_movimiento', 'circuito_re')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->string('circuito_re', 8)->nullable()->after('arbolaprobacion_oc_trigger_id')
                    ->comment('Rama RE A|B cuando el CC opera dual-rama');
            });
        }

        if (! Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
            Schema::create('arbolaprobacion_cuenta_excepcion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('arbolaprobacion_id');
                $table->unsignedBigInteger('centrocosto_id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacontable_id');
                $table->char('activo', 1)->default('S');
                $table->timestamps();

                $table->unique(
                    ['arbolaprobacion_id', 'centrocosto_id', 'empresa_id', 'cuentacontable_id'],
                    'arbol_cta_exc_unique'
                );
                $table->index(['arbolaprobacion_id', 'centrocosto_id', 'activo'], 'arbol_cta_exc_cc_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
            Schema::drop('arbolaprobacion_cuenta_excepcion');
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'circuito_re')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->dropColumn('circuito_re');
            });
        }

        if (Schema::hasTable('arbolaprobacion_nivel')
            && Schema::hasColumn('arbolaprobacion_nivel', 'rama')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->dropColumn('rama');
            });
        }
    }
};
