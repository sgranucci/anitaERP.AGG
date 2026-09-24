<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de aplicaciones/medios Anita para reimpresión de OP importadas (sin CC ERP).
 * No se usa en OP emitidas en ERP (esas tienen pagoproveedor_comprobante / caja / cheques).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagoproveedor')) {
            return;
        }
        if (Schema::hasColumn('pagoproveedor', 'anita_impresion_json')) {
            return;
        }

        Schema::table('pagoproveedor', function (Blueprint $table) {
            $table->json('anita_impresion_json')->nullable()->after('detalle');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pagoproveedor')) {
            return;
        }
        if (! Schema::hasColumn('pagoproveedor', 'anita_impresion_json')) {
            return;
        }

        Schema::table('pagoproveedor', function (Blueprint $table) {
            $table->dropColumn('anita_impresion_json');
        });
    }
};
