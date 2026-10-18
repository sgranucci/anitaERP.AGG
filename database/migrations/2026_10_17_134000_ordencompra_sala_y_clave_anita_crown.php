<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: sala_id (casino = penmp_sucursal) + clave Anita (tipo/letra/sucursal)
 * para distinguir OC con el mismo penmp_nro en distintas sucursales.
 * En AGG las columnas quedan null y no cambian el flujo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'sala_id')) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('empresa_id');
                $table->foreign('sala_id', 'fk_ordencompra_sala')
                    ->references('id')->on('sala')
                    ->nullOnDelete();
                $table->index('sala_id', 'idx_ordencompra_sala');
            }
            if (! Schema::hasColumn('ordencompra', 'anita_tipo')) {
                $table->string('anita_tipo', 3)->nullable()->after('numeroordencompra');
            }
            if (! Schema::hasColumn('ordencompra', 'anita_letra')) {
                $table->string('anita_letra', 1)->nullable()->after('anita_tipo');
            }
            if (! Schema::hasColumn('ordencompra', 'anita_sucursal')) {
                $table->unsignedSmallInteger('anita_sucursal')->nullable()->after('anita_letra');
            }
        });

        if (! Schema::hasIndex('ordencompra', 'uk_ordencompra_clave_anita')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->unique(
                    ['numeroordencompra', 'anita_tipo', 'anita_letra', 'anita_sucursal'],
                    'uk_ordencompra_clave_anita'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (Schema::hasIndex('ordencompra', 'uk_ordencompra_clave_anita')) {
                $table->dropUnique('uk_ordencompra_clave_anita');
            }
            if (Schema::hasColumn('ordencompra', 'sala_id')) {
                $table->dropForeign('fk_ordencompra_sala');
                $table->dropIndex('idx_ordencompra_sala');
                $table->dropColumn('sala_id');
            }
            foreach (['anita_sucursal', 'anita_letra', 'anita_tipo'] as $col) {
                if (Schema::hasColumn('ordencompra', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
