<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: el mismo numerorecepcion puede repetirse en distintas sucursales (casino).
 * La unique (empresa_id, numerorecepcion) bloqueaba el import histórico.
 * Pasa a clave Anita (tipo/letra/sucursal/nro).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recepcion_proveedor')) {
            return;
        }

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasIndex('recepcion_proveedor', 'uk_recep_prov_empresa_nro')) {
                $table->dropUnique('uk_recep_prov_empresa_nro');
            }
            if (! Schema::hasIndex('recepcion_proveedor', 'idx_recep_prov_empresa_nro')) {
                $table->index(['empresa_id', 'numerorecepcion'], 'idx_recep_prov_empresa_nro');
            }
            if (! Schema::hasIndex('recepcion_proveedor', 'uk_recep_prov_clave_anita')) {
                $table->unique(
                    ['anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro'],
                    'uk_recep_prov_clave_anita'
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recepcion_proveedor')) {
            return;
        }

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasIndex('recepcion_proveedor', 'uk_recep_prov_clave_anita')) {
                $table->dropUnique('uk_recep_prov_clave_anita');
            }
            if (Schema::hasIndex('recepcion_proveedor', 'idx_recep_prov_empresa_nro')) {
                $table->dropIndex('idx_recep_prov_empresa_nro');
            }
            if (! Schema::hasIndex('recepcion_proveedor', 'uk_recep_prov_empresa_nro')) {
                $table->unique(['empresa_id', 'numerorecepcion'], 'uk_recep_prov_empresa_nro');
            }
        });
    }
};
