<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normaliza webservice legacy wsfe_v1 → wsfev1 (valor canónico en todo el ERP).
     * Sin esto, el PV CAEA Rebisco queda fuera de arca:solicitar-caea-quincenal y de la pantalla CAEA.
     */
    public function up(): void
    {
        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        DB::table('puntoventa')
            ->where('webservice', 'wsfe_v1')
            ->update(['webservice' => 'wsfev1']);
    }

    public function down(): void
    {
        // No revertir: wsfe_v1 no es un valor reconocido por los servicios ARCA.
    }
};
