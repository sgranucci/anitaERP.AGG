<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hora y destinatarios de la presentación automática de CAEA.
 * En El Bierzo los mails iniciales son Claudia Martinez y Sergio Granucci.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $this->upsert(
            ParametroSistemaSupport::CLAVE_CAEA_PRESENTACION_HORA,
            '14:00',
        );

        $mails = '';
        if (EntornoEmpresaSupport::esElBierzo() && Schema::hasTable('usuario')) {
            $mails = DB::table('usuario')
                ->whereIn('usuario', ['claudiam', 'sergio'])
                ->where('suspendido', 0)
                ->orderBy('usuario')
                ->pluck('email')
                ->map(static fn ($email): string => strtolower(trim((string) $email)))
                ->filter(static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->implode(',');
        }
        $this->upsert(
            ParametroSistemaSupport::CLAVE_CAEA_PRESENTACION_MAILS,
            $mails,
        );

        Cache::forget('parametro_sistema.mapa');
    }

    public function down(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        DB::table('parametro_sistema')
            ->whereIn('clave', [
                ParametroSistemaSupport::CLAVE_CAEA_PRESENTACION_HORA,
                ParametroSistemaSupport::CLAVE_CAEA_PRESENTACION_MAILS,
            ])
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }

    private function upsert(string $clave, string $valor): void
    {
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
                'orden' => $def['orden'],
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $valor,
            'orden' => $def['orden'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
