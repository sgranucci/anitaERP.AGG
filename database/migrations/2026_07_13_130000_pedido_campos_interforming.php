<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos Anita pendmae/pendmov exclusivos de INTERFORMING.
 * En Bierzo/AGG esta migración no altera el schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'INTERFORMING') {
            return;
        }

        if (! Schema::hasTable('ubicacion')) {
            Schema::create('ubicacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 6)->unique();
                $table->string('nombre', 30);
                $table->string('zona', 6)->nullable();
                $table->string('area', 6)->nullable();
                $table->string('nivel', 6)->nullable();
                $table->string('estado', 1)->nullable();
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        Schema::table('pedido', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido', 'orden_compra')) {
                $table->string('orden_compra', 15)->nullable()->after('descuentointegrado');
            }
            if (! Schema::hasColumn('pedido', 'deposito_id')) {
                $table->unsignedBigInteger('deposito_id')->nullable()->after('transporte_id');
            }
            if (! Schema::hasColumn('pedido', 'moneda_id')) {
                $table->unsignedBigInteger('moneda_id')->nullable()->after('deposito_id');
            }
            if (! Schema::hasColumn('pedido', 'cotizacion')) {
                $table->decimal('cotizacion', 18, 6)->nullable()->after('moneda_id');
            }
            if (! Schema::hasColumn('pedido', 'razon_suspension')) {
                $table->string('razon_suspension', 30)->nullable()->after('estadopedido');
            }
            if (! Schema::hasColumn('pedido', 'en_stock')) {
                $table->string('en_stock', 1)->nullable()->after('razon_suspension');
            }
            if (! Schema::hasColumn('pedido', 'tipo_comprobante')) {
                $table->string('tipo_comprobante', 3)->nullable()->after('codigo');
            }
            if (! Schema::hasColumn('pedido', 'letra_comprobante')) {
                $table->string('letra_comprobante', 1)->nullable()->after('tipo_comprobante');
            }
            if (! Schema::hasColumn('pedido', 'sucursal_comprobante')) {
                $table->unsignedInteger('sucursal_comprobante')->nullable()->after('letra_comprobante');
            }
            if (! Schema::hasColumn('pedido', 'numero_comprobante')) {
                $table->unsignedInteger('numero_comprobante')->nullable()->after('sucursal_comprobante');
            }
        });

        Schema::table('pedido_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_articulo', 'cantidad')) {
                $table->decimal('cantidad', 22, 6)->nullable()->after('numeroitem');
            }
            if (! Schema::hasColumn('pedido_articulo', 'cantidad_a_entregar')) {
                $table->decimal('cantidad_a_entregar', 22, 6)->nullable()->after('cantidad');
            }
            if (! Schema::hasColumn('pedido_articulo', 'cantidad_entregada')) {
                $table->decimal('cantidad_entregada', 22, 6)->nullable()->after('cantidad_a_entregar');
            }
            if (! Schema::hasColumn('pedido_articulo', 'cantidad_facturada')) {
                $table->decimal('cantidad_facturada', 22, 6)->nullable()->after('cantidad_entregada');
            }
            if (! Schema::hasColumn('pedido_articulo', 'unidadmedida_alter_id')) {
                $table->unsignedBigInteger('unidadmedida_alter_id')->nullable()->after('unidadmedida_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'cantidad_alter')) {
                $table->decimal('cantidad_alter', 22, 6)->nullable()->after('unidadmedida_alter_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'fechaentrega')) {
                $table->date('fechaentrega')->nullable()->after('observacion');
            }
            if (! Schema::hasColumn('pedido_articulo', 'orden_compra')) {
                $table->string('orden_compra', 15)->nullable()->after('fechaentrega');
            }
            if (! Schema::hasColumn('pedido_articulo', 'articulo_cliente')) {
                $table->string('articulo_cliente', 16)->nullable()->after('orden_compra');
            }
            if (! Schema::hasColumn('pedido_articulo', 'partida')) {
                $table->unsignedTinyInteger('partida')->nullable()->after('articulo_cliente');
            }
            if (! Schema::hasColumn('pedido_articulo', 'porc_fason')) {
                $table->decimal('porc_fason', 10, 4)->nullable()->after('partida');
            }
            if (! Schema::hasColumn('pedido_articulo', 'porc_fason_ant')) {
                $table->decimal('porc_fason_ant', 10, 4)->nullable()->after('porc_fason');
            }
            if (! Schema::hasColumn('pedido_articulo', 'precio_fason')) {
                $table->decimal('precio_fason', 22, 6)->nullable()->after('porc_fason_ant');
            }
            if (! Schema::hasColumn('pedido_articulo', 'moneda_fason_id')) {
                $table->unsignedBigInteger('moneda_fason_id')->nullable()->after('precio_fason');
            }
            if (! Schema::hasColumn('pedido_articulo', 'deposito_id')) {
                $table->unsignedBigInteger('deposito_id')->nullable()->after('moneda_fason_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'ubicacion')) {
                $table->string('ubicacion', 6)->nullable()->after('deposito_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'detalle_ubicacion')) {
                $table->string('detalle_ubicacion', 6)->nullable()->after('ubicacion');
            }
            if (! Schema::hasColumn('pedido_articulo', 'estado_cierre')) {
                $table->string('estado_cierre', 1)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('pedido_articulo', 'motivocierrepedido_id')) {
                $table->unsignedBigInteger('motivocierrepedido_id')->nullable()->after('estado_cierre');
            }
            if (! Schema::hasColumn('pedido_articulo', 'fecha_cierre')) {
                $table->date('fecha_cierre')->nullable()->after('motivocierrepedido_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'hora_cierre')) {
                $table->string('hora_cierre', 5)->nullable()->after('fecha_cierre');
            }
            if (! Schema::hasColumn('pedido_articulo', 'usuario_cierre_id')) {
                $table->unsignedBigInteger('usuario_cierre_id')->nullable()->after('hora_cierre');
            }
            if (! Schema::hasColumn('pedido_articulo', 'usuario_aprobacion_id')) {
                $table->unsignedBigInteger('usuario_aprobacion_id')->nullable()->after('usuario_cierre_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'fecha_aprobacion')) {
                $table->date('fecha_aprobacion')->nullable()->after('usuario_aprobacion_id');
            }
            if (! Schema::hasColumn('pedido_articulo', 'motivo_rechazo_id')) {
                $table->unsignedBigInteger('motivo_rechazo_id')->nullable()->after('fecha_aprobacion');
            }
            if (! Schema::hasColumn('pedido_articulo', 'descripcion_aux')) {
                $table->string('descripcion_aux', 50)->nullable()->after('motivo_rechazo_id');
            }
        });
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'INTERFORMING') {
            return;
        }

        Schema::table('pedido_articulo', function (Blueprint $table) {
            $cols = [
                'cantidad', 'cantidad_a_entregar', 'cantidad_entregada', 'cantidad_facturada',
                'unidadmedida_alter_id', 'cantidad_alter', 'fechaentrega', 'orden_compra',
                'articulo_cliente', 'partida', 'porc_fason', 'porc_fason_ant', 'precio_fason',
                'moneda_fason_id', 'deposito_id', 'ubicacion', 'detalle_ubicacion',
                'estado_cierre', 'motivocierrepedido_id', 'fecha_cierre', 'hora_cierre',
                'usuario_cierre_id', 'usuario_aprobacion_id', 'fecha_aprobacion',
                'motivo_rechazo_id', 'descripcion_aux',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('pedido_articulo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('pedido', function (Blueprint $table) {
            foreach (['fk_pedido_depmae_interforming', 'fk_pedido_moneda_interforming'] as $fk) {
                try {
                    $table->dropForeign($fk);
                } catch (\Throwable $e) {
                }
            }
            $cols = [
                'orden_compra', 'deposito_id', 'moneda_id', 'cotizacion', 'razon_suspension',
                'en_stock', 'tipo_comprobante', 'letra_comprobante', 'sucursal_comprobante',
                'numero_comprobante',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('pedido', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('ubicacion');
    }
};
