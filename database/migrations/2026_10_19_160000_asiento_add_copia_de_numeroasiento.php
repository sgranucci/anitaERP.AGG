<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asiento', 'copia_de_numeroasiento')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('copia_de_numeroasiento')
                    ->nullable()
                    ->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('asiento', 'copia_de_numeroasiento')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->dropColumn('copia_de_numeroasiento');
            });
        }
    }
};
