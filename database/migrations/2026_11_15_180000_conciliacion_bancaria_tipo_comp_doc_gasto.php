<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tipo_comp → conceptos banco y líneas documento gastos (cuenta/concepto IVA).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_tipo_comp')) {
            Schema::create('conciliacion_bancaria_tipo_comp', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tipo_comp', 16);
                $table->text('patrones')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->unique('tipo_comp');
            });
        }

        if (! Schema::hasTable('conciliacion_bancaria_doc_gasto')) {
            Schema::create('conciliacion_bancaria_doc_gasto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 16);
                $table->string('cuenta_codigo', 32)->nullable();
                $table->string('concepto_ivacompra_codigo', 32)->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->unique('codigo');
            });
        }

        $this->seedSiVacio();
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_doc_gasto');
        Schema::dropIfExists('conciliacion_bancaria_tipo_comp');
    }

    private function seedSiVacio(): void
    {
        $now = now();

        if (Schema::hasTable('conciliacion_bancaria_tipo_comp')
            && (int) DB::table('conciliacion_bancaria_tipo_comp')->count() === 0) {
            $orden = 0;
            $filas = [];
            foreach ((array) config('conciliacion_bancaria.tipo_comp_conceptos_banco', []) as $tipo => $pats) {
                $orden++;
                $pats = array_map('strval', is_array($pats) ? $pats : []);
                $filas[] = [
                    'tipo_comp' => strtoupper((string) $tipo),
                    'patrones' => $pats !== [] ? implode('|', $pats) : null,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($filas !== []) {
                DB::table('conciliacion_bancaria_tipo_comp')->insert($filas);
            }
        }

        if (Schema::hasTable('conciliacion_bancaria_doc_gasto')
            && (int) DB::table('conciliacion_bancaria_doc_gasto')->count() === 0) {
            $orden = 0;
            $filas = [];
            foreach ((array) config('conciliacion_bancaria.documento_gastos.lineas', []) as $codigo => $def) {
                $orden++;
                $filas[] = [
                    'codigo' => (string) $codigo,
                    'cuenta_codigo' => isset($def['cuenta_codigo']) && $def['cuenta_codigo'] !== null && $def['cuenta_codigo'] !== ''
                        ? (string) $def['cuenta_codigo']
                        : null,
                    'concepto_ivacompra_codigo' => isset($def['concepto_ivacompra_codigo']) && $def['concepto_ivacompra_codigo'] !== null && $def['concepto_ivacompra_codigo'] !== ''
                        ? (string) $def['concepto_ivacompra_codigo']
                        : null,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($filas !== []) {
                DB::table('conciliacion_bancaria_doc_gasto')->insert($filas);
            }
        }
    }
};
