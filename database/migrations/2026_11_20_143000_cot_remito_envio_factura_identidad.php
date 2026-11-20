<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad de la factura asociada al COT (tipo/letra/PV/número).
 * Evita reenviar el mismo FAC con otro número de remito tras regeneraciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cot_remito_envio')) {
            return;
        }

        Schema::table('cot_remito_envio', function (Blueprint $table) {
            if (! Schema::hasColumn('cot_remito_envio', 'factura_tipo')) {
                $table->string('factura_tipo', 3)->nullable()->after('venta_id');
            }
            if (! Schema::hasColumn('cot_remito_envio', 'factura_letra')) {
                $table->string('factura_letra', 1)->nullable()->after('factura_tipo');
            }
            if (! Schema::hasColumn('cot_remito_envio', 'factura_sucursal')) {
                $table->unsignedInteger('factura_sucursal')->nullable()->after('factura_letra');
            }
            if (! Schema::hasColumn('cot_remito_envio', 'factura_numero')) {
                $table->unsignedInteger('factura_numero')->nullable()->after('factura_sucursal');
            }
        });

        if (! $this->indexExists('cot_remito_envio', 'cot_remito_envio_factura_idx')) {
            Schema::table('cot_remito_envio', function (Blueprint $table) {
                $table->index(
                    ['factura_tipo', 'factura_letra', 'factura_sucursal', 'factura_numero'],
                    'cot_remito_envio_factura_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cot_remito_envio')) {
            return;
        }

        if ($this->indexExists('cot_remito_envio', 'cot_remito_envio_factura_idx')) {
            Schema::table('cot_remito_envio', function (Blueprint $table) {
                $table->dropIndex('cot_remito_envio_factura_idx');
            });
        }

        Schema::table('cot_remito_envio', function (Blueprint $table) {
            foreach (['factura_numero', 'factura_sucursal', 'factura_letra', 'factura_tipo'] as $col) {
                if (Schema::hasColumn('cot_remito_envio', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneIndice($table, $indexName);
    }
};
