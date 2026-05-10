<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion_presupuesto', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion_presupuesto', 'condicionentrega_id')) {
                $table->unsignedBigInteger('condicionentrega_id')->nullable()->after('fecha');
                $table->foreign('condicionentrega_id', 'fk_req_presupuesto_condicionentrega')
                    ->references('id')->on('condicionentrega')
                    ->onDelete('set null')->onUpdate('set null');
            }
            if (! Schema::hasColumn('requisicion_presupuesto', 'condicioncompra_id')) {
                $table->unsignedBigInteger('condicioncompra_id')->nullable()->after('condicionentrega_id');
                $table->foreign('condicioncompra_id', 'fk_req_presupuesto_condicioncompra')
                    ->references('id')->on('condicioncompra')
                    ->onDelete('set null')->onUpdate('set null');
            }
            if (! Schema::hasColumn('requisicion_presupuesto', 'condicionpago_id')) {
                $table->unsignedBigInteger('condicionpago_id')->nullable()->after('condicioncompra_id');
                $table->foreign('condicionpago_id', 'fk_req_presupuesto_condicionpago')
                    ->references('id')->on('condicionpago')
                    ->onDelete('set null')->onUpdate('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicion_presupuesto', function (Blueprint $table) {
            if (Schema::hasColumn('requisicion_presupuesto', 'condicionpago_id')) {
                $table->dropForeign('fk_req_presupuesto_condicionpago');
                $table->dropColumn('condicionpago_id');
            }
            if (Schema::hasColumn('requisicion_presupuesto', 'condicioncompra_id')) {
                $table->dropForeign('fk_req_presupuesto_condicioncompra');
                $table->dropColumn('condicioncompra_id');
            }
            if (Schema::hasColumn('requisicion_presupuesto', 'condicionentrega_id')) {
                $table->dropForeign('fk_req_presupuesto_condicionentrega');
                $table->dropColumn('condicionentrega_id');
            }
        });
    }
};

