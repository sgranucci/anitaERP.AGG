<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legado: estado final de mesa (F) con propina → movimiento bóveda PROP/Mesas.
 * La propina de mesa ya no se asienta en bóveda: queda en el movimiento de mesa
 * (Anexo II + reporte propinas por casino). Este support solo limpia vínculos viejos.
 */
final class TesoreriaCrownMesaPropinaBovedaSupport
{
    /**
     * Ya no genera PROP/Mesas. La propina vive en el estado final.
     *
     * @return string|null error
     */
    public static function asentarDesdeEstadoFinal(TesoreriaCrownMesa $mesa, ?Usuario $usuario = null): ?string
    {
        return null;
    }

    /**
     * Anula el PROP/MES generado desde el estado final (si existe y sigue confirmado).
     */
    public static function anularVinculada(TesoreriaCrownMesa $mesa): void
    {
        if (! Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return;
        }
        $bovedaId = (int) ($mesa->boveda_id ?? 0);
        if ($bovedaId <= 0) {
            return;
        }
        $boveda = TesoreriaCrownBoveda::query()->find($bovedaId);
        if ($boveda === null || $boveda->estado !== TesoreriaCrownBoveda::CONFIRMADO) {
            return;
        }
        if ($boveda->reversa !== null || $boveda->revierte_id !== null) {
            return;
        }
        $boveda->estado = TesoreriaCrownBoveda::ANULADO;
        $boveda->save();
        if ($mesa->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($mesa->jornada);
        }
    }

    /**
     * Compensa el PROP/MES legado del estado final original al revertir la mesa.
     *
     * @return string|null error
     */
    public static function compensarAlRevertir(
        TesoreriaCrownMesa $original,
        TesoreriaCrownMesa $reversa,
        Usuario $usuario,
        string $motivo
    ): ?string {
        if (! Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            return null;
        }
        $bovedaId = (int) ($original->boveda_id ?? 0);
        if ($bovedaId <= 0) {
            return null;
        }
        $boveda = TesoreriaCrownBoveda::query()->with('lineas')->find($bovedaId);
        if ($boveda === null || $boveda->estado !== TesoreriaCrownBoveda::CONFIRMADO) {
            return null;
        }
        if ($boveda->revierte_id !== null || $boveda->reversa !== null) {
            return null;
        }
        $lineas = $boveda->lineas->filter(fn ($l) => abs((float) $l->cantidad) >= 0.0001);
        if ($lineas->isEmpty()) {
            $boveda->estado = TesoreriaCrownBoveda::ANULADO;
            $boveda->save();
            if ($original->jornada) {
                TesoreriaCrownAnexo8Calculo::invalidarCache($original->jornada);
            }

            return null;
        }

        DB::transaction(function () use ($boveda, $lineas, $usuario, $motivo, $reversa) {
            $ahora = now();
            $nuevo = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $boveda->sala_id,
                'empresa_id' => $boveda->empresa_id,
                'jornada_id' => $boveda->jornada_id,
                'deposito_id' => $boveda->deposito_id,
                'tipo_id' => $boveda->tipo_id,
                'subtipo_id' => $boveda->subtipo_id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $boveda->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                'comentario' => 'Reversa de '.TesoreriaCrownNumeradorSupport::visible((int) $boveda->numero),
                'motivo' => $motivo,
                'hora' => $ahora->format('H:i:s'),
                'revierte_id' => $boveda->id,
                'usuario_id' => $usuario->id,
                'confirmado_at' => $ahora,
                'importe_pesos' => $boveda->importe_pesos !== null
                    ? round((float) $boveda->importe_pesos * -1, 2)
                    : null,
            ]);
            foreach ($lineas as $linea) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $nuevo->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => round((float) $linea->cantidad * -1, 2),
                ]);
            }
            $reversa->boveda_id = $nuevo->id;
            $reversa->save();
        });

        if ($original->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($original->jornada);
        }

        return null;
    }
}
