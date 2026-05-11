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
        Schema::create('ordencompra_comprobante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordencompra_id');
            $table->foreign('ordencompra_id', 'fk_ordencompra_comprobante_ordencompra')->references('id')->on('ordencompra')->onDelete('cascade')->onUpdate('cascade');
            $table->string('tipocomprobante', 50);
            $table->date('fechavencimiento');
            $table->decimal('monto',22,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_ordencompra_comprobante_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->float('cotizacion')->nullable();
            $table->text('detalle')->nullable();
            $table->integer('cantidadcuota')->nullable();
            $table->unsignedBigInteger('condicionpago_id')->nullable();
            $table->foreign('condicionpago_id', 'fk_ordencompra_comprobante_condicionpago')->references('id')->on('condicionpago')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ordencompra_comprobante_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ordencompra_comprobante');
    }
};
