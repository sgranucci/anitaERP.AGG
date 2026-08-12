<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de propuesta de pagos + FK en pagoproveedor y arbolaprobacion_movimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('propuesta_pago')) {
            Schema::create('propuesta_pago', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');
                $table->date('fecha_vencimiento_desde')->nullable();
                $table->date('fecha_vencimiento_hasta')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->string('estado', 30)->default('BORRADOR');
                $table->decimal('monto_total', 18, 4)->default(0);
                $table->string('detalle', 500)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('empresa_id');
                $table->index('estado');
                $table->index('fecha');
            });
        }

        if (! Schema::hasTable('propuesta_pago_linea')) {
            Schema::create('propuesta_pago_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('propuesta_pago_id');
                $table->unsignedBigInteger('proveedor_id');
                $table->unsignedBigInteger('proveedor_cuentacorriente_id');
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable();
                $table->date('fechavencimiento')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('saldo_deuda', 18, 4)->default(0);
                $table->decimal('monto_propuesto', 18, 4)->default(0);
                $table->boolean('incluido')->default(true);
                $table->unsignedBigInteger('pagoproveedor_id')->nullable();
                $table->string('estado_linea', 30)->default('PENDIENTE');
                $table->timestamps();

                $table->index('propuesta_pago_id');
                $table->index('proveedor_id');
                $table->index('proveedor_cuentacorriente_id');
                $table->index('pagoproveedor_id');
            });
        }

        if (! Schema::hasTable('propuesta_pago_estado')) {
            Schema::create('propuesta_pago_estado', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('propuesta_pago_id');
                $table->dateTime('fecha')->nullable();
                $table->string('estado', 30);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('observacion', 500)->nullable();
                $table->timestamps();

                $table->index('propuesta_pago_id');
            });
        }

        if (Schema::hasTable('pagoproveedor') && Schema::hasColumn('pagoproveedor', 'propuesta_pago_id')) {
            try {
                Schema::table('pagoproveedor', function (Blueprint $table) {
                    $table->foreign('propuesta_pago_id')
                        ->references('id')
                        ->on('propuesta_pago')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // FK puede existir o el motor no soportar re-agregar.
            }
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && ! Schema::hasColumn('arbolaprobacion_movimiento', 'propuesta_pago_id')
        ) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('propuesta_pago_id')->nullable()->index()->after('pedido_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagoproveedor') && Schema::hasColumn('pagoproveedor', 'propuesta_pago_id')) {
            try {
                Schema::table('pagoproveedor', function (Blueprint $table) {
                    $table->dropForeign(['propuesta_pago_id']);
                });
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'propuesta_pago_id')
        ) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->dropColumn('propuesta_pago_id');
            });
        }

        Schema::dropIfExists('propuesta_pago_estado');
        Schema::dropIfExists('propuesta_pago_linea');
        Schema::dropIfExists('propuesta_pago');
    }
};
