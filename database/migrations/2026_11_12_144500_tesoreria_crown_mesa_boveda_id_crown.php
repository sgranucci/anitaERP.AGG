<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: vínculo estado final de mesa (propina) → movimiento bóveda PROP/MES.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_mesa')
            || Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return;
        }
        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            $table->unsignedBigInteger('boveda_id')->nullable()->after('voucher');
            $table->index('boveda_id');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_mesa')
            || ! Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return;
        }
        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            $table->dropIndex(['boveda_id']);
            $table->dropColumn('boveda_id');
        });
    }
};
