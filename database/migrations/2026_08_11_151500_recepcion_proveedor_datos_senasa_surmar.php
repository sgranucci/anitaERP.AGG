<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos SENASA de recepción Surmar (Anita carga_certificado / defaults COM).
 * Cabecera: certificado, tropa, temperatura, destino, cámara, nro establecimiento.
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'certificado_senasa')) {
                $table->string('certificado_senasa', 30)->nullable()->after('observacion');
            }
            if (! Schema::hasColumn('recepcion_proveedor', 'tropa')) {
                $table->unsignedInteger('tropa')->nullable()->after('certificado_senasa');
            }
            if (! Schema::hasColumn('recepcion_proveedor', 'temperatura_ingreso')) {
                $table->decimal('temperatura_ingreso', 8, 2)->nullable()->after('tropa');
            }
            if (! Schema::hasColumn('recepcion_proveedor', 'destino_senasa')) {
                $table->string('destino_senasa', 60)->nullable()->after('temperatura_ingreso');
            }
            if (! Schema::hasColumn('recepcion_proveedor', 'camara')) {
                $table->string('camara', 60)->nullable()->after('destino_senasa');
            }
            if (! Schema::hasColumn('recepcion_proveedor', 'nro_establecimiento')) {
                $table->unsignedInteger('nro_establecimiento')->nullable()->after('camara');
            }
        });
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            foreach (['nro_establecimiento', 'camara', 'destino_senasa', 'temperatura_ingreso', 'tropa', 'certificado_senasa'] as $col) {
                if (Schema::hasColumn('recepcion_proveedor', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
