<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Motor de liquidacion - Fase A:
 *  - acumulador_sueldos: acumuladores dinamicos (N) configurables. Cada uno
 *    agrupa por tipo de concepto (tipos_incluye) con un signo. Se leen en las
 *    formulas con acum("CODIGO").
 *  - parametro_sueldos + parametro_valor_sueldos: parametros globales con
 *    vigencia temporal (topes SIPA, minimos, alicuotas). Se leen con
 *    param("CODIGO") tomando el valor vigente a la fecha de liquidacion.
 *
 * Los acumuladores reservados REM / NOREM / BRUTO / DESC estan disponibles de
 * fabrica; el operador puede sumar los suyos desde el ABM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acumulador_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable(); // null = global (todas las empresas)
            $table->string('codigo', 30);                          // ej. REM, NOREM, BRUTO, DESC, BASE_SAC
            $table->string('descripcion', 80);
            $table->json('tipos_incluye')->nullable();             // ["remunerativo","no_remunerativo",...]
            $table->tinyInteger('signo')->default(1);              // 1 suma, -1 resta
            $table->boolean('reservado')->default(false);          // de sistema (no borrable en ABM)
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empresa_id', 'codigo']);
            $table->index(['activo']);
        });

        Schema::create('parametro_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable(); // null = global
            $table->string('codigo', 40);                          // ej. TOPE_SIPA, MINIMO_SIPA
            $table->string('descripcion', 120);
            $table->string('tipo', 15)->default('numero');         // numero | texto
            $table->string('unidad', 20)->nullable();              // $, %, dias...
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empresa_id', 'codigo']);
        });

        Schema::create('parametro_valor_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parametro_id');
            $table->date('fecha_vigencia');                        // rige desde esta fecha
            $table->decimal('valor', 18, 6)->default(0);
            $table->string('valor_texto', 120)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['parametro_id', 'fecha_vigencia'], 'paramvalor_param_fecha_uq');
            $table->foreign('parametro_id')
                ->references('id')->on('parametro_sueldos')
                ->onDelete('cascade');
        });

        $this->sembrarAcumuladores();
        $this->sembrarParametros();
    }

    private function sembrarAcumuladores(): void
    {
        $ahora = now();
        $filas = [
            ['codigo' => 'REM', 'descripcion' => 'Bruto remunerativo', 'tipos_incluye' => ['remunerativo'], 'signo' => 1, 'orden' => 10],
            ['codigo' => 'NOREM', 'descripcion' => 'Bruto no remunerativo', 'tipos_incluye' => ['no_remunerativo'], 'signo' => 1, 'orden' => 20],
            ['codigo' => 'BRUTO', 'descripcion' => 'Total bruto', 'tipos_incluye' => ['remunerativo', 'no_remunerativo'], 'signo' => 1, 'orden' => 30],
            ['codigo' => 'DESC', 'descripcion' => 'Total descuentos', 'tipos_incluye' => ['descuento', 'aporte', 'retencion'], 'signo' => 1, 'orden' => 40],
            ['codigo' => 'ASIG', 'descripcion' => 'Asignaciones familiares', 'tipos_incluye' => ['asignacion'], 'signo' => 1, 'orden' => 50],
        ];
        foreach ($filas as $f) {
            DB::table('acumulador_sueldos')->insert([
                'empresa_id' => null,
                'codigo' => $f['codigo'],
                'descripcion' => $f['descripcion'],
                'tipos_incluye' => json_encode($f['tipos_incluye']),
                'signo' => $f['signo'],
                'reservado' => true,
                'activo' => true,
                'orden' => $f['orden'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function sembrarParametros(): void
    {
        $ahora = now();
        // Definiciones estandar (valores de referencia 2026: el operador debe mantenerlos)
        $defs = [
            ['codigo' => 'TOPE_SIPA', 'descripcion' => 'Tope base imponible jubilatoria (SIPA)', 'unidad' => '$', 'valor' => 0],
            ['codigo' => 'MINIMO_SIPA', 'descripcion' => 'Mínimo base imponible jubilatoria (SIPA)', 'unidad' => '$', 'valor' => 0],
            ['codigo' => 'SMVM', 'descripcion' => 'Salario mínimo vital y móvil', 'unidad' => '$', 'valor' => 0],
            ['codigo' => 'ALIC_JUBILACION', 'descripcion' => 'Alícuota aporte jubilatorio', 'unidad' => '%', 'valor' => 11],
            ['codigo' => 'ALIC_LEY19032', 'descripcion' => 'Alícuota aporte INSSJP (Ley 19032)', 'unidad' => '%', 'valor' => 3],
            ['codigo' => 'ALIC_OBRASOCIAL', 'descripcion' => 'Alícuota aporte obra social', 'unidad' => '%', 'valor' => 3],
        ];
        foreach ($defs as $d) {
            $id = DB::table('parametro_sueldos')->insertGetId([
                'empresa_id' => null,
                'codigo' => $d['codigo'],
                'descripcion' => $d['descripcion'],
                'tipo' => 'numero',
                'unidad' => $d['unidad'],
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            DB::table('parametro_valor_sueldos')->insert([
                'parametro_id' => $id,
                'fecha_vigencia' => '2026-01-01',
                'valor' => $d['valor'],
                'valor_texto' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parametro_valor_sueldos');
        Schema::dropIfExists('parametro_sueldos');
        Schema::dropIfExists('acumulador_sueldos');
    }
};
