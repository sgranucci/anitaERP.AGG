<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ordencompra', function (Blueprint $table) {
            $table->unsignedBigInteger('condicionpago_id')->nullable()->after('condicionentrega_id');
            $table->foreign('condicionpago_id', 'fk_ordencompra_condicionpago')
                ->references('id')->on('condicionpago')
                ->onDelete('set null')->onUpdate('set null');
        });

        // Backfill: primer comprobante a venir, si no hay → default del proveedor.
        DB::statement("
            UPDATE ordencompra oc
            LEFT JOIN (
                SELECT ordencompra_id, MIN(id) AS min_id
                FROM ordencompra_comprobante
                GROUP BY ordencompra_id
            ) first_comp ON first_comp.ordencompra_id = oc.id
            LEFT JOIN ordencompra_comprobante c ON c.id = first_comp.min_id
            LEFT JOIN proveedor p ON p.id = oc.proveedor_id
            SET oc.condicionpago_id = COALESCE(c.condicionpago_id, p.condicionpago_id)
            WHERE oc.condicionpago_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordencompra', function (Blueprint $table) {
            $table->dropForeign('fk_ordencompra_condicionpago');
            $table->dropColumn('condicionpago_id');
        });
    }
};
