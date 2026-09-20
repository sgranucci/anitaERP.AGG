<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El descarte de un scan de Anita era irreversible y sin explicación: se registraba como
 * efecto secundario de borrar la precarga, sin motivo y sin forma de volver atrás. Si el
 * operador se equivocaba, la factura desaparecía del legajo y no había manera de recuperarla
 * desde la UI. Con motivo + reversión el descarte pasa a ser una acción explícita y auditable.
 */
return new class extends Migration
{
    private const TABLA = 'ordencompra_legajo_scan_anita_descartado';

    public function up(): void
    {
        Schema::table(self::TABLA, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLA, 'motivo')) {
                $table->string('motivo', 255)->nullable()->after('precarga_id_origen')
                    ->comment('Por qué se descartó el scan del legajo');
            }
            if (! Schema::hasColumn(self::TABLA, 'revertido_at')) {
                $table->timestamp('revertido_at')->nullable()->after('user_id')
                    ->comment('Si está informado, el descarte fue deshecho y el scan vuelve al legajo');
            }
            if (! Schema::hasColumn(self::TABLA, 'revertido_user_id')) {
                $table->unsignedBigInteger('revertido_user_id')->nullable()->after('revertido_at')
                    ->comment('Usuario que deshizo el descarte');
            }
            if (! Schema::hasColumn(self::TABLA, 'revertido_motivo')) {
                $table->string('revertido_motivo', 255)->nullable()->after('revertido_user_id')
                    ->comment('Por qué se deshizo el descarte');
            }
        });

        Schema::table(self::TABLA, function (Blueprint $table) {
            // Los descartes vigentes se consultan por OC en cada render de la bandeja.
            $table->index(['ordencompra_id', 'revertido_at'], 'idx_scan_descartado_oc_vigente');
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropIndex('idx_scan_descartado_oc_vigente');
        });

        Schema::table(self::TABLA, function (Blueprint $table) {
            foreach (['motivo', 'revertido_at', 'revertido_user_id', 'revertido_motivo'] as $col) {
                if (Schema::hasColumn(self::TABLA, $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
