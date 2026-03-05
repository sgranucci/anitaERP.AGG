<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaConceptoIvacompra extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('concepto_ivacompra', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',10);
            $table->string('formula',255);
            $table->unsignedBigInteger('columna_ivacompra_id');
            $table->foreign('columna_ivacompra_id', 'fk_concepto_ivacompra_columna_ivacompra')->references('id')->on('columna_ivacompra')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id', 'fk_concepto_ivacompra_empresa')->references('id')->on('empresa')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('cuentacontabledebe_id')->nullable();
            $table->foreign('cuentacontabledebe_id', 'fk_concepto_ivacompra_cuentacontabledebe')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('cuentacontablehaber_id')->nullable();
            $table->foreign('cuentacontablehaber_id', 'fk_concepto_ivacompra_cuentacontablehaer')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
            $table->string('tipoconcepto',1);
            $table->string('retieneganancia',1);
            $table->string('retieneIIBB',1);
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_concepto_ivacompra_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id', 'fk_concepto_ivacompra_impuesto')->nullable()->references('id')->on('impuesto');
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
        Schema::dropIfExists('concepto_ivacompra');
    }
}
