<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cliente', 'tipoempresa_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->unsignedBigInteger('tipoempresa_id')->nullable()->after('condicioniibb_id');
                $table->foreign('tipoempresa_id', 'fk_cliente_tipoempresa')
                    ->references('id')
                    ->on('tipoempresa')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente', 'tipoempresa_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->dropForeign('fk_cliente_tipoempresa');
                $table->dropColumn('tipoempresa_id');
            });
        }
    }
};
