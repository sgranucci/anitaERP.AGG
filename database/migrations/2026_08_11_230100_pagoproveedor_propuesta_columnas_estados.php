<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de vínculo con propuesta de pagos y reverso de OP.
 * Estados PAGADA/CONCILIADA viven en PagoproveedorEstadoTrait (app).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagoproveedor')) {
            return;
        }

        Schema::table('pagoproveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('pagoproveedor', 'propuesta_pago_id')) {
                $table->unsignedBigInteger('propuesta_pago_id')->nullable()->index()->after('asiento_id');
            }
            if (! Schema::hasColumn('pagoproveedor', 'pagoproveedor_origen_id')) {
                $table->unsignedBigInteger('pagoproveedor_origen_id')->nullable()->index()->after('propuesta_pago_id');
            }
            if (! Schema::hasColumn('pagoproveedor', 'pagoproveedor_revertido_por_id')) {
                $table->unsignedBigInteger('pagoproveedor_revertido_por_id')->nullable()->index()->after('pagoproveedor_origen_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pagoproveedor')) {
            return;
        }

        Schema::table('pagoproveedor', function (Blueprint $table) {
            foreach (['pagoproveedor_revertido_por_id', 'pagoproveedor_origen_id', 'propuesta_pago_id'] as $col) {
                if (Schema::hasColumn('pagoproveedor', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
