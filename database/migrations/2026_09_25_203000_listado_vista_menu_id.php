<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookmark de vista workbench → ítem de menú (menu_id nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listado_vista')) {
            return;
        }
        if (Schema::hasColumn('listado_vista', 'menu_id')) {
            return;
        }

        Schema::table('listado_vista', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable()->after('compartida')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('listado_vista') || ! Schema::hasColumn('listado_vista', 'menu_id')) {
            return;
        }

        Schema::table('listado_vista', function (Blueprint $table) {
            $table->dropColumn('menu_id');
        });
    }
};
