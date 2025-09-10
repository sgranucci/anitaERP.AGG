<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->unsignedBigInteger('tipodocumento_id');
            $table->foreign('tipodocumento_id', 'fk_cliente_uif_tipodocumento')->references('id')->on('tipodocumento')->onDelete('restrict')->onUpdate('restrict');
            $table->string('numerodocumento',50);
            $table->string('cuit',50)->nullable();
            $table->date('fechanacimiento')->nullable();
            $table->unsignedBigInteger('localidadnacimiento_id')->nullable();
            $table->foreign('localidadnacimiento_id', 'fk_cliente_uif_localidad_nacimiento_uif')->references('id')->on('localidad_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('paisnacimiento_id')->nullable();
            $table->foreign('paisnacimiento_id', 'fk_cliente_uif_pais_nacimiento_uif')->references('id')->on('pais_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('sexo',50);
            $table->unsignedBigInteger('estadocivil_uif_id')->nullable();
            $table->foreign('estadocivil_uif_id', 'fk_cliente_uif_estadocivil_uif')->references('id')->on('estadocivil_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('domicilio',255);
            $table->string('piso',50);
            $table->string('departamento',50);
            $table->unsignedBigInteger('localidad_uif_id');
            $table->foreign('localidad_uif_id', 'fk_cliente_uif_localidad_uif')->references('id')->on('localidad_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',8);
            $table->unsignedBigInteger('provincia_uif_id');
            $table->foreign('provincia_uif_id', 'fk_cliente_uif_provincia_uif')->references('id')->on('provincia_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('pais_uif_id');
            $table->foreign('pais_uif_id', 'fk_cliente_uif_pais_uif')->references('id')->on('pais_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('telefono',255)->nullable();
            $table->string('email',255)->nullable();
            $table->unsignedBigInteger('actividad_uif_id');
            $table->foreign('actividad_uif_id', 'fk_cliente_uif_actividad_uif')->references('id')->on('actividad_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('estado',50);
            $table->unsignedBigInteger('pep_uif_id');
            $table->foreign('pep_uif_id', 'fk_cliente_uif_pep_uif')->references('id')->on('pep_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('resideparaisofiscal',1);
            $table->string('resideexterior',1);
            $table->date('fechafirmapep')->nullable();
            $table->date('fechaconfirmapep')->nullable();
            $table->date('fechainformepep')->nullable();
            $table->date('fechaimformenosis')->nullable();
            $table->date('fechavencimientodni')->nullable();
            $table->date('fechavencimientoactividad')->nullable();
            $table->string('firmoactividad',1);
            $table->unsignedBigInteger('so_uif_id');
            $table->foreign('so_uif_id', 'fk_cliente_uif_so_uif')->references('id')->on('so_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('cumplenormativaso',1);
            $table->string('riesgopep',50);
            $table->unsignedBigInteger('nivelsocioeconomico_uif_id');
            $table->foreign('nivelsocioeconomico_uif_id', 'fk_cliente_uif_nivelsocioeconomico_uif')->references('id')->on('nivelsocioeconomico_uif')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('cliente_uif');
    }
}
