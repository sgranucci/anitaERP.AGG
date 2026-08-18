<?php

use App\Support\Database\MigrationDialectSupport;
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
        Schema::table('ordencompra', function (Blueprint $table) {
            $table->unsignedBigInteger('condicionpago_id')->nullable()->after('condicionentrega_id');
            $table->foreign('condicionpago_id', 'fk_ordencompra_condicionpago')
                ->references('id')->on('condicionpago')
                ->onDelete('set null')->onUpdate('set null');
        });

        // Backfill: primer comprobante a venir, si no hay → default del proveedor.
        MigrationDialectSupport::statementPorDriver(
            "UPDATE ordencompra oc
             LEFT JOIN (
                 SELECT ordencompra_id, MIN(id) AS min_id
                 FROM ordencompra_comprobante
                 GROUP BY ordencompra_id
             ) first_comp ON first_comp.ordencompra_id = oc.id
             LEFT JOIN ordencompra_comprobante c ON c.id = first_comp.min_id
             LEFT JOIN proveedor p ON p.id = oc.proveedor_id
             SET oc.condicionpago_id = COALESCE(c.condicionpago_id, p.condicionpago_id)
             WHERE oc.condicionpago_id IS NULL",
            "UPDATE ordencompra AS oc
             SET condicionpago_id = sub.condicionpago_id
             FROM (
                 SELECT oc2.id,
                        COALESCE(c.condicionpago_id, p.condicionpago_id) AS condicionpago_id
                 FROM ordencompra AS oc2
                 LEFT JOIN (
                     SELECT ordencompra_id, MIN(id) AS min_id
                     FROM ordencompra_comprobante
                     GROUP BY ordencompra_id
                 ) first_comp ON first_comp.ordencompra_id = oc2.id
                 LEFT JOIN ordencompra_comprobante c ON c.id = first_comp.min_id
                 LEFT JOIN proveedor p ON p.id = oc2.proveedor_id
             ) AS sub
             WHERE oc.id = sub.id
               AND oc.condicionpago_id IS NULL
               AND sub.condicionpago_id IS NOT NULL"
        );
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
