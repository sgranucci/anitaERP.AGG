<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oleada 3: worklist de excepciones + reglas de posting residual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_excepcion')) {
            Schema::create('conciliacion_bancaria_excepcion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->unsignedTinyInteger('mes');
                $table->unsignedSmallInteger('anio');
                $table->string('lado', 1);
                $table->string('hash', 64);
                $table->string('tipo', 40)->default('pendiente');
                $table->string('estado', 20)->default('abierta');
                $table->date('fecha')->nullable();
                $table->decimal('importe', 18, 2)->default(0);
                $table->unsignedSmallInteger('dias')->default(0);
                $table->string('bucket', 20)->nullable();
                $table->string('detalle', 255)->nullable();
                $table->json('movimiento_json')->nullable();
                $table->unsignedBigInteger('asignado_usuario_id')->nullable();
                $table->unsignedBigInteger('resuelto_por_usuario_id')->nullable();
                $table->timestamp('resuelto_at')->nullable();
                $table->string('resolucion', 40)->nullable();
                $table->text('nota')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_conc_banc_exc_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('cuentacaja_id', 'fk_conc_banc_exc_cuenta')
                    ->references('id')->on('cuentacaja')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->unique(['cuentacaja_id', 'lado', 'hash', 'mes', 'anio'], 'uq_conc_banc_exc_hash_periodo');
                $table->index(['empresa_id', 'estado', 'asignado_usuario_id'], 'idx_conc_banc_exc_estado');
            });
        }

        if (! Schema::hasTable('conciliacion_bancaria_residual_regla')) {
            Schema::create('conciliacion_bancaria_residual_regla', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('clave', 40)->unique();
                $table->string('descripcion', 120);
                $table->string('patrones', 500)->nullable();
                $table->string('dh', 1)->default('D');
                $table->string('cuenta_codigo', 20)->nullable();
                $table->string('concepto_ivacompra_codigo', 20)->nullable();
                $table->string('tipo_documento', 10)->default('EGR');
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(100);
                $table->timestamps();
            });

            $now = now();
            DB::table('conciliacion_bancaria_residual_regla')->insert([
                [
                    'clave' => 'interes',
                    'descripcion' => 'Intereses bancarios',
                    'patrones' => 'INTERES|INT.|INTEREST',
                    'dh' => 'D',
                    'cuenta_codigo' => null,
                    'concepto_ivacompra_codigo' => null,
                    'tipo_documento' => 'EGR',
                    'activo' => 1,
                    'orden' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'clave' => 'fee',
                    'descripcion' => 'Comisión / fee bancario',
                    'patrones' => 'COMISION|FEE|GASTO BANC|MANTENIMIENTO',
                    'dh' => 'D',
                    'cuenta_codigo' => null,
                    'concepto_ivacompra_codigo' => null,
                    'tipo_documento' => 'EGR',
                    'activo' => 1,
                    'orden' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'clave' => 'fx',
                    'descripcion' => 'Diferencia de cambio',
                    'patrones' => 'DIF.CAMBIO|DIFERENCIA DE CAMBIO|FX|COTIZ',
                    'dh' => 'D',
                    'cuenta_codigo' => null,
                    'concepto_ivacompra_codigo' => null,
                    'tipo_documento' => 'ASI',
                    'activo' => 1,
                    'orden' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_residual_regla');
        Schema::dropIfExists('conciliacion_bancaria_excepcion');
    }
};
