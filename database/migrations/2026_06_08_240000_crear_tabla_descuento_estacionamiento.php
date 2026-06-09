<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descuento_estacionamiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->string('codigo', 50)->unique();
            $table->char('tipovalor', 1);
            $table->decimal('valor', 22, 4);
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('cliente_id', 'fk_descuento_estac_cliente')
                ->references('id')->on('cliente')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descuento_estacionamiento');
    }
};
