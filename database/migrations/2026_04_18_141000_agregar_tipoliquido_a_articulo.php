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
            if (!Schema::hasColumn('articulo', 'tipoliquido_id')) {
                $table->unsignedBigInteger('tipoliquido_id')->nullable()->after('color_id');
                $table->foreign('tipoliquido_id', 'fk_articulo_tipoliquido')
                    ->references('id')
                    ->on('tipoliquido')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'tipoliquido_id')) {
                $table->dropForeign('fk_articulo_tipoliquido');
                $table->dropColumn('tipoliquido_id');
            }
        });
    }
};
