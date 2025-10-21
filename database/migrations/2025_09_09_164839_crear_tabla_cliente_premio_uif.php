<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClientePremioUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_premio_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_uif_id');
            $table->foreign('cliente_uif_id', 'fk_cliente_premio_uif_cliente_uif')->references('id')->on('cliente_uif')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('sala_id')->nullable();
            $table->foreign('sala_id', 'fk_cliente_premio_uif_sala')->references('id')->on('sala')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('juego_uif_id')->nullable();
            $table->foreign('juego_uif_id', 'fk_cliente_premio_uif_juego_uif')->references('id')->on('juego_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->dateTime('fechaentrega');
            $table->string('detalle', 255)->nullable();
            $table->decimal('monto',22,4);
			$table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cliente_premio_uif_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('posicion', 255)->nullable();
            $table->string('numerotito', 255)->nullable();
            $table->date('fechatito')->nullable();
			$table->unsignedBigInteger('mediopago_id')->nullable();
            $table->foreign('mediopago_id', 'fk_cliente_premio_uif_mediopago')->references('id')->on('mediopago')->onDelete('restrict')->onUpdate('restrict');
            $table->string('piderecibopago', 50)->nullable();
            $table->string('foto', 255)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_cliente_premio_uif_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('cliente_premio_uif');    
    }
}
