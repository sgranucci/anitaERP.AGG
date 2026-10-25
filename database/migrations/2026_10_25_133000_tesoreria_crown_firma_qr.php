<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código QR de la firma del cajero. Cada comprobante pendiente tiene el suyo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante') || Schema::hasColumn('tesoreria_crown_comprobante', 'qr_token')) {
            return;
        }

        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique('tc_comp_qr_token_uq');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante') || ! Schema::hasColumn('tesoreria_crown_comprobante', 'qr_token')) {
            return;
        }

        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->dropUnique('tc_comp_qr_token_uq');
            $table->dropColumn('qr_token');
        });
    }
};
