<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteCm05 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_cm05', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_cliente_cm05_cliente')->references('id')->on('cliente')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_cliente_cm05_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
            $table->string('tipopercepcion', 1);
            $table->float('coeficiente')->nullable();
            $table->date('fechavigencia')->nullable();
            $table->string('certificadonoretencion',1)->nullable();
            $table->date('desdefechanoretencion')->nullable();
            $table->date('hastafechanoretencion')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_cliente_cm05_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('cliente_cm05');
    }
}
