<?php

namespace App\Support\ReglaNegocio;

use App\Models\Configuracion\AvisoRegla;
use App\Models\Configuracion\AvisoReglaConfig;
use App\Models\Configuracion\ReglaNegocio;
use App\Models\Configuracion\ReglaNegocioConfig;
use Illuminate\Support\Facades\Schema;

/**
 * F5 — puente conceptual entre motor sync (regla_negocio) y async avisos (aviso_regla).
 * Comparten evaluador IF; storage y runtime siguen separados a propósito.
 */
final class ReglaNegocioAvisoBridgeSupport
{
    /**
     * @return array{
     *   evaluador_compartido: bool,
     *   bre_motor: bool,
     *   aviso_motor: bool,
     *   bre_reglas: int,
     *   bre_activas: int,
     *   aviso_reglas: int,
     *   aviso_activas: int,
     *   mensaje: string
     * }
     */
    public static function estado(): array
    {
        $breReglas = 0;
        $breActivas = 0;
        $avisoReglas = 0;
        $avisoActivas = 0;
        $breMotor = false;
        $avisoMotor = false;

        if (Schema::hasTable('regla_negocio')) {
            $breReglas = (int) ReglaNegocio::query()->count();
            $breActivas = (int) ReglaNegocio::query()->where('activo', true)->count();
        }
        if (Schema::hasTable('regla_negocio_config')) {
            $breMotor = (bool) ReglaNegocioConfig::actual()->motor_habilitado
                && (bool) config('regla_negocio.habilitado_entorno', true);
        }
        if (Schema::hasTable('aviso_regla')) {
            $avisoReglas = (int) AvisoRegla::query()->count();
            $avisoActivas = (int) AvisoRegla::query()->where('activo', true)->count();
        }
        if (Schema::hasTable('aviso_regla_config')) {
            $avisoMotor = (bool) AvisoReglaConfig::actual()->motor_habilitado
                && (bool) config('aviso_reglas.habilitado_entorno', true);
        }

        return [
            'evaluador_compartido' => true,
            'bre_motor' => $breMotor,
            'aviso_motor' => $avisoMotor,
            'bre_reglas' => $breReglas,
            'bre_activas' => $breActivas,
            'aviso_reglas' => $avisoReglas,
            'aviso_activas' => $avisoActivas,
            'mensaje' => 'Sync (regla_negocio) decide en Services; async (aviso_regla) notifica tras eventos. '
                .'Evaluador IF compartido (ReglaNegocioCondicionSupport). Storage unificado = no-objetivo cercano.',
        ];
    }
}
