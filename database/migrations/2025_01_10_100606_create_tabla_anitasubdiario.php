<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTablaAnitasubdiario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('anitasubdiario', function (Blueprint $table) {
            $table->string('subd_sistema', 1);
            $table->unsignedBigInteger('subd_fecha');
            $table->string('subd_tipo', 3);
            $table->string('subd_letra', 1);
            $table->unsignedBigInteger('subd_sucursal');
            $table->unsignedBigInteger('subd_nro');
            $table->string('subd_emisor', 8);
            $table->string('subd_tipo_mov', 1);
            $table->unsignedBigInteger('subd_cuenta');
            $table->unsignedBigInteger('subd_contrapartida');
            $table->unsignedBigInteger('subd_nro_operacion');
            $table->string('subd_ref_tipo', 3);
            $table->string('subd_ref_letra', 1);
            $table->unsignedBigInteger('subd_ref_sucursal');
            $table->unsignedBigInteger('subd_ref_nro');
            $table->string('subd_ref_sistema', 1);
            $table->decimal('subd_importe', 24, 4);
            $table->string('subd_cod_mon', 1);
            $table->decimal('subd_cotizacion', 24, 4);
            $table->string('subd_desc_mov', 30);
            $table->unsignedBigInteger('subd_nro_asiento');
            $table->string('subd_procesado', 1);
            $table->unsignedBigInteger('subd_ccosto_cta');
            $table->unsignedBigInteger('subd_ccosto_con');
            $table->unsignedBigInteger('subd_nro_interno');
            $table->unsignedBigInteger('subd_empresa');
            $table->string('subd_usuario', 8);
            $table->unsignedBigInteger('subd_fecha_ult_act');
            $table->string('subd_hora_ult_act', 5);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('anitasubdiario');
    }
}
