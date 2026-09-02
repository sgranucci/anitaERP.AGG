<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('destino')) {
            return;
        }

        Schema::table('destino', function (Blueprint $table) {
            $table->string('localidad', 80)->nullable()->change();
            $table->string('provincia', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('destino')) {
            return;
        }

        Schema::table('destino', function (Blueprint $table) {
            $table->string('localidad', 15)->nullable()->change();
            $table->string('provincia', 15)->nullable()->change();
        });
    }
};
