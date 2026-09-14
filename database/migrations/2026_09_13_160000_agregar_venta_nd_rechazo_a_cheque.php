<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'venta_nd_id')) {
                $table->unsignedBigInteger('venta_nd_id')->nullable()->after('cliente_id');
                $table->index('venta_nd_id', 'cheque_venta_nd_id_idx');
            }
            if (! Schema::hasColumn('cheque', 'fecha_rechazo')) {
                $table->date('fecha_rechazo')->nullable()->after('venta_nd_id');
            }
            if (! Schema::hasColumn('cheque', 'motivo_rechazo')) {
                $table->string('motivo_rechazo', 255)->nullable()->after('fecha_rechazo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (Schema::hasColumn('cheque', 'motivo_rechazo')) {
                $table->dropColumn('motivo_rechazo');
            }
            if (Schema::hasColumn('cheque', 'fecha_rechazo')) {
                $table->dropColumn('fecha_rechazo');
            }
            if (Schema::hasColumn('cheque', 'venta_nd_id')) {
                $table->dropIndex('cheque_venta_nd_id_idx');
                $table->dropColumn('venta_nd_id');
            }
        });
    }
};
