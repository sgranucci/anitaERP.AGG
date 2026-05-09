<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ClienteUifFotodocumentoNullable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE `cliente_uif` MODIFY `fotodocumento` VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE `cliente_uif` MODIFY `fotodocumento` VARCHAR(255) NOT NULL');
    }
}
