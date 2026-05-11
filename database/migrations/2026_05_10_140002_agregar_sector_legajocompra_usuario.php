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
        Schema::table('usuario', function (Blueprint $table) {
            if (! Schema::hasColumn('usuario', 'sector_legajocompra_id')) {
                $table->unsignedBigInteger('sector_legajocompra_id')->nullable()->after('oficinacompra_id');
                $table->foreign('sector_legajocompra_id', 'fk_usuario_sector_legajocompra')
                    ->references('id')
                    ->on('sector_legajocompra')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (Schema::hasColumn('usuario', 'sector_legajocompra_id')) {
                $table->dropForeign('fk_usuario_sector_legajocompra');
                $table->dropColumn('sector_legajocompra_id');
            }
        });
    }
};
