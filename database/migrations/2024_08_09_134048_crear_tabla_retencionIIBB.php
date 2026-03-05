<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRetencionIIBB extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retencionIIBB', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_retencionIIBB_provincia')->references('id')->on('provincia')->onDelete('restrict');
			$table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->foreign('cuentacontable_id', 'fk_retencionIIBB_cuentacontable')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('retencionIIBB');
    }
}
