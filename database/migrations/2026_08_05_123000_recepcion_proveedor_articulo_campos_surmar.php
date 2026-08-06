<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos Surmar en líneas de recepción (lote/pesos/piqueo).
 * Solo EL BIERZO. En AGG no tiene efecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'lote_proveedor')) {
                $table->string('lote_proveedor', 30)->nullable()->after('lote_id');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'certificado')) {
                $table->string('certificado', 30)->nullable()->after('lote_proveedor');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'fecha_vto')) {
                $table->date('fecha_vto')->nullable()->after('certificado');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'peso_bruto')) {
                $table->decimal('peso_bruto', 18, 4)->nullable()->after('fecha_vto');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'peso_neto')) {
                $table->decimal('peso_neto', 18, 4)->nullable()->after('peso_bruto');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'cant_pieza')) {
                $table->decimal('cant_pieza', 18, 4)->nullable()->after('peso_neto');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'hora_piqueo')) {
                $table->string('hora_piqueo', 5)->nullable()->after('cant_pieza');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'piqueado_at')) {
                $table->timestamp('piqueado_at')->nullable()->after('hora_piqueo');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'stock_etiqueta_id')) {
                $table->unsignedBigInteger('stock_etiqueta_id')->nullable()->after('piqueado_at');
            }
        });

        if (Schema::hasColumn('recepcion_proveedor_articulo', 'stock_etiqueta_id')) {
            try {
                Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
                    $table->foreign('stock_etiqueta_id', 'fk_rpa_stock_etiqueta')
                        ->references('id')
                        ->on('stock_etiqueta')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // índice/FK ya existe
            }
        }
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'stock_etiqueta_id')) {
                try {
                    $table->dropForeign('fk_rpa_stock_etiqueta');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('stock_etiqueta_id');
            }
            foreach (['piqueado_at', 'hora_piqueo', 'cant_pieza', 'peso_neto', 'peso_bruto', 'fecha_vto', 'certificado', 'lote_proveedor'] as $col) {
                if (Schema::hasColumn('recepcion_proveedor_articulo', $col)) {
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
