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
        Schema::create('tickettarjeta_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id');
            $table->string('numerodocumento', 20);
            $table->date('fecha');
            $table->decimal('monto', 22, 2);
            $table->string('numerocupon', 20);
            $table->decimal('montoticket', 22, 2);
            $table->unsignedBigInteger('numeroticket');
            $table->string('estado', 50);
            $table->unsignedBigInteger('venta_id');
            $table->foreign('venta_id', 'fk_tickettarjeta_gastronomia_venta')->references('id')->on('venta')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_tickettarjeta_gastronomia_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
            $table->unique(['ticket_id', 'numeroticket'], 'uq_tickettarjeta_gastronomia_ticket');
            $table->index('venta_id', 'idx_tickettarjeta_gastronomia_venta');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        }); 
    }

    public function down(): void
    {
        Schema::dropIfExists('tickettarjeta_gastronomia');
    }
};
