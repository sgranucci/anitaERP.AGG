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
        Schema::create('ordencompra_comprobante_cuota', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordencompra_comprobante_id');
            $table->foreign('ordencompra_comprobante_id', 'fk_ordencompra_comprobante_cuota_ordencompra_comprobante')->references('id')->on('ordencompra_comprobante')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fechavencimiento');
            $table->decimal('monto',22,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_ordencompra_comprobante_cuota_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->float('cotizacion')->nullable();
            $table->unsignedBigInteger('formapago_id');
            $table->foreign('formapago_id', 'fk_ordencompra_comprobante_cuota_formapago')->references('id')->on('formapago')->onDelete('restrict')->onUpdate('restrict');
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ordencompra_comprobante_cuota_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ordencompra_comprobante_cuota');
    }
};
