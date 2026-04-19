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
            if (!Schema::hasColumn('articulo', 'color_id')) {
                $table->unsignedBigInteger('color_id')->nullable()->after('capacidad_id');
                $table->foreign('color_id', 'fk_articulo_color')
                    ->references('id')
                    ->on('color')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'color_id')) {
                $table->dropForeign('fk_articulo_color');
                $table->dropColumn('color_id');
            }
        });
    }
};

