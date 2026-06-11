<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mozo_gastronomia')) {
            return;
        }

        Schema::table('mozo_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('mozo_gastronomia', 'clave')) {
                $table->string('clave', 60)->nullable()->after('codigo');
            }
        });

        DB::table('mozo_gastronomia')
            ->whereNull('clave')
            ->orWhere('clave', '')
            ->update(['clave' => '12345']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('mozo_gastronomia')) {
            return;
        }

        Schema::table('mozo_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('mozo_gastronomia', 'clave')) {
                $table->dropColumn('clave');
            }
        });
    }
};
