<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger profesional de vacaciones / licencias / ausencias (reemplaza vacempl + vacreal + vacliq).
 *
 *  - tipo_ausencia_sueldos: catalogo de tipos (vacaciones, enfermedad, ART, licencias, etc.).
 *  - empleado_cuota_movimiento_sueldos: mayor de dias de cuota (devengado +, consumido -) por periodo.
 *  - empleado_ausencia_sueldos: eventos reales (tramos) tomados, con estado y liquidacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_ausencia_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('nombre', 60);
            // vacaciones | enfermedad | accidente | licencia | suspension | otro
            $table->string('categoria', 20)->default('licencia');
            $table->boolean('afecta_saldo_vacaciones')->default(false);
            $table->boolean('goza_sueldo')->default(true);
            $table->boolean('computa_antiguedad')->default(true);
            $table->boolean('requiere_certificado')->default(false);
            $table->string('tipo_dias', 10)->default('corridos'); // corridos | habiles
            $table->unsignedSmallInteger('tope_dias_anio')->nullable();
            $table->unsignedBigInteger('concepto_id')->nullable(); // concepto que la liquida (futuro)
            $table->string('color', 9)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['categoria', 'activo']);
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->nullOnDelete();
        });

        Schema::create('empleado_ausencia_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('tipo_ausencia_id');
            $table->unsignedSmallInteger('anio_imputacion')->nullable(); // periodo de vacaciones que consume
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->decimal('dias', 8, 2)->default(0);
            $table->string('tipo_dias', 10)->default('corridos'); // corridos | habiles
            // planificada | aprobada | tomada | liquidada | anulada
            $table->string('estado', 15)->default('planificada');
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->string('certificado_archivo', 255)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'fecha_desde']);
            $table->index(['empleado_id', 'tipo_ausencia_id', 'anio_imputacion'], 'empausencia_emp_tipo_anio_idx');
            $table->index(['empleado_id', 'estado']);
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
            $table->foreign('tipo_ausencia_id')->references('id')->on('tipo_ausencia_sueldos');
        });

        Schema::create('empleado_cuota_movimiento_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedSmallInteger('anio_periodo'); // periodo de vacaciones (ej. 2025)
            // devengamiento | consumo | ajuste | migracion
            $table->string('origen', 20)->default('devengamiento');
            $table->date('fecha');
            $table->decimal('dias', 8, 2)->default(0); // + credito (devengado), - debito (consumido)
            $table->unsignedBigInteger('ausencia_id')->nullable(); // debito ligado al evento
            $table->string('descripcion', 150)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'anio_periodo']);
            $table->index(['empleado_id', 'origen']);
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
            $table->foreign('ausencia_id')->references('id')->on('empleado_ausencia_sueldos')->nullOnDelete();
        });

        $this->sembrarTipos();
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_cuota_movimiento_sueldos');
        Schema::dropIfExists('empleado_ausencia_sueldos');
        Schema::dropIfExists('tipo_ausencia_sueldos');
    }

    private function sembrarTipos(): void
    {
        $ahora = now();
        $tipos = [
            ['codigo' => 1, 'nombre' => 'Vacaciones anuales', 'categoria' => 'vacaciones', 'afecta_saldo_vacaciones' => true, 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => null, 'orden' => 1],
            ['codigo' => 10, 'nombre' => 'Enfermedad inculpable', 'categoria' => 'enfermedad', 'afecta_saldo_vacaciones' => false, 'goza_sueldo' => true, 'requiere_certificado' => true, 'tipo_dias' => 'corridos', 'orden' => 10],
            ['codigo' => 11, 'nombre' => 'Accidente / ART', 'categoria' => 'accidente', 'goza_sueldo' => true, 'requiere_certificado' => true, 'tipo_dias' => 'corridos', 'orden' => 11],
            ['codigo' => 20, 'nombre' => 'Licencia por matrimonio', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => 10, 'orden' => 20],
            ['codigo' => 21, 'nombre' => 'Licencia por nacimiento (paternidad)', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => 2, 'orden' => 21],
            ['codigo' => 22, 'nombre' => 'Licencia por maternidad', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => null, 'orden' => 22],
            ['codigo' => 23, 'nombre' => 'Licencia por fallecimiento familiar', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => 3, 'orden' => 23],
            ['codigo' => 24, 'nombre' => 'Licencia por examen', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'habiles', 'tope_dias_anio' => 10, 'orden' => 24],
            ['codigo' => 30, 'nombre' => 'Licencia sin goce de sueldo', 'categoria' => 'licencia', 'goza_sueldo' => false, 'computa_antiguedad' => false, 'tipo_dias' => 'corridos', 'orden' => 30],
            ['codigo' => 40, 'nombre' => 'Ausencia injustificada', 'categoria' => 'otro', 'goza_sueldo' => false, 'computa_antiguedad' => false, 'tipo_dias' => 'corridos', 'orden' => 40],
            ['codigo' => 41, 'nombre' => 'Suspensión', 'categoria' => 'suspension', 'goza_sueldo' => false, 'computa_antiguedad' => false, 'tipo_dias' => 'corridos', 'orden' => 41],
        ];

        foreach ($tipos as $tipo) {
            if (DB::table('tipo_ausencia_sueldos')->where('codigo', $tipo['codigo'])->exists()) {
                continue;
            }
            DB::table('tipo_ausencia_sueldos')->insert(array_merge([
                'afecta_saldo_vacaciones' => false,
                'goza_sueldo' => true,
                'computa_antiguedad' => true,
                'requiere_certificado' => false,
                'tipo_dias' => 'corridos',
                'tope_dias_anio' => null,
                'concepto_id' => null,
                'color' => null,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $tipo));
        }
    }
};
