<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaOrdenventa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordenventa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_ordenventa_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numeroordenventa');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_ordenventa_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->string('comentario',255);
            $table->text('detalle');
            $table->decimal('monto',22,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_ordenventa_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('tratamiento',20);
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id', 'fk_ordenventa_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->string('nombre',255)->nullable();
            $table->string('domicilio',255)->nullable();
            $table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_ordenventa_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',8)->nullable();
            $table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_ordenventa_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('pais_id')->nullable();
            $table->foreign('pais_id', 'fk_ordenventa_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('nroinscripcion',100)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('email',255)->nullable();
            $table->unsignedBigInteger('formapago_id')->nullable();
            $table->foreign('formapago_id', 'fk_ordenventa_formapago')->references('id')->on('formapago')->onDelete('set null')->onUpdate('set null');
            $table->string('estado',50)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ordenventa_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ordenventa');
    }
}
