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
        if (Schema::hasTable('requisicion_sala_articulo')) {
            return;
        }

        Schema::create('requisicion_sala_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('requisicion_sala_id');
            $table->foreign('requisicion_sala_id', 'fk_requisicion_sala_articulo_requisicion_sala')->references('id')->on('requisicion_sala')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_requisicion_sala_articulo_articulo')->references('id')->on('articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('cantidad', 22, 4);
            $table->decimal('precio', 22, 4);
            $table->text('detalle')->nullable();
            $table->string('fueradeservicio', 1)->nullable();
            $table->string('uid',50)->nullable();
            $table->string('destino', 50)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('estadoparcial', 50)->nullable();
            $table->string('numeroremito', 50)->nullable();
            $table->string('nombreresponsable',255)->nullable();
            $table->string('numeroparte',50)->nullable();
            $table->integer('cantidadjuego')->nullable();
            $table->string('descripcionjuego', 255)->nullable();
            $table->integer('cantidadso')->nullable();
            $table->string('descripcionso', 255)->nullable();
            $table->integer('cantidadmemoria')->nullable();
            $table->string('descripcionmemoria', 255)->nullable();
            $table->integer('cantidaddongle')->nullable();
            $table->string('descripciondongle', 255)->nullable();
            $table->integer('cantidadsim')->nullable();
            $table->string('descripcionsim', 255)->nullable();
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
        Schema::dropIfExists('requisicion_sala_articulo');
    }
};
