<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCobranzaRetencion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cobranza_retencion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobranza_id');
            $table->foreign('cobranza_id', 'fk_cobranza_retencion_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('retencion_cobranza_id');
            $table->foreign('retencion_cobranza_id', 'fk_cobranza_retencion_retencion_cobranza')->references('id')->on('retencion_cobranza')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('monto', 22, 4);
            $table->float('tasa')->nullable();
            $table->float('cotizacion');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cobranza_retencion_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');            
            $table->string('comprobante', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });  
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cobranza_retencion');
    }
}
