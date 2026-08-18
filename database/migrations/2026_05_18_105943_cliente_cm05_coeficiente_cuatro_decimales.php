<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;

class ClienteCm05CoeficienteCuatroDecimales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE `cliente_cm05` MODIFY `coeficiente` DECIMAL(10,4) NULL',
            'ALTER TABLE cliente_cm05 ALTER COLUMN coeficiente TYPE DECIMAL(10,4) USING coeficiente::DECIMAL(10,4)'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE `cliente_cm05` MODIFY `coeficiente` DOUBLE NULL',
            'ALTER TABLE cliente_cm05 ALTER COLUMN coeficiente TYPE DOUBLE PRECISION USING coeficiente::DOUBLE PRECISION'
        );
    }
}
