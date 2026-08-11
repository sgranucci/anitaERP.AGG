<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tara (bin/carro) en líneas de recepción Surmar.
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'peso_tara')) {
                $table->decimal('peso_tara', 18, 4)->nullable()->after('peso_bruto');
            }
        });
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'peso_tara')) {
                $table->dropColumn('peso_tara');
            }
        });
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
