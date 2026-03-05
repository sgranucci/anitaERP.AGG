<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRetencionIIBBCondicion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retencionIIBB_condicion', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('retencionIIBB_id');
			$table->foreign('retencionIIBB_id', 'fk_retencionIIBB_condicion_retencionIIBB')->references('id')->on('retencionIIBB')->onDelete('cascade');
			$table->unsignedBigInteger('condicionIIBB_id');
			$table->foreign('condicionIIBB_id', 'fk_retencionIIBB_condicion_condicionIIBB')->references('id')->on('condicionIIBB')->onDelete('restrict');
            $table->decimal('minimoretencion',22,4);
            $table->decimal('minimoimponible',22,4);
            $table->float('porcentajeretencion',5,2);
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
        Schema::dropIfExists('retencionIIBB_condicion');
    }
}
