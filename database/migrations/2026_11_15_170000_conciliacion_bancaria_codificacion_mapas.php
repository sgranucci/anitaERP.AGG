<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapas de codificación editables: concepto IB, código operación, patrones de gasto.
 * Fallback: config/conciliacion_bancaria.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_concepto')) {
            Schema::create('conciliacion_bancaria_concepto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('patron', 80);
                $table->string('codigo', 16);
                $table->string('descripcion', 120)->nullable();
                $table->boolean('es_acumulado')->default(false);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->index(['orden']);
            });
        }

        if (! Schema::hasTable('conciliacion_bancaria_cod_op')) {
            Schema::create('conciliacion_bancaria_cod_op', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('cod_op', 32);
                $table->string('codigo', 16);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->index(['cod_op']);
            });
        }

        if (! Schema::hasTable('conciliacion_bancaria_patron_gasto')) {
            Schema::create('conciliacion_bancaria_patron_gasto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 16);
                $table->string('descripcion', 120)->nullable();
                $table->text('patrones')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->unique('codigo');
            });
        }

        $this->seedSiVacio();
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_patron_gasto');
        Schema::dropIfExists('conciliacion_bancaria_cod_op');
        Schema::dropIfExists('conciliacion_bancaria_concepto');
    }

    private function seedSiVacio(): void
    {
        $now = now();

        if (Schema::hasTable('conciliacion_bancaria_concepto')
            && (int) DB::table('conciliacion_bancaria_concepto')->count() === 0) {
            $orden = 0;
            $filas = [];
            foreach ((array) config('conciliacion_bancaria.concepto_map', []) as $patron => $def) {
                $orden++;
                $filas[] = [
                    'patron' => (string) $patron,
                    'codigo' => (string) ($def['codigo'] ?? ''),
                    'descripcion' => isset($def['descripcion']) ? (string) $def['descripcion'] : null,
                    'es_acumulado' => false,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach ((array) config('conciliacion_bancaria.conceptos_acumulado', []) as $patron) {
                $orden++;
                $filas[] = [
                    'patron' => (string) $patron,
                    'codigo' => 'AC',
                    'descripcion' => 'ACUMULADO DIARIO',
                    'es_acumulado' => true,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($filas !== []) {
                DB::table('conciliacion_bancaria_concepto')->insert($filas);
            }
        }

        if (Schema::hasTable('conciliacion_bancaria_cod_op')
            && (int) DB::table('conciliacion_bancaria_cod_op')->count() === 0) {
            $orden = 0;
            $filas = [];
            foreach ((array) config('conciliacion_bancaria.cod_op_map', []) as $codOp => $codigo) {
                $orden++;
                $filas[] = [
                    'cod_op' => (string) $codOp,
                    'codigo' => (string) $codigo,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($filas !== []) {
                DB::table('conciliacion_bancaria_cod_op')->insert($filas);
            }
        }

        if (Schema::hasTable('conciliacion_bancaria_patron_gasto')
            && (int) DB::table('conciliacion_bancaria_patron_gasto')->count() === 0) {
            $orden = 0;
            $filas = [];
            foreach ((array) config('conciliacion_bancaria.codificacion_gastos', []) as $codigo => $def) {
                $orden++;
                $pats = array_map('strval', $def['patrones'] ?? []);
                $filas[] = [
                    'codigo' => (string) $codigo,
                    'descripcion' => isset($def['descripcion']) ? (string) $def['descripcion'] : null,
                    'patrones' => $pats !== [] ? implode('|', $pats) : null,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($filas !== []) {
                DB::table('conciliacion_bancaria_patron_gasto')->insert($filas);
            }
        }
    }
};
