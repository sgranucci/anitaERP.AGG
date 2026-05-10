<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion_presupuesto', function (Blueprint $table) {
            $cols = [];
            foreach (['condiciones_entrega', 'condiciones_compra', 'condiciones_pago'] as $c) {
                if (Schema::hasColumn('requisicion_presupuesto', $c)) {
                    $cols[] = $c;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicion_presupuesto', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion_presupuesto', 'condiciones_entrega')) {
                $table->text('condiciones_entrega')->nullable();
            }
            if (! Schema::hasColumn('requisicion_presupuesto', 'condiciones_compra')) {
                $table->text('condiciones_compra')->nullable();
            }
            if (! Schema::hasColumn('requisicion_presupuesto', 'condiciones_pago')) {
                $table->text('condiciones_pago')->nullable();
            }
        });
    }
};
