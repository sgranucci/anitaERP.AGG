<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CashBox / recuento de slot → movimiento de bóveda (relevamiento JL).
 * Al confirmar el recuento se asienta un INGR en bóveda y se guarda el vínculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_slot') || Schema::hasColumn('tesoreria_crown_slot', 'boveda_id')) {
            return;
        }
        Schema::table('tesoreria_crown_slot', function (Blueprint $table) {
            $table->unsignedBigInteger('boveda_id')->nullable()->after('jornada_id');
            $table->index('boveda_id');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_slot') || ! Schema::hasColumn('tesoreria_crown_slot', 'boveda_id')) {
            return;
        }
        Schema::table('tesoreria_crown_slot', function (Blueprint $table) {
            $table->dropIndex(['boveda_id']);
            $table->dropColumn('boveda_id');
        });
    }
};
