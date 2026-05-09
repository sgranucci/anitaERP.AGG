<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identificador del cliente en Anita (clientes_uif.inroclienteid) para sincronizar sin duplicar filas.
     */
    public function up(): void
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            $table->unsignedBigInteger('inroclienteid')->nullable()->after('id');
            $table->unique('inroclienteid', 'uk_cliente_uif_inroclienteid');
        });
    }

    public function down(): void
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            $table->dropUnique('uk_cliente_uif_inroclienteid');
            $table->dropColumn('inroclienteid');
        });
    }
};
