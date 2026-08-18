<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listados definibles de sueldos (Anita listmae/listcol/listcon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_sueldos_definible', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique()->comment('Nro. listado Anita / código ERP');
            $table->string('titulo', 80);
            $table->string('tipo', 20)->default('generico')->comment('osocial|sindicato|generico');
            $table->unsignedBigInteger('asociado_codigo')->nullable()->comment('Código OS/sindicato Anita');
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->string('origen', 20)->default('manual')->comment('manual|anita|plantilla');
            $table->unsignedInteger('anita_listado')->nullable()->index();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('version_actual')->default(0);
            $table->string('estado_publicacion', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresa')->nullOnDelete();
        });

        Schema::create('reporte_sueldos_definible_columna', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedInteger('nro_columna')->default(1);
            $table->string('descripcion', 80);
            $table->string('contenido', 30)->default('importe')
                ->comment('importe|cantidad|valor|campo_empleado|concepto_ganancias|formula');
            $table->unsignedInteger('campo_empleado')->nullable()->comment('Código Anita 1..N');
            $table->unsignedInteger('largo')->nullable();
            $table->string('formula', 255)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_col_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->unique(
                ['reporte_sueldos_definible_id', 'nro_columna'],
                'rsd_col_reporte_nro_uq'
            );
        });

        Schema::create('reporte_sueldos_definible_concepto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('columna_id');
            $table->unsignedInteger('concepto_codigo');
            $table->unsignedInteger('orden')->default(0);
            $table->char('signo', 1)->default('+');
            $table->timestamps();

            $table->foreign('columna_id', 'rsd_conc_columna_fk')
                ->references('id')->on('reporte_sueldos_definible_columna')->cascadeOnDelete();
            $table->unique(['columna_id', 'orden'], 'rsd_conc_columna_orden_uq');
            $table->index(['columna_id', 'concepto_codigo'], 'rsd_conc_columna_codigo_idx');
        });

        Schema::create('reporte_sueldos_definible_version', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('comentario', 255)->nullable();
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_ver_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->unique(['reporte_sueldos_definible_id', 'version'], 'rsd_ver_reporte_version_uq');
        });

        Schema::create('usuario_reporte_sueldos_definible', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->timestamps();

            $table->foreign('usuario_id', 'ursd_usuario_fk')
                ->references('id')->on('usuario')->cascadeOnDelete();
            $table->foreign('reporte_sueldos_definible_id', 'ursd_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->unique(['usuario_id', 'reporte_sueldos_definible_id'], 'ursd_usuario_reporte_uq');
        });

        Schema::create('reporte_sueldos_definible_suscripcion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('email', 120);
            $table->string('formato', 10)->default('PDF');
            $table->boolean('activo')->default(true);
            $table->json('filtros_default')->nullable();
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_sus_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->index(['reporte_sueldos_definible_id', 'activo'], 'rsd_sus_reporte_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_sueldos_definible_suscripcion');
        Schema::dropIfExists('usuario_reporte_sueldos_definible');
        Schema::dropIfExists('reporte_sueldos_definible_version');
        Schema::dropIfExists('reporte_sueldos_definible_concepto');
        Schema::dropIfExists('reporte_sueldos_definible_columna');
        Schema::dropIfExists('reporte_sueldos_definible');
    }
};
