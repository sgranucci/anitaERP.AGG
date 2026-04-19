<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('app.empresa') !== 'FRASLE') {
            return;
        }

        Schema::table('articulo', function (Blueprint $table) {
            if (!Schema::hasColumn('articulo', 'tipoliquidofreno_id')) {
                $table->unsignedBigInteger('tipoliquidofreno_id')->nullable()->after('color_id');
                $table->foreign('tipoliquidofreno_id', 'fk_articulo_tipoliquidofreno')
                    ->references('id')
                    ->on('tipoliquidofreno')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'tipoliquidofreno_id')) {
                $table->dropForeign('fk_articulo_tipoliquidofreno');
                $table->dropColumn('tipoliquidofreno_id');
            }
        });
    }
};
