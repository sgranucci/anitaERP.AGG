<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->boolean('fl_diferencia_cantidad')->default(false)->after('fl_precio_diferencia');
            $table->boolean('fl_articulo_extra')->default(false)->after('fl_diferencia_cantidad');
            $table->boolean('fl_faltante_oc')->default(false)->after('fl_articulo_extra');
            $table->boolean('fl_laboratorio')->default(false)->after('fl_faltante_oc');
            $table->text('resumen_diferencias')->nullable()->after('comentario_precio');
        });

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->string('tipo_linea', 20)->default('OC')->after('ordencompra_articulo_id');
            $table->decimal('cantidad_oc', 22, 6)->nullable()->after('cantidad');
            $table->boolean('fl_cantidad_diferencia')->default(false)->after('fl_precio_diferencia');
            $table->boolean('fl_articulo_distinto')->default(false)->after('fl_cantidad_diferencia');
            $table->unsignedBigInteger('ordencompra_articulo_sustituido_id')->nullable()->after('ordencompra_articulo_id');
            $table->text('comentario_diferencia')->nullable()->after('comentario_precio');

            $table->foreign('ordencompra_articulo_sustituido_id', 'fk_recep_prov_art_oc_sustituido')
                ->references('id')->on('ordencompra_articulo')->onDelete('restrict');
        });

        if (! Schema::hasTable('configuracion_recepcion_proveedor_tolerancia')) {
            Schema::create('configuracion_recepcion_proveedor_tolerancia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('centrocosto_id')->nullable()
                    ->comment('Null = tolerancia default de la empresa');
                $table->decimal('tolerancia_cantidad_pct', 8, 4)->default(0);
                $table->decimal('tolerancia_precio_pct', 8, 4)->default(0);
                $table->decimal('tolerancia_precio_absoluto', 22, 6)->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['empresa_id', 'centrocosto_id'], 'uk_recep_prov_tol_emp_cc');
                $table->foreign('empresa_id', 'fk_recep_prov_tol_empresa')
                    ->references('id')->on('empresa')->onDelete('cascade');
                $table->foreign('centrocosto_id', 'fk_recep_prov_tol_cc')
                    ->references('id')->on('centrocosto')->onDelete('cascade');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_recepcion_proveedor_tolerancia');

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->dropForeign('fk_recep_prov_art_oc_sustituido');
            $table->dropColumn([
                'tipo_linea', 'cantidad_oc', 'fl_cantidad_diferencia', 'fl_articulo_distinto',
                'ordencompra_articulo_sustituido_id', 'comentario_diferencia',
            ]);
        });

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->dropColumn([
                'fl_diferencia_cantidad', 'fl_articulo_extra', 'fl_faltante_oc',
                'fl_laboratorio', 'resumen_diferencias',
            ]);
        });
    }
};
