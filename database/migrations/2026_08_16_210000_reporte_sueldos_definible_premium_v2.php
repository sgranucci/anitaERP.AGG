<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Premium v2: Sanctum PAT, dataset materializado, publicación separada, pivot/dashboard, leases distribución.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('reporte_sueldos_definible_dataset', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('ejecucion_id');
            $table->unsignedBigInteger('version_id')->nullable();
            $table->string('estado', 20)->default('borrador')
                ->comment('borrador|publicado|archivado');
            $table->unsignedInteger('cantidad_filas')->default(0);
            $table->json('columnas')->nullable();
            $table->json('totales')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('publicado_por')->nullable();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_dataset_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->foreign('ejecucion_id', 'rsd_dataset_ejecucion_fk')
                ->references('id')->on('reporte_sueldos_definible_ejecucion')->cascadeOnDelete();
            $table->foreign('version_id', 'rsd_dataset_version_fk')
                ->references('id')->on('reporte_sueldos_definible_version')->nullOnDelete();
            $table->index(['reporte_sueldos_definible_id', 'estado'], 'rsd_dataset_reporte_estado_idx');
        });

        Schema::create('reporte_sueldos_definible_dataset_fila', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dataset_id');
            $table->unsignedInteger('orden')->default(0);
            $table->unsignedInteger('legajo')->nullable()->index();
            $table->unsignedBigInteger('empleado_id')->nullable();
            $table->json('datos');
            $table->timestamps();

            $table->foreign('dataset_id', 'rsd_dataset_fila_fk')
                ->references('id')->on('reporte_sueldos_definible_dataset')->cascadeOnDelete();
            $table->index(['dataset_id', 'orden'], 'rsd_dataset_fila_orden_idx');
        });

        Schema::create('reporte_sueldos_definible_dataset_publicacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('dataset_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('accion', 20)->comment('publicar|despublicar|rollback');
            $table->string('comentario', 255)->nullable();
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_dspub_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->foreign('dataset_id', 'rsd_dspub_dataset_fk')
                ->references('id')->on('reporte_sueldos_definible_dataset')->cascadeOnDelete();
            $table->index(['reporte_sueldos_definible_id', 'created_at'], 'rsd_dspub_reporte_fecha_idx');
        });

        Schema::create('reporte_sueldos_definible_dashboard', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('variante_id')->nullable();
            $table->string('nombre', 80);
            $table->boolean('compartida')->default(false);
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_dash_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->foreign('variante_id', 'rsd_dash_variante_fk')
                ->references('id')->on('reporte_sueldos_definible_variante')->nullOnDelete();
            $table->unique(
                ['usuario_id', 'reporte_sueldos_definible_id', 'nombre'],
                'rsd_dash_usuario_reporte_nombre_uq'
            );
        });

        Schema::create('reporte_sueldos_definible_dashboard_widget', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dashboard_id');
            $table->string('titulo', 100);
            $table->string('tipo', 20)->default('tabla')
                ->comment('tabla|barra|linea|pie|kpi');
            $table->json('pivot_spec')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->unsignedInteger('ancho')->default(12);
            $table->timestamps();

            $table->foreign('dashboard_id', 'rsd_widget_dash_fk')
                ->references('id')->on('reporte_sueldos_definible_dashboard')->cascadeOnDelete();
        });

        if (Schema::hasTable('reporte_sueldos_definible_variante')
            && ! Schema::hasColumn('reporte_sueldos_definible_variante', 'pivot_spec')) {
            Schema::table('reporte_sueldos_definible_variante', function (Blueprint $table) {
                $table->json('pivot_spec')->nullable()->after('agrupaciones');
                $table->json('visualizacion')->nullable()->after('pivot_spec');
            });
        }

        if (Schema::hasTable('reporte_sueldos_definible_suscripcion')
            && ! Schema::hasColumn('reporte_sueldos_definible_suscripcion', 'next_run_at')) {
            Schema::table('reporte_sueldos_definible_suscripcion', function (Blueprint $table) {
                $table->timestamp('next_run_at')->nullable()->after('ultima_ejecucion');
                $table->timestamp('lease_until')->nullable()->after('next_run_at');
                $table->string('lease_token', 64)->nullable()->after('lease_until');
            });
        }

        if (Schema::hasTable('reporte_sueldos_definible_ejecucion')
            && ! Schema::hasColumn('reporte_sueldos_definible_ejecucion', 'uuid')) {
            Schema::table('reporte_sueldos_definible_ejecucion', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
                $table->unsignedBigInteger('dataset_id')->nullable()->after('version_id');
            });
        }

        if (Schema::hasTable('reporte_sueldos_definible')
            && ! Schema::hasColumn('reporte_sueldos_definible', 'publicado_dataset_id')) {
            Schema::table('reporte_sueldos_definible', function (Blueprint $table) {
                $table->unsignedBigInteger('publicado_dataset_id')->nullable()->after('publicado_ejecucion_id');
                $table->boolean('incluye_confidencial')->default(false)->after('activo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reporte_sueldos_definible')
            && Schema::hasColumn('reporte_sueldos_definible', 'publicado_dataset_id')) {
            Schema::table('reporte_sueldos_definible', function (Blueprint $table) {
                $table->dropColumn(['publicado_dataset_id', 'incluye_confidencial']);
            });
        }
        if (Schema::hasTable('reporte_sueldos_definible_ejecucion')
            && Schema::hasColumn('reporte_sueldos_definible_ejecucion', 'uuid')) {
            Schema::table('reporte_sueldos_definible_ejecucion', function (Blueprint $table) {
                $table->dropColumn(['uuid', 'dataset_id']);
            });
        }
        if (Schema::hasTable('reporte_sueldos_definible_suscripcion')
            && Schema::hasColumn('reporte_sueldos_definible_suscripcion', 'next_run_at')) {
            Schema::table('reporte_sueldos_definible_suscripcion', function (Blueprint $table) {
                $table->dropColumn(['next_run_at', 'lease_until', 'lease_token']);
            });
        }
        if (Schema::hasTable('reporte_sueldos_definible_variante')
            && Schema::hasColumn('reporte_sueldos_definible_variante', 'pivot_spec')) {
            Schema::table('reporte_sueldos_definible_variante', function (Blueprint $table) {
                $table->dropColumn(['pivot_spec', 'visualizacion']);
            });
        }

        Schema::dropIfExists('reporte_sueldos_definible_dashboard_widget');
        Schema::dropIfExists('reporte_sueldos_definible_dashboard');
        Schema::dropIfExists('reporte_sueldos_definible_dataset_publicacion');
        Schema::dropIfExists('reporte_sueldos_definible_dataset_fila');
        Schema::dropIfExists('reporte_sueldos_definible_dataset');
        // No dropear personal_access_tokens si otras apps lo usan.
    }
};
