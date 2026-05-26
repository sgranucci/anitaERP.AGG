<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categoriafidelidad_entrega_gastronomia')) {
            return;
        }

        if (Schema::hasColumn('categoriafidelidad_entrega_gastronomia', 'trackdata')) {
            return;
        }

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->string('trackdata', 128)->nullable()->after('tarjeta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categoriafidelidad_entrega_gastronomia')) {
            return;
        }

        if (! Schema::hasColumn('categoriafidelidad_entrega_gastronomia', 'trackdata')) {
            return;
        }

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->dropColumn('trackdata');
        });
    }
};
