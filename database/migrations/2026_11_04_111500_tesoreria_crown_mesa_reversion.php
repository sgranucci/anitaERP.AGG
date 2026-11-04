<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La reversa de un movimiento de mesa es otro movimiento (revierte_id),
 * con las cantidades al revés y el motivo escrito. El original queda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_mesa')) {
            return;
        }

        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_crown_mesa', 'motivo')) {
                $table->string('motivo', 500)->nullable()->after('comentario');
            }
            if (! Schema::hasColumn('tesoreria_crown_mesa', 'revierte_id')) {
                $table->unsignedBigInteger('revierte_id')->nullable()->after('banca_id');
                $table->foreign('revierte_id', 'tc_mesa_revierte_fk')
                    ->references('id')->on('tesoreria_crown_mesa');
            }
        });

        DB::table('permiso')->where('slug', 'editar-tesoreria-crown-mesa')->update([
            'ayuda' => 'Confirmar un movimiento de mesa y revertirlo. Al confirmar guarda la vigencia de la banca de esa fecha. Revertir graba otro movimiento, con el número siguiente y las cantidades al revés; el original queda. No borra un borrador.',
            'updated_at' => now(),
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_mesa')) {
            return;
        }

        Schema::table('tesoreria_crown_mesa', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_crown_mesa', 'revierte_id')) {
                $table->dropForeign('tc_mesa_revierte_fk');
                $table->dropColumn('revierte_id');
            }
            if (Schema::hasColumn('tesoreria_crown_mesa', 'motivo')) {
                $table->dropColumn('motivo');
            }
        });

        DB::table('permiso')->where('slug', 'editar-tesoreria-crown-mesa')->update([
            'ayuda' => 'Confirmar un movimiento de mesa y anular uno ya confirmado. Al confirmar guarda la vigencia de la banca de esa fecha. Anular lo saca del stock y deja el número. No borra un borrador y no arma una reversa.',
            'updated_at' => now(),
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }
};
