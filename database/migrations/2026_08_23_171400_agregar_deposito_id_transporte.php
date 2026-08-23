<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transporte') || Schema::hasColumn('transporte', 'deposito_id')) {
            return;
        }

        Schema::table('transporte', function (Blueprint $table) {
            $table->unsignedBigInteger('deposito_id')->nullable()->after('copiapedido');
            $table->foreign('deposito_id', 'fk_transporte_depmae')
                ->references('id')
                ->on('depmae')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transporte') || ! Schema::hasColumn('transporte', 'deposito_id')) {
            return;
        }

        Schema::table('transporte', function (Blueprint $table) {
            $table->dropForeign('fk_transporte_depmae');
        });
        Schema::table('transporte', function (Blueprint $table) {
            $table->dropColumn('deposito_id');
        });
    }
};
