<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProveedor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proveedor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',10);
            $table->string('contacto',255)->nullable();
            $table->string('fantasia',255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('urlweb',255)->nullable();
            $table->string('domicilio',255);
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_proveedor_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_proveedor_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('pais_id');
            $table->foreign('pais_id', 'fk_proveedor_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable();
            $table->unsignedBigInteger('tipoempresa_id')->nullable();
            $table->foreign('tipoempresa_id', 'fk_proveedor_tipoempresa')->references('id')->on('tipoempresa')->onDelete('set null')->onUpdate('set null');
            $table->string('nroinscripcion',100);
			$table->unsignedBigInteger('condicioniva_id');
            $table->foreign('condicioniva_id', 'fk_proveedor_condicioniva')->references('id')->on('condicioniva')->onDelete('restrict')->onUpdate('restrict');
            $table->string('agentepercepcioniva',2);
            $table->string('retieneiva',1);
			$table->unsignedBigInteger('retencioniva_id')->nullable();
            $table->foreign('retencioniva_id', 'fk_proveedor_retencioniva')->references('id')->on('retencioniva')->onDelete('set null')->onUpdate('set null');
            $table->string('retieneganancia',1);
            $table->string('condicionganancia',1);
			$table->unsignedBigInteger('retencionganancia_id')->nullable();
            $table->foreign('retencionganancia_id', 'fk_proveedor_retencionganancia')->references('id')->on('retencionganancia')->onDelete('set null')->onUpdate('set null');
            $table->string('retienesuss',1);
			$table->unsignedBigInteger('retencionsuss_id')->nullable();
            $table->foreign('retencionsuss_id', 'fk_proveedor_retencionsuss')->references('id')->on('retencionsuss')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('condicionIIBB_id');
			$table->foreign('condicionIIBB_id', 'fk_proveedor_condicionIIBB')->references('id')->on('condicionIIBB')->onDelete('restrict');
            $table->string('agentepercepcionIIBB',2);            
            $table->string('nroIIBB',100)->nullable();
            $table->unsignedBigInteger('condicionpago_id')->nullable();
            $table->foreign('condicionpago_id', 'fk_proveedor_condicionpago')->references('id')->on('condicionpago')->onDelete('set null');
			$table->unsignedBigInteger('condicionentrega_id')->nullable();
            $table->foreign('condicionentrega_id', 'fk_proveedor_condicionentrega')->references('id')->on('condicionentrega')->onDelete('set null');
			$table->unsignedBigInteger('condicioncompra_id')->nullable();
            $table->foreign('condicioncompra_id', 'fk_proveedor_condicioncompra')->references('id')->on('condicioncompra')->onDelete('set null');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_proveedor_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict');
            $table->unsignedBigInteger('cuentacontableme_id')->nullable();
            $table->foreign('cuentacontableme_id', 'fk_proveedor_cuentacontableme')->references('id')->on('cuentacontable')->onDelete('set null');
            $table->unsignedBigInteger('cuentacontablecompra_id')->nullable();
            $table->foreign('cuentacontablecompra_id', 'fk_proveedor_cuentacontablecompra')->references('id')->on('cuentacontable')->onDelete('set null');
            $table->unsignedBigInteger('centrocostocompra_id')->nullable();
            $table->foreign('centrocostocompra_id', 'fk_proveedor_centrocostocompra')->references('id')->on('centrocosto')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('conceptogasto_id')->nullable();
            $table->foreign('conceptogasto_id', 'fk_proveedor_conceptogasto')->references('id')->on('conceptogasto')->onDelete('set null')->onUpdate('set null');
            $table->string('estado',1);
            $table->string('leyenda',2048);
            $table->unsignedBigInteger('tiposuspension_id')->nullable();
            $table->foreign('tiposuspension_id', 'fk_proveedor_tiposuspensionproveedor')->references('id')->on('tiposuspensionproveedor')->onDelete('set null')->onUpdate('set null');
            $table->string('tipoalta',1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_proveedor_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->timestamps();
			$table->softDeletes();
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
        Schema::dropIfExists('proveedor');
    }
}
