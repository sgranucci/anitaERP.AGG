<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ClienteUifFotodocumentoNullable extends Migration
{
    public function up()
    {
        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE `cliente_uif` MODIFY `fotodocumento` VARCHAR(255) NULL',
            'ALTER TABLE cliente_uif ALTER COLUMN fotodocumento DROP NOT NULL'
        );
    }

    public function down()
    {
        MigrationDialectSupport::statementPorDriver(
            'ALTER TABLE `cliente_uif` MODIFY `fotodocumento` VARCHAR(255) NOT NULL',
            'ALTER TABLE cliente_uif ALTER COLUMN fotodocumento SET NOT NULL'
        );
    }
}
