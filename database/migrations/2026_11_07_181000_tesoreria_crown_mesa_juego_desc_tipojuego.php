<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de juego de mesa (mesaj_desc_tipoj) en catálogo ERP para Anexo II.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_mesa_juego')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_mesa_juego', 'desc_tipojuego')) {
            Schema::table('tesoreria_crown_mesa_juego', function (Blueprint $table) {
                $table->string('desc_tipojuego', 40)->nullable()->after('banca_codigo');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (Schema::hasTable('tesoreria_crown_mesa_juego')
            && Schema::hasColumn('tesoreria_crown_mesa_juego', 'desc_tipojuego')) {
            Schema::table('tesoreria_crown_mesa_juego', function (Blueprint $table) {
                $table->dropColumn('desc_tipojuego');
            });
        }
    }
};
