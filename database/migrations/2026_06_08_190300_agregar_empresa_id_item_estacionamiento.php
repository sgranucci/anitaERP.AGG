<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_estacionamiento', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
        });

        $empresaDefaultId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 0);
        if ($empresaDefaultId > 0) {
            DB::table('item_estacionamiento')
                ->whereNull('empresa_id')
                ->update(['empresa_id' => $empresaDefaultId]);
        }

        Schema::table('item_estacionamiento', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('item_estacionamiento', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'nombre']);
            $table->dropForeign(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
