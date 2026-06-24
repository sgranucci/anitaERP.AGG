<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (! Schema::hasColumn('usuario', 'suspendido')) {
                $table->boolean('suspendido')->default(false)->after('foto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (Schema::hasColumn('usuario', 'suspendido')) {
                $table->dropColumn('suspendido');
            }
        });
    }
};
