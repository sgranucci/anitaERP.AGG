<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente') && ! Schema::hasColumn('cliente', 'provincia_iibb_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->unsignedBigInteger('provincia_iibb_id')->nullable()->after('provincia_id');
                $table->foreign('provincia_iibb_id', 'fk_cliente_provincia_iibb')
                    ->references('id')->on('provincia')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }

        if (Schema::hasTable('cliente_entrega') && ! Schema::hasColumn('cliente_entrega', 'provincia_iibb_id')) {
            Schema::table('cliente_entrega', function (Blueprint $table) {
                $table->unsignedBigInteger('provincia_iibb_id')->nullable()->after('provincia_id');
                $table->foreign('provincia_iibb_id', 'fk_cliente_entrega_provincia_iibb')
                    ->references('id')->on('provincia')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cliente_entrega') && Schema::hasColumn('cliente_entrega', 'provincia_iibb_id')) {
            Schema::table('cliente_entrega', function (Blueprint $table) {
                $table->dropForeign('fk_cliente_entrega_provincia_iibb');
                $table->dropColumn('provincia_iibb_id');
            });
        }

        if (Schema::hasTable('cliente') && Schema::hasColumn('cliente', 'provincia_iibb_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->dropForeign('fk_cliente_provincia_iibb');
                $table->dropColumn('provincia_iibb_id');
            });
        }
    }
};
