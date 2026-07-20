<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de empleados (Anita empleado + empley + emping + empfoto).
 * Tabla propia empleado_sueldos para no chocar con produccion.empleado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('legajo');
            $table->string('nombre', 80);
            $table->string('domicilio', 80)->nullable();
            $table->string('entre_calles', 80)->nullable();
            $table->string('localidad', 60)->nullable();
            $table->string('codigo_postal', 12)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('telefono_emergencia', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('nacionalidad', 40)->nullable();
            $table->unsignedBigInteger('pais_nacimiento_id')->nullable();
            $table->string('documento', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('cuil', 15)->nullable();
            $table->char('sexo', 1)->nullable(); // 1=M 2=F (Anita)
            $table->unsignedTinyInteger('estado_civil')->nullable(); // 1..5
            $table->char('estado', 1)->default('P'); // P=provisorio A=activo B=baja (ERP)
            $table->boolean('confidencial')->default(false);

            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_egreso')->nullable();
            $table->unsignedBigInteger('motivoegreso_id')->nullable();
            $table->string('comentario_baja', 80)->nullable();

            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->unsignedBigInteger('agrupamiento_id')->nullable();
            $table->unsignedBigInteger('lugartrabajo_id')->nullable();
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->unsignedBigInteger('obrasocial_id')->nullable();
            $table->string('afiliacion_os', 30)->nullable();
            $table->unsignedBigInteger('sindicato_id')->nullable();
            $table->unsignedBigInteger('vacacion_id')->nullable();
            $table->unsignedBigInteger('art_id')->nullable();

            $table->decimal('sueldo_basico', 18, 4)->nullable();
            $table->decimal('jornal_dia', 18, 4)->nullable();
            $table->decimal('jornal_hora', 18, 4)->nullable();
            $table->string('codigo_liquidacion', 20)->nullable();
            $table->string('antiguedad_anterior', 12)->nullable(); // aa-mm-dd Anita

            $table->string('cbu', 30)->nullable();
            $table->string('cuenta_bancaria', 30)->nullable();
            $table->unsignedInteger('banco_codigo')->nullable();

            $table->char('mano_obra', 1)->nullable(); // D/I/N
            $table->char('personal_contratado', 1)->nullable(); // S/N
            $table->string('codigo_afjp', 20)->nullable();
            $table->string('situacion_sijp', 4)->nullable();
            $table->string('condicion_sijp', 4)->nullable();
            $table->string('modalidad_sijp', 6)->nullable();
            $table->string('siniestrado_sijp', 4)->nullable();
            $table->char('marca_reduccion_sijp', 1)->nullable();
            $table->char('tipo_empresa_sijp', 1)->nullable();
            $table->char('regimen_sijp', 1)->nullable();

            $table->string('a_cargo_de', 80)->nullable();
            $table->string('puesto_jefe', 80)->nullable();
            $table->string('clave_alta_temprana', 40)->nullable();
            $table->string('foto', 120)->nullable();

            $table->unsignedBigInteger('usuario_alta_id')->nullable();
            $table->unsignedBigInteger('usuario_autoriza_id')->nullable();
            $table->timestamp('autorizado_at')->nullable();

            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['empresa_id', 'legajo'], 'emp_sueldos_empresa_legajo_uq');
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'nombre']);
            $table->index('cuil');
            $table->index('categoria_id');
            $table->index('centrocosto_id');

            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->foreign('pais_nacimiento_id')->references('id')->on('pais')->nullOnDelete();
            $table->foreign('categoria_id')->references('id')->on('categoria_sueldos')->nullOnDelete();
            $table->foreign('agrupamiento_id')->references('id')->on('agrupamiento_sueldos')->nullOnDelete();
            $table->foreign('lugartrabajo_id')->references('id')->on('lugartrabajo_sueldos')->nullOnDelete();
            $table->foreign('centrocosto_id')->references('id')->on('centrocosto')->nullOnDelete();
            $table->foreign('obrasocial_id')->references('id')->on('obrasocial_sueldos')->nullOnDelete();
            $table->foreign('sindicato_id')->references('id')->on('sindicato_sueldos')->nullOnDelete();
            $table->foreign('vacacion_id')->references('id')->on('vacacion_sueldos')->nullOnDelete();
            $table->foreign('art_id')->references('id')->on('art_sueldos')->nullOnDelete();
            $table->foreign('motivoegreso_id')->references('id')->on('motivoegreso_sueldos')->nullOnDelete();
        });

        // Leyendas Anita empley
        Schema::create('empleado_leyenda_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedInteger('linea');
            $table->string('leyenda', 80);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['empleado_id', 'linea'], 'empley_empleado_linea_uq');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
        });

        // Historia ingresos/egresos Anita emping
        Schema::create('empleado_ingreso_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->date('fecha_ingreso');
            $table->date('fecha_egreso')->nullable();
            $table->unsignedBigInteger('motivoegreso_id')->nullable();
            $table->string('comentario_baja', 80)->nullable();
            $table->char('tipo_movimiento', 1)->default('I'); // I=ingreso/reingreso B=baja
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['empleado_id', 'fecha_ingreso'], 'emping_empleado_feing_uq');
            $table->index(['empleado_id', 'fecha_egreso']);
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
            $table->foreign('motivoegreso_id')->references('id')->on('motivoegreso_sueldos')->nullOnDelete();
        });

        // Bases de liquidación por empleado (cuando categoría origen_bases = C)
        Schema::create('empleado_base_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('nombrebase_id');
            $table->decimal('valor', 18, 4)->default(0);
            $table->date('fecha_vigencia');
            $table->decimal('valor_anterior', 18, 4)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'nombrebase_id', 'fecha_vigencia'], 'empbase_vigencia_idx');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
            $table->foreign('nombrebase_id')->references('id')->on('nombrebase_sueldos')->onDelete('cascade');
        });

        // Archivos asociados
        Schema::create('empleado_archivo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->string('nombrearchivo', 255);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_archivo_sueldos');
        Schema::dropIfExists('empleado_base_sueldos');
        Schema::dropIfExists('empleado_ingreso_sueldos');
        Schema::dropIfExists('empleado_leyenda_sueldos');
        Schema::dropIfExists('empleado_sueldos');
    }
};
