<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anita EL BIERZO: stkcmov.stkcv_permitido (S/N). Nullable; solo se crea en ese entorno.
     */
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        if (Schema::hasTable('formula_articulo_hijo') && ! Schema::hasColumn('formula_articulo_hijo', 'permitido')) {
            Schema::table('formula_articulo_hijo', function (Blueprint $table) {
                $table->string('permitido', 1)->nullable()->after('deposito_id');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        if (Schema::hasTable('formula_articulo_hijo') && Schema::hasColumn('formula_articulo_hijo', 'permitido')) {
            Schema::table('formula_articulo_hijo', function (Blueprint $table) {
                $table->dropColumn('permitido');
            });
        }
    }
};
