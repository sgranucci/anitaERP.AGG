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
            if (!Schema::hasColumn('usuario', 'oficinacompra_id')) {
                $table->unsignedBigInteger('oficinacompra_id')->nullable()->after('centrocosto_id');
                $table->foreign('oficinacompra_id', 'fk_usuario_oficinacompra')
                    ->references('id')->on('oficinacompra')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (Schema::hasColumn('usuario', 'oficinacompra_id')) {
                $table->dropForeign('fk_usuario_oficinacompra');
                $table->dropColumn('oficinacompra_id');
            }
        });
    }
};
