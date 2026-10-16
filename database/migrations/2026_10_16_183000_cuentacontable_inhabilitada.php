<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cuentacontable', 'inhabilitada')) {
            return;
        }

        Schema::table('cuentacontable', function (Blueprint $table) {
            $table->boolean('inhabilitada')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cuentacontable', 'inhabilitada')) {
            return;
        }

        Schema::table('cuentacontable', function (Blueprint $table) {
            $table->dropColumn('inhabilitada');
        });
    }
};
