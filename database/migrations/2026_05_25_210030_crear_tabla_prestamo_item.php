<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestamo_item')) {
            return;
        }

        Schema::create('prestamo_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prestamo_id');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad', 20, 6);
            $table->decimal('cantidad_devuelta', 20, 6)->default(0);
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();

            $table->foreign('prestamo_id', 'fk_prestamoitem_prestamo')
                ->references('id')->on('prestamo')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('articulo_id', 'fk_prestamoitem_articulo')
                ->references('id')->on('articulo')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index('prestamo_id', 'ix_prestamoitem_prestamo');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_item');
    }
};
