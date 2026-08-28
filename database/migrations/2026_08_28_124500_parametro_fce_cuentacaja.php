<?php

use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Ventas\ArcaFceDatosAdicionalesSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $existe = DB::table('parametro_sistema')
            ->where('clave', ParametroSistemaSupport::CLAVE_FCE_CUENTACAJA_ID)
            ->exists();
        if ($existe) {
            return;
        }

        $codigos = [
            ArcaFceDatosAdicionalesSupport::CUENTA_TESORERIA_ANITA,
            ltrim(ArcaFceDatosAdicionalesSupport::CUENTA_TESORERIA_ANITA, '0') ?: '0',
        ];
        $cuentaId = 0;
        if (Schema::hasTable('cuentacaja')) {
            $cuentaId = (int) (DB::table('cuentacaja')->whereIn('codigo', $codigos)->value('id') ?? 0);
            if ($cuentaId <= 0) {
                $cbu = preg_replace('/\D+/', '', (string) config('arca.caea.fce.cbu_emisor', '')) ?? '';
                if (strlen($cbu) === 22) {
                    $cuentaId = (int) (DB::table('cuentacaja')->where('cbu', $cbu)->value('id') ?? 0);
                }
            }
        }

        $def = ParametroSistemaSupport::definiciones()[ParametroSistemaSupport::CLAVE_FCE_CUENTACAJA_ID];

        DB::table('parametro_sistema')->insert([
            'clave' => ParametroSistemaSupport::CLAVE_FCE_CUENTACAJA_ID,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $cuentaId > 0 ? (string) $cuentaId : '',
            'orden' => $def['orden'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        DB::table('parametro_sistema')
            ->where('clave', ParametroSistemaSupport::CLAVE_FCE_CUENTACAJA_ID)
            ->delete();
    }
};
