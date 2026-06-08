<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_table_ids_adicionales')) {
                $table->string('waitry_table_ids_adicionales', 255)->nullable()->after('waitry_table_id');
            }
        });

        if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_table_ids_adicionales')) {
            return;
        }

        // Empresa Biyemas (1): Tomasso 101066; Salon/Pizzeria 103443 + 103444
        DB::table('totem_waitry_gastronomia')
            ->where('id', 1)
            ->update([
                'waitry_table_id' => 101066,
                'waitry_table_ids_adicionales' => null,
            ]);

        DB::table('totem_waitry_gastronomia')
            ->where('id', 2)
            ->update([
                'waitry_table_id' => 103443,
                'waitry_table_ids_adicionales' => '103444',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('totem_waitry_gastronomia', 'waitry_table_ids_adicionales')) {
                $table->dropColumn('waitry_table_ids_adicionales');
            }
        });
    }
};
