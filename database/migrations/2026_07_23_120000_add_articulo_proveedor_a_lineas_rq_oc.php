<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo a catálogo articulo_proveedor en líneas de RQ y OC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion_articulo', 'proveedor_id')) {
                $table->unsignedBigInteger('proveedor_id')->nullable()->after('articulo_id');
                $table->foreign('proveedor_id', 'fk_req_art_proveedor')
                    ->references('id')->on('proveedor')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
            if (! Schema::hasColumn('requisicion_articulo', 'articulo_proveedor_id')) {
                $table->unsignedBigInteger('articulo_proveedor_id')->nullable()->after('proveedor_id');
                $table->foreign('articulo_proveedor_id', 'fk_req_art_articulo_proveedor')
                    ->references('id')->on('articulo_proveedor')
                    ->onDelete('set null')->onUpdate('cascade');
            }
        });

        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'articulo_proveedor_id')) {
                $table->unsignedBigInteger('articulo_proveedor_id')->nullable()->after('articulo_id');
                $table->foreign('articulo_proveedor_id', 'fk_oc_art_articulo_proveedor')
                    ->references('id')->on('articulo_proveedor')
                    ->onDelete('set null')->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicion_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('requisicion_articulo', 'articulo_proveedor_id')) {
                $table->dropForeign('fk_req_art_articulo_proveedor');
                $table->dropColumn('articulo_proveedor_id');
            }
            if (Schema::hasColumn('requisicion_articulo', 'proveedor_id')) {
                $table->dropForeign('fk_req_art_proveedor');
                $table->dropColumn('proveedor_id');
            }
        });

        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra_articulo', 'articulo_proveedor_id')) {
                $table->dropForeign('fk_oc_art_articulo_proveedor');
                $table->dropColumn('articulo_proveedor_id');
            }
        });
    }
};
