<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('areadestino', 'suspendido')) {
            return;
        }

        Schema::table('areadestino', function (Blueprint $table) {
            $table->boolean('suspendido')->default(false)->after('nombre');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('areadestino', 'suspendido')) {
            return;
        }

        Schema::table('areadestino', function (Blueprint $table) {
            $table->dropColumn('suspendido');
        });
    }
};
