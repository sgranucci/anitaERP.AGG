<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Importaciones (comercio exterior compras): carpeta, embarque, gastos, prorrateo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('importacion_gasto_tipo')) {
            Schema::create('importacion_gasto_tipo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 120);
                $table->string('tratamiento', 20)->default('inventariable'); // inventariable|gasto
                $table->string('base_prorrateo', 20)->default('valor'); // valor|cantidad|peso|volumen|igual
                $table->unsignedBigInteger('cuentacontable_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(10);
                $table->timestamps();

                $table->foreign('cuentacontable_id', 'fk_imp_gasto_tipo_cta')
                    ->references('id')->on('cuentacontable')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion')) {
            Schema::create('importacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 40)->unique();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('cotizacion', 18, 6)->nullable();
                $table->unsignedBigInteger('incoterm_id')->nullable();
                $table->string('estado', 30)->default('borrador');
                $table->date('fecha_apertura')->nullable();
                $table->date('fecha_etd')->nullable();
                $table->date('fecha_eta')->nullable();
                $table->date('fecha_nacionalizacion')->nullable();
                $table->date('fecha_cierre_costos')->nullable();
                $table->decimal('fob_estimado', 18, 4)->default(0);
                $table->decimal('gastos_estimados', 18, 4)->default(0);
                $table->decimal('gastos_reales', 18, 4)->default(0);
                $table->decimal('landed_cost', 18, 4)->default(0);
                $table->decimal('coeficiente_embarque', 18, 6)->nullable();
                $table->text('observacion')->nullable();
                $table->boolean('exige_stock_para_cerrar')->default(true);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'estado'], 'idx_importacion_empresa_estado');
                $table->foreign('empresa_id', 'fk_importacion_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('proveedor_id', 'fk_importacion_proveedor')
                    ->references('id')->on('proveedor')->onDelete('set null')->onUpdate('restrict');
                $table->foreign('moneda_id', 'fk_importacion_moneda')
                    ->references('id')->on('moneda')->onDelete('set null')->onUpdate('restrict');
                if (Schema::hasTable('incoterm')) {
                    $table->foreign('incoterm_id', 'fk_importacion_incoterm')
                        ->references('id')->on('incoterm')->onDelete('set null')->onUpdate('restrict');
                }
                $table->foreign('usuario_id', 'fk_importacion_usuario')
                    ->references('id')->on('usuario')->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_ordencompra')) {
            Schema::create('importacion_ordencompra', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->unsignedBigInteger('ordencompra_id');
                $table->timestamps();

                $table->unique(['importacion_id', 'ordencompra_id'], 'uk_importacion_oc');
                $table->foreign('importacion_id', 'fk_imp_oc_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('ordencompra_id', 'fk_imp_oc_ordencompra')
                    ->references('id')->on('ordencompra')->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_embarque')) {
            Schema::create('importacion_embarque', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->string('tipo_documento', 20)->default('BL'); // BL|AWB|CRT|OTRO
                $table->string('numero_documento', 80)->nullable();
                $table->string('contenedor', 60)->nullable();
                $table->string('buque_vuelo', 120)->nullable();
                $table->string('puerto_origen', 120)->nullable();
                $table->string('puerto_destino', 120)->nullable();
                $table->date('fecha_embarque')->nullable();
                $table->date('fecha_arribo')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->foreign('importacion_id', 'fk_imp_embarque_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_gasto')) {
            Schema::create('importacion_gasto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->unsignedBigInteger('importacion_gasto_tipo_id');
                $table->string('naturaleza', 20)->default('estimado'); // estimado|real
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('cotizacion', 18, 6)->nullable();
                $table->decimal('importe', 18, 4)->default(0);
                $table->decimal('importe_moneda_local', 18, 4)->default(0);
                $table->string('base_prorrateo', 20)->nullable();
                $table->date('fecha')->nullable();
                $table->string('referencia', 120)->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->index(['importacion_id', 'naturaleza'], 'idx_imp_gasto_nat');
                $table->foreign('importacion_id', 'fk_imp_gasto_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('importacion_gasto_tipo_id', 'fk_imp_gasto_tipo')
                    ->references('id')->on('importacion_gasto_tipo')->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('proveedor_id', 'fk_imp_gasto_proveedor')
                    ->references('id')->on('proveedor')->onDelete('set null')->onUpdate('restrict');
                $table->foreign('comprobante_proveedor_id', 'fk_imp_gasto_cp')
                    ->references('id')->on('comprobante_proveedor')->onDelete('set null')->onUpdate('restrict');
                $table->foreign('moneda_id', 'fk_imp_gasto_moneda')
                    ->references('id')->on('moneda')->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_lote')) {
            Schema::create('importacion_lote', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->unsignedBigInteger('lote_id');
                $table->timestamps();

                $table->unique(['importacion_id', 'lote_id'], 'uk_importacion_lote');
                $table->foreign('importacion_id', 'fk_imp_lote_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('lote_id', 'fk_imp_lote_lote')
                    ->references('id')->on('lote')->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_prorrateo')) {
            Schema::create('importacion_prorrateo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('calculado_en')->nullable();
                $table->boolean('aplicado')->default(false);
                $table->timestamp('aplicado_en')->nullable();
                $table->text('detalle_json')->nullable();
                $table->timestamps();

                $table->foreign('importacion_id', 'fk_imp_prorrateo_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('usuario_id', 'fk_imp_prorrateo_usuario')
                    ->references('id')->on('usuario')->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_prorrateo_linea')) {
            Schema::create('importacion_prorrateo_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_prorrateo_id');
                $table->unsignedBigInteger('importacion_gasto_id')->nullable();
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->unsignedBigInteger('ordencompra_articulo_id')->nullable();
                $table->string('sku', 60)->nullable();
                $table->decimal('base', 18, 6)->default(0);
                $table->decimal('importe_asignado', 18, 4)->default(0);
                $table->boolean('inventariable')->default(true);
                $table->timestamps();

                $table->foreign('importacion_prorrateo_id', 'fk_imp_prorrateo_lin_cab')
                    ->references('id')->on('importacion_prorrateo')->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('importacion_gasto_id', 'fk_imp_prorrateo_lin_gasto')
                    ->references('id')->on('importacion_gasto')->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_archivo')) {
            Schema::create('importacion_archivo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->string('nombre_original', 255);
                $table->string('ruta', 500);
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('tamano')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->foreign('importacion_id', 'fk_imp_archivo_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_checklist_item')) {
            Schema::create('importacion_checklist_item', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('importacion_id');
                $table->string('codigo', 40);
                $table->string('nombre', 120);
                $table->boolean('obligatorio')->default(false);
                $table->boolean('cumplido')->default(false);
                $table->date('fecha_vencimiento')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->unique(['importacion_id', 'codigo'], 'uk_imp_checklist');
                $table->foreign('importacion_id', 'fk_imp_checklist_importacion')
                    ->references('id')->on('importacion')->onDelete('cascade')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('importacion_nomenclatura')) {
            Schema::create('importacion_nomenclatura', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 20)->unique();
                $table->string('descripcion', 255);
                $table->decimal('alicuota_derecho', 8, 4)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('lote') && ! Schema::hasColumn('lote', 'importacion_id')) {
            Schema::table('lote', function (Blueprint $table) {
                $table->unsignedBigInteger('importacion_id')->nullable()->after('usuario_id');
                $table->foreign('importacion_id', 'fk_lote_importacion')
                    ->references('id')->on('importacion')->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (Schema::hasTable('ordencompra') && ! Schema::hasColumn('ordencompra', 'incoterm_id')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->unsignedBigInteger('incoterm_id')->nullable();
                if (Schema::hasTable('incoterm')) {
                    $table->foreign('incoterm_id', 'fk_ordencompra_incoterm')
                        ->references('id')->on('incoterm')->onDelete('set null')->onUpdate('restrict');
                }
            });
        }

        if (Schema::hasTable('recepcion_proveedor') && ! Schema::hasColumn('recepcion_proveedor', 'importacion_id')) {
            Schema::table('recepcion_proveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('importacion_id')->nullable();
                $table->foreign('importacion_id', 'fk_recepcion_importacion')
                    ->references('id')->on('importacion')->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (Schema::hasTable('libro_iva_importacion_bien') && ! Schema::hasColumn('libro_iva_importacion_bien', 'importacion_id')) {
            Schema::table('libro_iva_importacion_bien', function (Blueprint $table) {
                $table->unsignedBigInteger('importacion_id')->nullable();
            });
        }

        $this->seedTiposGasto();
    }

    public function down(): void
    {
        if (Schema::hasTable('libro_iva_importacion_bien') && Schema::hasColumn('libro_iva_importacion_bien', 'importacion_id')) {
            Schema::table('libro_iva_importacion_bien', function (Blueprint $table) {
                $table->dropColumn('importacion_id');
            });
        }

        if (Schema::hasTable('recepcion_proveedor') && Schema::hasColumn('recepcion_proveedor', 'importacion_id')) {
            Schema::table('recepcion_proveedor', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_recepcion_importacion');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('importacion_id');
            });
        }

        if (Schema::hasTable('ordencompra') && Schema::hasColumn('ordencompra', 'incoterm_id')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_ordencompra_incoterm');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('incoterm_id');
            });
        }

        if (Schema::hasTable('lote') && Schema::hasColumn('lote', 'importacion_id')) {
            Schema::table('lote', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_lote_importacion');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('importacion_id');
            });
        }

        Schema::dropIfExists('importacion_nomenclatura');
        Schema::dropIfExists('importacion_checklist_item');
        Schema::dropIfExists('importacion_archivo');
        Schema::dropIfExists('importacion_prorrateo_linea');
        Schema::dropIfExists('importacion_prorrateo');
        Schema::dropIfExists('importacion_lote');
        Schema::dropIfExists('importacion_gasto');
        Schema::dropIfExists('importacion_embarque');
        Schema::dropIfExists('importacion_ordencompra');
        Schema::dropIfExists('importacion');
        Schema::dropIfExists('importacion_gasto_tipo');
    }

    private function seedTiposGasto(): void
    {
        if (! Schema::hasTable('importacion_gasto_tipo')) {
            return;
        }

        $now = now();
        $tipos = [
            ['codigo' => 'FLETE', 'nombre' => 'Flete internacional', 'tratamiento' => 'inventariable', 'base_prorrateo' => 'valor', 'orden' => 10],
            ['codigo' => 'SEGURO', 'nombre' => 'Seguro de mercadería', 'tratamiento' => 'inventariable', 'base_prorrateo' => 'valor', 'orden' => 20],
            ['codigo' => 'DERECHOS', 'nombre' => 'Derechos de importación', 'tratamiento' => 'inventariable', 'base_prorrateo' => 'valor', 'orden' => 30],
            ['codigo' => 'TASA_ESTAD', 'nombre' => 'Tasa estadística', 'tratamiento' => 'inventariable', 'base_prorrateo' => 'valor', 'orden' => 40],
            ['codigo' => 'DESPACHANTE', 'nombre' => 'Honorarios despachante', 'tratamiento' => 'gasto', 'base_prorrateo' => 'valor', 'orden' => 50],
            ['codigo' => 'FLETE_INT', 'nombre' => 'Flete interno / local', 'tratamiento' => 'inventariable', 'base_prorrateo' => 'peso', 'orden' => 60],
            ['codigo' => 'OTROS', 'nombre' => 'Otros gastos de importación', 'tratamiento' => 'gasto', 'base_prorrateo' => 'igual', 'orden' => 90],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('importacion_gasto_tipo')->where('codigo', $tipo['codigo'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('importacion_gasto_tipo')->insert(array_merge($tipo, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
};
