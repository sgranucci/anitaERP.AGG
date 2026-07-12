<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requisicion')) {
            return;
        }

        Schema::table('requisicion', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion', 'centrocostodestino_arbol_id')) {
                $table->unsignedBigInteger('centrocostodestino_arbol_id')->nullable()->after('centrocosto_id');
                $table->foreign('centrocostodestino_arbol_id', 'fk_requisicion_centrocostodestino_arbol')
                    ->references('id')->on('centrocosto')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('requisicion') || ! Schema::hasColumn('requisicion', 'centrocostodestino_arbol_id')) {
            return;
        }

        Schema::table('requisicion', function (Blueprint $table) {
            $table->dropForeign('fk_requisicion_centrocostodestino_arbol');
            $table->dropColumn('centrocostodestino_arbol_id');
        });
    }
};
