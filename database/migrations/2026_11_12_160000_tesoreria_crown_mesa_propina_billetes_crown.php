<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: billetaje de propina en estado final de mesa (detalle para bóveda PROP/MES).
 * El monto total sigue en propina (Anexo II); este JSON guarda valor_id => cantidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_mesa')
            || Schema::hasColumn('tesoreria_crown_mesa', 'propina_billetes')) {
            return;
        }
        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            $table->json('propina_billetes')->nullable()->after('propina');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_mesa')
            || ! Schema::hasColumn('tesoreria_crown_mesa', 'propina_billetes')) {
            return;
        }
        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            $table->dropColumn('propina_billetes');
        });
    }
};
