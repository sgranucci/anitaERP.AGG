<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En AGG el resultado de la presentación automática de CAEA va a
 * Sergio Granucci y Guillermo Surace.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('parametro_sistema') || ! Schema::hasTable('usuario')) {
            return;
        }

        $mails = DB::table('usuario')
            ->where('suspendido', 0)
            ->where(function ($query): void {
                $query->where('usuario', 'gsurace')
                    ->orWhere('email', 'sergiogranucci@gmail.com');
            })
            ->orderBy('email')
            ->pluck('email')
            ->map(static fn ($email): string => strtolower(trim((string) $email)))
            ->filter(static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->implode(',');

        if ($mails === '') {
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
                'valor' => $mails,
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
                'valor' => $mails,
                'orden' => $def['orden'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('parametro_sistema.mapa');
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('parametro_sistema')) {
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
