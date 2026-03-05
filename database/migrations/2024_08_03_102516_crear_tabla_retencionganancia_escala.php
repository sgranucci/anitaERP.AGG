<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRetenciongananciaEscala extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retencionganancia_escala', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('retencionganancia_id');
			$table->foreign('retencionganancia_id', 'fk_retencionganancia_escala_retencionganancia')->references('id')->on('retencionganancia')->onDelete('cascade');
            $table->decimal('desdemonto',22,4);
            $table->decimal('hastamonto',22,4);
            $table->decimal('montoretencion',22,4);
            $table->float('porcentajeretencion',5,2);
            $table->decimal('excedente',22,4);
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
        Schema::dropIfExists('retencionganancia_escala');
    }
}
