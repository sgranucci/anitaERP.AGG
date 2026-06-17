<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'penvp_orden')) {
                $table->unsignedInteger('penvp_orden')->nullable()->after('articulo_id');
                $table->index(['ordencompra_id', 'penvp_orden'], 'idx_oc_art_penvp_orden');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra_articulo', 'penvp_orden')) {
                $table->dropIndex('idx_oc_art_penvp_orden');
                $table->dropColumn('penvp_orden');
            }
        });
    }
};
