<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado P (provisorio) + vínculo a factura de proveedor para el alta desde FIU/etc.
 * char(1) ya admite P; no hace falta ALTER del tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bien_uso')) {
            return;
        }

        if (! Schema::hasColumn('bien_uso', 'comprobante_proveedor_id')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable()->after('proveedor_id');
                $table->index('comprobante_proveedor_id', 'bien_uso_comprobante_proveedor_id_idx');
            });

            if (Schema::hasTable('comprobante_proveedor')) {
                Schema::table('bien_uso', function (Blueprint $table) {
                    $table->foreign('comprobante_proveedor_id', 'fk_bien_uso_comprobante_proveedor')
                        ->references('id')
                        ->on('comprobante_proveedor')
                        ->onDelete('restrict');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bien_uso') || ! Schema::hasColumn('bien_uso', 'comprobante_proveedor_id')) {
            return;
        }

        Schema::table('bien_uso', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_bien_uso_comprobante_proveedor');
            } catch (\Throwable $e) {
                // índice/FK puede no existir en installs parciales
            }
            try {
                $table->dropIndex('bien_uso_comprobante_proveedor_id_idx');
            } catch (\Throwable $e) {
            }
            $table->dropColumn('comprobante_proveedor_id');
        });
    }
};
