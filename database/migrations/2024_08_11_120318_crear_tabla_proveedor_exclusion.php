<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProveedorExclusion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proveedor_exclusion', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_proveedor_exclusion_proveedor')->references('id')->on('proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->string('comentario',255);
            $table->string('tiporetencion',1);
            $table->date('desdefecha');
            $table->date('hastafecha');
            $table->float('porcentajeexclusion',5,2);
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
        Schema::dropIfExists('proveedor_exclusion');
    }
}
