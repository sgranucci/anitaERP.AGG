<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa P.C.C. (grouping_code_ib) editable: reemplaza pcc_map / pcc_acumulado / pcc_ambiguos de config.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_pcc')) {
            Schema::create('conciliacion_bancaria_pcc', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pcc', 32);
                $table->string('codigo', 16);
                $table->string('descripcion', 120)->nullable();
                $table->text('patrones_concepto')->nullable();
                $table->text('patrones_descripcion')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->index(['pcc', 'orden']);
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_pcc')
            && (int) DB::table('conciliacion_bancaria_pcc')->count() === 0) {
            $this->seedDesdeConfig();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_pcc');
    }

    private function seedDesdeConfig(): void
    {
        $now = now();
        $filas = [];
        $orden = 0;

        foreach ((array) config('conciliacion_bancaria.pcc_map', []) as $pcc => $codigo) {
            $orden++;
            $filas[] = [
                'pcc' => (string) $pcc,
                'codigo' => (string) $codigo,
                'descripcion' => null,
                'patrones_concepto' => null,
                'patrones_descripcion' => null,
                'orden' => $orden,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ((array) config('conciliacion_bancaria.pcc_ambiguos', []) as $pcc => $reglas) {
            if (! is_array($reglas)) {
                continue;
            }
            foreach ($reglas as $regla) {
                $orden++;
                $patConc = $regla['patrones_concepto'] ?? [];
                $patDesc = $regla['patrones_descripcion'] ?? [];
                $filas[] = [
                    'pcc' => (string) $pcc,
                    'codigo' => (string) ($regla['codigo'] ?? ''),
                    'descripcion' => isset($regla['descripcion']) ? (string) $regla['descripcion'] : null,
                    'patrones_concepto' => $patConc !== [] ? implode('|', array_map('strval', $patConc)) : null,
                    'patrones_descripcion' => $patDesc !== [] ? implode('|', array_map('strval', $patDesc)) : null,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Acumulados que no estén ya en el mapa como AC.
        $yaAc = [];
        foreach ($filas as $f) {
            if (strtoupper((string) $f['codigo']) === 'AC') {
                $yaAc[$f['pcc']] = true;
            }
        }
        foreach ((array) config('conciliacion_bancaria.pcc_acumulado', []) as $pcc) {
            $pcc = (string) $pcc;
            if (isset($yaAc[$pcc])) {
                continue;
            }
            $orden++;
            $filas[] = [
                'pcc' => $pcc,
                'codigo' => 'AC',
                'descripcion' => 'ACUMULADO DIARIO',
                'patrones_concepto' => null,
                'patrones_descripcion' => null,
                'orden' => $orden,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($filas !== []) {
            DB::table('conciliacion_bancaria_pcc')->insert($filas);
        }
    }
};
