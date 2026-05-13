<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('requisicion_articulo_id')->nullable()->after('ordencompra_id');
            $table->string('precio_origen_tipo', 40)->nullable()->after('detalle');
            $table->unsignedBigInteger('precio_origen_ref_id')->nullable()->after('precio_origen_tipo');
            $table->string('precio_origen_etiqueta', 512)->nullable()->after('precio_origen_ref_id');

            $table->foreign('requisicion_articulo_id', 'fk_oc_art_req_art')
                ->references('id')->on('requisicion_articulo')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            $table->dropForeign('fk_oc_art_req_art');
            $table->dropColumn(['requisicion_articulo_id', 'precio_origen_tipo', 'precio_origen_ref_id', 'precio_origen_etiqueta']);
        });
    }
};
