<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pulido premium: alias CBU, lote enviado, menú cockpit (sin formato banco a medida).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_formapago') && ! Schema::hasColumn('proveedor_formapago', 'alias_cbu')) {
            Schema::table('proveedor_formapago', function (Blueprint $table) {
                $table->string('alias_cbu', 80)->nullable()->after('cbu');
            });
        }

        if (Schema::hasTable('lote_bancario') && ! Schema::hasColumn('lote_bancario', 'enviado_banco_at')) {
            Schema::table('lote_bancario', function (Blueprint $table) {
                $table->timestamp('enviado_banco_at')->nullable()->after('exportado_at');
                $table->string('convenio_driver', 40)->nullable()->after('enviado_banco_at');
            });
        }

        if (Schema::hasTable('pagoproveedor') && ! Schema::hasColumn('pagoproveedor', 'bloqueado_banco')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                $table->boolean('bloqueado_banco')->default(false)->after('interbanking_transferencia_id');
            });
        }

        if (Schema::hasTable('lote_bancario_linea') && ! Schema::hasColumn('lote_bancario_linea', 'alias_cbu')) {
            Schema::table('lote_bancario_linea', function (Blueprint $table) {
                $table->string('alias_cbu', 80)->nullable()->after('cbu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('proveedor_formapago') && Schema::hasColumn('proveedor_formapago', 'alias_cbu')) {
            Schema::table('proveedor_formapago', function (Blueprint $table) {
                $table->dropColumn('alias_cbu');
            });
        }
        if (Schema::hasTable('lote_bancario')) {
            Schema::table('lote_bancario', function (Blueprint $table) {
                foreach (['enviado_banco_at', 'convenio_driver'] as $col) {
                    if (Schema::hasColumn('lote_bancario', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('pagoproveedor') && Schema::hasColumn('pagoproveedor', 'bloqueado_banco')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                $table->dropColumn('bloqueado_banco');
            });
        }
        if (Schema::hasTable('lote_bancario_linea') && Schema::hasColumn('lote_bancario_linea', 'alias_cbu')) {
            Schema::table('lote_bancario_linea', function (Blueprint $table) {
                $table->dropColumn('alias_cbu');
            });
        }
    }
};
