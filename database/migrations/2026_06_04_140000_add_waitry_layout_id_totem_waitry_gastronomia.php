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
            if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_layout_id')) {
                $table->unsignedInteger('waitry_layout_id')->nullable()->after('waitry_table_id');
            }
        });

        if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_layout_id')) {
            return;
        }

        // Biyemas: Tomasso → layout Mostrador (todas las mesas de ese punto de acceso).
        DB::table('totem_waitry_gastronomia')
            ->where('id', 1)
            ->update(['waitry_layout_id' => 32211]);

        // Tótem Pizzería (K1/K2): sigue por tableId hasta dividir en dos ubicaciones/tótems con layout 32392 / 32393.
    }

    public function down(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('totem_waitry_gastronomia', 'waitry_layout_id')) {
                $table->dropColumn('waitry_layout_id');
            }
        });
    }
};
