<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRetencioniva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retencioniva', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',10);
            $table->string('regimen',10);
            $table->string('formacalculo',3);
            $table->float('porcentajeretencion',5,2);
            $table->decimal('minimoimponible',22,4);
            $table->decimal('baseimponible',22,4);
            $table->biginteger('cantidadperiodoacumula');
            $table->decimal('valorunitario',22,4);
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
        Schema::dropIfExists('retencioniva');
    }
}
