<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarFotoCombinacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('combinacion', function (Blueprint $table) {
			$table->string('foto', 100)->nullable()->after('usuarioultcambio_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::table('combinacion', function (Blueprint $table) {
            $table->dropColumn('foto');
        });

    }
}
