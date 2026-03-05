<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRetencionCobranzaCuentacontable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retencion_cobranza_cuentacontable', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('retencion_cobranza_id');
            $table->foreign('retencion_cobranza_id', 'fk_retencion_cobranza_cuentacontable_retencion_cobranza')->references('id')->on('retencion_cobranza')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_retencion_cobranza_cuentacontable_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_retencion_cobranza_cuentacontable_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_retencion_cobranza_cuentacontable_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('retencion_cobranza_cuentacontable');
    }
}
