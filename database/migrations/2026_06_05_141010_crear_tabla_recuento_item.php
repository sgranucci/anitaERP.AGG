<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recuento_item')) {
            return;
        }

        Schema::create('recuento_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recuento_id');
            $table->unsignedBigInteger('articulo_id');
            $table->string('detalle', 500)->nullable();
            $table->unsignedBigInteger('unidadmedida_id')->nullable();
            $table->decimal('saldo_sistema', 20, 6)->default(0);
            $table->decimal('cantidad_contada', 20, 6)->default(0);
            $table->timestamps();

            $table->foreign('recuento_id', 'fk_recuentoitem_recuento')
                ->references('id')->on('recuento')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('articulo_id', 'fk_recuentoitem_articulo')
                ->references('id')->on('articulo')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('unidadmedida_id', 'fk_recuentoitem_unidadmedida')
                ->references('id')->on('unidadmedida')
                ->onDelete('set null')->onUpdate('restrict');

            $table->unique(['recuento_id', 'articulo_id'], 'uq_recuentoitem_recuento_articulo');
            $table->index('recuento_id', 'ix_recuentoitem_recuento');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuento_item');
    }
};
