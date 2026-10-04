<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitante de OC distinto de quien carga, y preferencia por usuario.
 * El parámetro de Configuración general se siembra solo en El Bierzo (activo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordencompra') && ! Schema::hasColumn('ordencompra', 'solicitante_usuario_id')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->unsignedBigInteger('solicitante_usuario_id')->nullable()->after('creousuario_id');
                $table->foreign('solicitante_usuario_id', 'fk_ordencompra_solicitante')
                    ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('ordencompra_solicitante_preferencia')) {
            Schema::create('ordencompra_solicitante_preferencia', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_id')->primary();
                $table->foreign('usuario_id', 'fk_ocsolpref_usuario')
                    ->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
                $table->unsignedBigInteger('solicitante_usuario_id');
                $table->foreign('solicitante_usuario_id', 'fk_ocsolpref_solicitante')
                    ->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
                $table->timestamps();
            });
        }

        $this->sembrarParametroBierzo();
    }

    public function down(): void
    {
        if (Schema::hasTable('parametro_sistema')) {
            DB::table('parametro_sistema')
                ->where('clave', ParametroSistemaSupport::CLAVE_OC_SOLICITANTE_EDITABLE)
                ->delete();
            Cache::forget('parametro_sistema.mapa');
        }

        Schema::dropIfExists('ordencompra_solicitante_preferencia');

        if (Schema::hasTable('ordencompra') && Schema::hasColumn('ordencompra', 'solicitante_usuario_id')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->dropForeign('fk_ordencompra_solicitante');
                $table->dropColumn('solicitante_usuario_id');
            });
        }
    }

    private function sembrarParametroBierzo(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_OC_SOLICITANTE_EDITABLE;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def)) {
            return;
        }

        $existe = DB::table('parametro_sistema')->where('clave', $clave)->exists();
        if ($existe) {
            return;
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => '1',
            'orden' => $def['orden'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('parametro_sistema.mapa');
    }
};
