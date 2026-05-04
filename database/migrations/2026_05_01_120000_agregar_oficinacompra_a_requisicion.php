<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion', function (Blueprint $table) {
            if (!Schema::hasColumn('requisicion', 'oficinacompra_id')) {
                $table->unsignedBigInteger('oficinacompra_id')->nullable()->after('centrocosto_id');
                $table->foreign('oficinacompra_id', 'fk_requisicion_oficinacompra')
                    ->references('id')->on('oficinacompra')
                    ->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicion', function (Blueprint $table) {
            if (Schema::hasColumn('requisicion', 'oficinacompra_id')) {
                $table->dropForeign('fk_requisicion_oficinacompra');
                $table->dropColumn('oficinacompra_id');
            }
        });
    }
};

