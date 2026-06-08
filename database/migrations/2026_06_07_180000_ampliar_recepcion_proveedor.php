<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->string('tipo', 20)->default('RECEPCION')->after('ordencompra_id');
            $table->unsignedBigInteger('recepcion_referencia_id')->nullable()->after('tipo');
            $table->unsignedBigInteger('moneda_id')->nullable()->after('numerofactura');
            $table->decimal('cotizacion', 22, 6)->nullable()->after('moneda_id');
            $table->boolean('fl_precio_diferencia')->default(false)->after('estado');
            $table->text('comentario_precio')->nullable()->after('fl_precio_diferencia');
            $table->unsignedBigInteger('asiento_id')->nullable()->after('comentario_precio');
            $table->unsignedBigInteger('movimientostock_id')->nullable()->after('asiento_id');
            $table->string('anita_tipo', 3)->nullable()->after('movimientostock_id');
            $table->string('anita_letra', 1)->nullable()->after('anita_tipo');
            $table->integer('anita_sucursal')->nullable()->after('anita_letra');
            $table->integer('anita_nro')->nullable()->after('anita_sucursal');
            $table->string('origen_carga', 20)->default('MANUAL')->after('anita_nro');

            $table->foreign('recepcion_referencia_id', 'fk_recepcion_proveedor_referencia')
                ->references('id')->on('recepcion_proveedor')->onDelete('restrict');
            $table->foreign('moneda_id', 'fk_recepcion_proveedor_moneda')
                ->references('id')->on('moneda')->onDelete('restrict');
            $table->foreign('asiento_id', 'fk_recepcion_proveedor_asiento')
                ->references('id')->on('asiento')->onDelete('restrict');
            $table->foreign('movimientostock_id', 'fk_recepcion_proveedor_movimientostock')
                ->references('id')->on('movimientostock')->onDelete('restrict');
        });

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('ordencompra_articulo_id')->nullable()->after('recepcion_proveedor_id');
            $table->integer('orden')->default(1)->after('ordencompra_articulo_id');
            $table->integer('penvp_orden')->nullable()->after('orden');
            $table->decimal('precio_ordencompra', 22, 6)->nullable()->after('precio');
            $table->boolean('fl_precio_diferencia')->default(false)->after('precio_ordencompra');
            $table->text('comentario_precio')->nullable()->after('fl_precio_diferencia');
            $table->decimal('cantidad_stock', 22, 6)->nullable()->after('cantidad');
            $table->decimal('precio_lista_proveedor', 22, 6)->nullable()->after('comentario_precio');
            $table->unsignedBigInteger('articulo_movimiento_id')->nullable()->after('lote_id');

            $table->foreign('ordencompra_articulo_id', 'fk_recepcion_proveedor_articulo_oc_articulo')
                ->references('id')->on('ordencompra_articulo')->onDelete('restrict');
            $table->foreign('articulo_movimiento_id', 'fk_recepcion_proveedor_articulo_movimiento')
                ->references('id')->on('articulo_movimiento')->onDelete('restrict');
        });

        Schema::table('recepcion_proveedor_estado', function (Blueprint $table) {
            $table->dateTime('fecha')->nullable()->after('estado');
            $table->unsignedBigInteger('usuario_id')->nullable()->after('fecha');
            $table->text('observacion')->nullable()->after('usuario_id');

            $table->foreign('usuario_id', 'fk_recepcion_proveedor_estado_usuario')
                ->references('id')->on('usuario')->onDelete('restrict');
        });

        Schema::table('recepcion_proveedor_archivo', function (Blueprint $table) {
            $table->string('tipo_archivo', 30)->default('ADJUNTO')->after('ruta');
            $table->string('mime', 100)->nullable()->after('tipo_archivo');
            $table->text('ocr_texto')->nullable()->after('mime');
            $table->json('ocr_datos')->nullable()->after('ocr_texto');
            $table->string('ocr_estado', 30)->nullable()->after('ocr_datos');
        });

        if (! Schema::hasTable('configuracion_recepcion_proveedor')) {
            Schema::create('configuracion_recepcion_proveedor', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->boolean('activa_contabilidad')->default(false);
                $table->unsignedBigInteger('cuentacontable_provision_facturas_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_factura_anticipada_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_anticipo_bienes_uso_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_proveedores_intangible_id')->nullable();
                $table->timestamps();

                $table->unique('empresa_id', 'uk_config_recepcion_proveedor_empresa');
                $table->foreign('empresa_id', 'fk_config_recepcion_proveedor_empresa')
                    ->references('id')->on('empresa')->onDelete('cascade');
                $table->foreign('cuentacontable_provision_facturas_id', 'fk_config_recep_prov_cta_provision')
                    ->references('id')->on('cuentacontable')->onDelete('restrict');
                $table->foreign('cuentacontable_factura_anticipada_id', 'fk_config_recep_prov_cta_fa')
                    ->references('id')->on('cuentacontable')->onDelete('restrict');
                $table->foreign('cuentacontable_anticipo_bienes_uso_id', 'fk_config_recep_prov_cta_abu')
                    ->references('id')->on('cuentacontable')->onDelete('restrict');
                $table->foreign('cuentacontable_proveedores_intangible_id', 'fk_config_recep_prov_cta_int')
                    ->references('id')->on('cuentacontable')->onDelete('restrict');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_recepcion_proveedor');

        Schema::table('recepcion_proveedor_archivo', function (Blueprint $table) {
            $table->dropColumn(['tipo_archivo', 'mime', 'ocr_texto', 'ocr_datos', 'ocr_estado']);
        });

        Schema::table('recepcion_proveedor_estado', function (Blueprint $table) {
            $table->dropForeign('fk_recepcion_proveedor_estado_usuario');
            $table->dropColumn(['fecha', 'usuario_id', 'observacion']);
        });

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->dropForeign('fk_recepcion_proveedor_articulo_oc_articulo');
            $table->dropForeign('fk_recepcion_proveedor_articulo_movimiento');
            $table->dropColumn([
                'ordencompra_articulo_id', 'orden', 'penvp_orden', 'precio_ordencompra',
                'fl_precio_diferencia', 'comentario_precio', 'cantidad_stock',
                'precio_lista_proveedor', 'articulo_movimiento_id',
            ]);
        });

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            $table->dropForeign('fk_recepcion_proveedor_referencia');
            $table->dropForeign('fk_recepcion_proveedor_moneda');
            $table->dropForeign('fk_recepcion_proveedor_asiento');
            $table->dropForeign('fk_recepcion_proveedor_movimientostock');
            $table->dropColumn([
                'tipo', 'recepcion_referencia_id', 'moneda_id', 'cotizacion',
                'fl_precio_diferencia', 'comentario_precio', 'asiento_id', 'movimientostock_id',
                'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro', 'origen_carga',
            ]);
        });
    }
};
