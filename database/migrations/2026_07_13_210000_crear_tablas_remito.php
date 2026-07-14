<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remito', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->date('fechaentrega');
            $table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_remito_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('condicionventa_id')->nullable();
            $table->foreign('condicionventa_id', 'fk_remito_condicionventa')->references('id')->on('condicionventa')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreign('vendedor_id', 'fk_remito_vendedor')->references('id')->on('vendedor')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id', 'fk_remito_transporte')->references('id')->on('transporte')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('mventa_id')->nullable();
            $table->foreign('mventa_id', 'fk_remito_mventa')->references('id')->on('mventa')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('zonavta_id')->nullable();
            $table->foreign('zonavta_id', 'fk_remito_zonavta')->references('id')->on('zonavta')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('cliente_entrega_id')->nullable();
            $table->foreign('cliente_entrega_id', 'fk_remito_cliente_entrega')->references('id')->on('cliente_entrega')->onDelete('set null')->onUpdate('set null');
            $table->string('lugarentrega', 255)->nullable();
            $table->string('estado', 1);
            $table->string('estadoremito', 80);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_remito_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->string('leyenda', 255)->nullable();
            $table->decimal('descuento', 5, 2)->default(0);
            $table->string('descuentointegrado', 100)->nullable();
            $table->string('codigo', 100);
            $table->string('tipocomprobante', 3)->default('REM');
            $table->string('letra', 1)->default('R');
            $table->unsignedBigInteger('puntoventa_id')->nullable();
            $table->unsignedBigInteger('numero')->default(0);
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->foreign('pedido_id', 'fk_remito_pedido')->references('id')->on('pedido')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->foreign('venta_id', 'fk_remito_venta')->references('id')->on('venta')->onDelete('set null')->onUpdate('set null');
            $table->string('origen', 20)->default('manual');
            $table->string('oblea', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('remito_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('remito_id');
            $table->foreign('remito_id', 'fk_remito_art_remito')->references('id')->on('remito')->onDelete('cascade');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_remito_art_articulo')->references('id')->on('articulo')->onDelete('cascade');
            $table->unsignedBigInteger('unidadmedida_id')->nullable();
            $table->foreign('unidadmedida_id', 'fk_remito_art_unidadmedida')->references('id')->on('unidadmedida')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('numeroitem');
            $table->decimal('caja', 22, 6);
            $table->decimal('pieza', 22, 6);
            $table->decimal('kilo', 22, 6);
            $table->decimal('precio', 22, 6);
            $table->unsignedBigInteger('listaprecio_id');
            $table->foreign('listaprecio_id', 'fk_remito_art_listaprecio')->references('id')->on('listaprecio')->onDelete('restrict')->onUpdate('restrict');
            $table->string('incluyeimpuesto', 1);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_remito_art_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');
            $table->unsignedBigInteger('descuentoventa_id')->nullable();
            $table->foreign('descuentoventa_id', 'fk_remito_art_descuentoventa')->references('id')->on('descuentoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('descuento', 5, 2);
            $table->string('descuentointegrado', 100)->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->foreign('lote_id', 'fk_remito_art_lote')->references('id')->on('lote')->onUpdate('restrict')->onDelete('restrict');
            $table->string('observacion', 255)->nullable();
            $table->string('estado', 1)->nullable();
            $table->unsignedBigInteger('pedido_articulo_id')->nullable();
            $table->foreign('pedido_articulo_id', 'fk_remito_art_pedido_articulo')->references('id')->on('pedido_articulo')->onDelete('set null')->onUpdate('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['remito_id', 'numeroitem']);
        });

        Schema::table('venta', function (Blueprint $table) {
            $table->unsignedBigInteger('remito_id')->nullable()->after('pedido_id');
            $table->foreign('remito_id', 'fk_venta_remito')->references('id')->on('remito')->onDelete('set null')->onUpdate('set null');
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropForeign('fk_venta_remito');
            $table->dropColumn('remito_id');
        });

        Schema::dropIfExists('remito_articulo');
        Schema::dropIfExists('remito');
    }
};
