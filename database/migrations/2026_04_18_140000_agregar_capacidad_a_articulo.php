<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('app.empresa') !== 'FRASLE') {
            return;
        }

        Schema::table('articulo', function (Blueprint $table) {
            if (!Schema::hasColumn('articulo', 'capacidad_id')) {
                $table->unsignedBigInteger('capacidad_id')->nullable()->after('tipoproducto_id');
                $table->foreign('capacidad_id', 'fk_articulo_capacidad')
                    ->references('id')
                    ->on('capacidad')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('app.empresa') !== 'FRASLE') {
            return;
        }

        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'capacidad_id')) {
                $table->dropForeign('fk_articulo_capacidad');
                $table->dropColumn('capacidad_id');
            }
        });
    }
};

