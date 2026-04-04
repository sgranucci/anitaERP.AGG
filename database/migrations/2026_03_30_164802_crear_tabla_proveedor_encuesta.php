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
        Schema::create('proveedor_encuesta', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_proveedor_encuesta_proveedor')->references('id')->on('proveedor')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('encuesta_id');
            $table->foreign('encuesta_id', 'fk_proveedor_encuesta_encuesta')->references('id')->on('encuesta')->onDelete('restrict')->onUpdate('restrict');
            $table->datetime('fecha');
            $table->text('comentario');
            $table->string('origen', 255);
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
        Schema::dropIfExists('proveedor_encuesta');
    }
};
