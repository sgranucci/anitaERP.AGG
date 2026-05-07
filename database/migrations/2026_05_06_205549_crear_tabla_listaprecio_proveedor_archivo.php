<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listaprecio_proveedor_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('listaprecio_proveedor_id');
            $table->foreign('listaprecio_proveedor_id', 'fk_listaprecio_proveedor_archivo_listaprecio_proveedor')->references('id')->on('listaprecio_proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->string('nombrearchivo', 255);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listaprecio_proveedor_archivo');
    }
};
