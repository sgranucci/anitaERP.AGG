<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPartidagastoMonto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partidagasto_monto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partidagasto_id');
            $table->foreign('partidagasto_id', 'fk_partidagasto_monto_partidagasto')->references('id')->on('partidagasto')->onDelete('cascade')->onUpdate('cascade');
            $table->string('periodo', 7);
            $table->decimal('monto', 22, 4);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_partidagasto_monto_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('partidagasto_monto');
    }
}
