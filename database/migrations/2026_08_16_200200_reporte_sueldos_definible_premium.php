<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime premium: ejecuciones inmutables, variantes, alertas, paridad y bursting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_sueldos_definible_ejecucion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('version_id')->nullable();
            $table->unsignedBigInteger('suscripcion_id')->nullable();
            $table->unsignedBigInteger('ejecucion_padre_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('origen', 20)->default('manual')
                ->comment('manual|programada|api|paridad|burst');
            $table->string('estado', 20)->default('pendiente')
                ->comment('pendiente|procesando|ok|advertencia|error|omitida');
            $table->json('filtros');
            $table->json('dimensiones')->nullable();
            $table->string('burst_clave', 120)->nullable();
            $table->string('burst_etiqueta', 160)->nullable();
            $table->string('resultado_hash', 64)->nullable()->index();
            $table->string('resultado_formato', 30)->nullable();
            $table->longText('resultado')->nullable();
            $table->unsignedInteger('cantidad_filas')->default(0);
            $table->unsignedInteger('cantidad_columnas')->default(0);
            $table->unsignedInteger('duracion_ms')->default(0);
            $table->unsignedBigInteger('memoria_pico_bytes')->default(0);
            $table->unsignedInteger('advertencias_count')->default(0);
            $table->json('advertencias')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('finalizada_at')->nullable();
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_ejec_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->foreign('version_id', 'rsd_ejec_version_fk')
                ->references('id')->on('reporte_sueldos_definible_version')->nullOnDelete();
            $table->foreign('suscripcion_id', 'rsd_ejec_suscripcion_fk')
                ->references('id')->on('reporte_sueldos_definible_suscripcion')->nullOnDelete();
            $table->foreign('ejecucion_padre_id', 'rsd_ejec_padre_fk')
                ->references('id')->on('reporte_sueldos_definible_ejecucion')->nullOnDelete();
            $table->index(
                ['reporte_sueldos_definible_id', 'created_at'],
                'rsd_ejec_reporte_fecha_idx'
            );
            $table->index(['estado', 'created_at'], 'rsd_ejec_estado_fecha_idx');
        });

        Schema::create('reporte_sueldos_definible_variante', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('nombre', 80);
            $table->json('filtros');
            $table->json('columnas_visibles')->nullable();
            $table->json('ordenamiento')->nullable();
            $table->json('agrupaciones')->nullable();
            $table->boolean('compartida')->default(false);
            $table->boolean('predeterminada')->default(false);
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_var_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->unique(
                ['usuario_id', 'reporte_sueldos_definible_id', 'nombre'],
                'rsd_var_usuario_reporte_nombre_uq'
            );
        });

        Schema::create('reporte_sueldos_definible_alerta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->string('nombre', 100);
            $table->string('tipo', 30)
                ->comment('sin_filas|filas_mayor|total_fuera_rango|variacion_pct|paridad');
            $table->unsignedInteger('columna_nro')->nullable();
            $table->string('operador', 10)->default('>');
            $table->decimal('umbral', 18, 4)->default(0);
            $table->decimal('umbral_hasta', 18, 4)->nullable();
            $table->boolean('bloqueante')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_alerta_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->index(
                ['reporte_sueldos_definible_id', 'activo'],
                'rsd_alerta_reporte_activo_idx'
            );
        });

        Schema::create('reporte_sueldos_definible_paridad', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ejecucion_id');
            $table->unsignedInteger('liquidacion_anita');
            $table->unsignedInteger('empresa_anita');
            $table->unsignedInteger('columna_nro');
            $table->string('columna_descripcion', 80);
            $table->decimal('total_erp', 20, 4)->default(0);
            $table->decimal('total_anita', 20, 4)->default(0);
            $table->decimal('diferencia', 20, 4)->default(0);
            $table->decimal('tolerancia', 14, 4)->default(0.01);
            $table->boolean('coincide')->default(false);
            $table->json('detalle')->nullable();
            $table->timestamps();

            $table->foreign('ejecucion_id', 'rsd_paridad_ejecucion_fk')
                ->references('id')->on('reporte_sueldos_definible_ejecucion')->cascadeOnDelete();
            $table->index(['ejecucion_id', 'coincide'], 'rsd_paridad_ejec_coincide_idx');
        });

        Schema::table('reporte_sueldos_definible_suscripcion', function (Blueprint $table) {
            $table->string('nombre', 100)->nullable()->after('reporte_sueldos_definible_id');
            $table->string('periodicidad', 20)->default('mensual')->after('activo');
            $table->unsignedTinyInteger('dia_mes')->default(5)->after('periodicidad');
            $table->unsignedTinyInteger('dia_semana')->default(1)->after('dia_mes');
            $table->string('hora', 5)->default('07:00')->after('dia_semana');
            $table->string('periodo_relativo', 30)->default('ultima_liquidacion')->after('hora');
            $table->boolean('publicar')->default(true)->after('formato');
            $table->boolean('solo_si_alertas')->default(false)->after('publicar');
            $table->string('burst_dimension', 30)->default('ninguna')->after('solo_si_alertas');
            $table->text('destinatarios')->nullable()->after('email');
            $table->json('usuario_ids')->nullable()->after('destinatarios');
            $table->text('mensaje')->nullable()->after('filtros_default');
            $table->timestamp('ultima_ejecucion')->nullable()->after('mensaje');
            $table->string('ultimo_estado', 20)->nullable()->after('ultima_ejecucion');
            $table->text('ultimo_mensaje')->nullable()->after('ultimo_estado');
        });

        Schema::create('reporte_sueldos_definible_suscripcion_destinatario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('suscripcion_id');
            $table->string('dimension_clave', 120)->default('*');
            $table->string('dimension_etiqueta', 160)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('email', 120)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('suscripcion_id', 'rsd_sus_dest_suscripcion_fk')
                ->references('id')->on('reporte_sueldos_definible_suscripcion')->cascadeOnDelete();
            $table->unique(
                ['suscripcion_id', 'dimension_clave', 'usuario_id', 'email'],
                'rsd_sus_dest_unico'
            );
            $table->index(
                ['suscripcion_id', 'dimension_clave', 'activo'],
                'rsd_sus_dest_busqueda_idx'
            );
        });

        Schema::table('reporte_sueldos_definible', function (Blueprint $table) {
            $table->unsignedBigInteger('publicado_ejecucion_id')->nullable()->after('estado_publicacion');
            $table->foreign('publicado_ejecucion_id', 'rsd_publicado_ejecucion_fk')
                ->references('id')->on('reporte_sueldos_definible_ejecucion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reporte_sueldos_definible', function (Blueprint $table) {
            $table->dropForeign('rsd_publicado_ejecucion_fk');
            $table->dropColumn('publicado_ejecucion_id');
        });

        Schema::dropIfExists('reporte_sueldos_definible_suscripcion_destinatario');

        Schema::table('reporte_sueldos_definible_suscripcion', function (Blueprint $table) {
            $table->dropColumn([
                'nombre',
                'periodicidad',
                'dia_mes',
                'dia_semana',
                'hora',
                'periodo_relativo',
                'publicar',
                'solo_si_alertas',
                'burst_dimension',
                'destinatarios',
                'usuario_ids',
                'mensaje',
                'ultima_ejecucion',
                'ultimo_estado',
                'ultimo_mensaje',
            ]);
        });

        Schema::dropIfExists('reporte_sueldos_definible_paridad');
        Schema::dropIfExists('reporte_sueldos_definible_alerta');
        Schema::dropIfExists('reporte_sueldos_definible_variante');
        Schema::dropIfExists('reporte_sueldos_definible_ejecucion');
    }
};
