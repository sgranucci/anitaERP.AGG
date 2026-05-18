<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ClienteCm05CoeficienteCuatroDecimales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `cliente_cm05` MODIFY `coeficiente` DECIMAL(10,4) NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `cliente_cm05` MODIFY `coeficiente` DOUBLE NULL');
    }
}
