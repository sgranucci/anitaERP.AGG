<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propina tipeada en el estado final de la mesa (fichmm_propina de a-fichammae.c).
 * El resumen de cajeros la suma en una fila del grupo Caja empl.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_mesa')) {
            return;
        }

        if (Schema::hasColumn('tesoreria_crown_mesa', 'propina')) {
            return;
        }

        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            $table->decimal('propina', 14, 2)->default(0)->after('comentario');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_mesa')) {
            return;
        }

        if (! Schema::hasColumn('tesoreria_crown_mesa', 'propina')) {
            return;
        }

        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            $table->dropColumn('propina');
        });
    }
};
