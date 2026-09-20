<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste de qué scan de Anita salió cada precarga.
 *
 * Hasta acá el modal del legajo unía scan y precarga por letra + sucursal + número, que es un dato
 * editable: cuando alguien corrige el número de la precarga, el scan queda huérfano y la siguiente
 * apertura del modal lo materializa otra vez como precarga nueva (así apareció A-5-238 en la OC
 * 216191 despues de que se renumerara la precarga a 33). El documento del scan es el dato estable.
 */
return new class extends Migration
{
    private const TABLA = 'precarga_comprobante_proveedor';

    public function up(): void
    {
        if (Schema::hasColumn(self::TABLA, 'anita_scan_documento_id')) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->unsignedBigInteger('anita_scan_documento_id')
                ->nullable()
                ->after('anita_nro_interno');

            $table->index('anita_scan_documento_id', 'idx_precarga_anita_scan_documento');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLA, 'anita_scan_documento_id')) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropIndex('idx_precarga_anita_scan_documento');
            $table->dropColumn('anita_scan_documento_id');
        });
    }
};
