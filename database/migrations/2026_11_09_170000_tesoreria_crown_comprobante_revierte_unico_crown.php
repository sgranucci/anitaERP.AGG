<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un comprobante de tesorería Crown se revierte una sola vez, aunque dos usuarios lo pidan a la vez.
 * Anular una reversa no conforme deja revierte_id en null y libera el original.
 */
return new class extends Migration
{
    private const INDICE = 'tc_comp_revierte_unico';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante') || Schema::hasIndex('tesoreria_crown_comprobante', self::INDICE)) {
            return;
        }
        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->unique('revierte_id', self::INDICE);
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante') || ! Schema::hasIndex('tesoreria_crown_comprobante', self::INDICE)) {
            return;
        }
        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->dropUnique(self::INDICE);
        });
    }
};
