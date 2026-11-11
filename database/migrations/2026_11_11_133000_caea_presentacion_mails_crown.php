<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En Crown el resultado de la presentación automática de CAEA va a
 * lroldan@casinosdelrio.com.ar (editable en Configuración general).
 */
return new class extends Migration
{
    private const MAIL_CROWN = 'lroldan@casinosdelrio.com.ar';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_CAEA_PRESENTACION_MAILS;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if ($def === null) {
            return;
        }

        $existe = DB::table('parametro_sistema')->where('clave', $clave)->exists();
        if ($existe) {
            DB::table('parametro_sistema')->where('clave', $clave)->update([
                'grupo' => $def['grupo'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'valor' => self::MAIL_CROWN,
                'orden' => $def['orden'],
                'updated_at' => now(),
            ]);
        } else {
            DB::table('parametro_sistema')->insert([
                'clave' => $clave,
                'grupo' => $def['grupo'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'valor' => self::MAIL_CROWN,
                'orden' => $def['orden'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('parametro_sistema.mapa');
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        DB::table('parametro_sistema')
            ->where('clave', ParametroSistemaSupport::CLAVE_CAEA_PRESENTACION_MAILS)
            ->update([
                'valor' => '',
                'updated_at' => now(),
            ]);
        Cache::forget('parametro_sistema.mapa');
    }
};
