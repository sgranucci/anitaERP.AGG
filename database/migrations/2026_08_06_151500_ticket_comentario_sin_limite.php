<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('ticket') || ! Schema::hasColumn('ticket', 'comentario')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            $table->text('comentario')->nullable(false)->change();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('ticket') || ! Schema::hasColumn('ticket', 'comentario')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            $table->string('comentario', 255)->nullable(false)->change();
        });
    }
};
