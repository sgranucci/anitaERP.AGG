<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTiposuspensionCliente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente', function (Blueprint $table) {
			$table->unsignedBigInteger('tiposuspension_id')->nullable()->after('estado');
            $table->foreign('tiposuspension_id', 'fk_cliente_tiposuspension')->references('id')->on('tiposuspensioncliente')->onDelete('set null')->onUpdate('set null');
            $table->string('tipoalta',1)->after('tiposuspension_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente', function (Blueprint $table) {
    		$table->dropColumn('tiposuspension_id');        
    		$table->dropColumn('tipoalta');        
        });
    }
}
