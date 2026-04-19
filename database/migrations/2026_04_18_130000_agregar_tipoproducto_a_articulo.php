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
        if (config('app.empresa') == 'FRASLE')
            Schema::table('articulo', function (Blueprint $table) {
                if (!Schema::hasColumn('articulo', 'tipoproducto_id')) {
                    $table->unsignedBigInteger('tipoproducto_id')->nullable()->after('estadofacturacion');
                    $table->foreign('tipoproducto_id', 'fk_articulo_tipoproducto')
                        ->references('id')
                        ->on('tipoproducto')
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
        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'tipoproducto_id')) {
                $table->dropForeign('fk_articulo_tipoproducto');
                $table->dropColumn('tipoproducto_id');
            }
        });
    }
};

