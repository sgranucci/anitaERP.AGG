<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class EloquentActualizacionPorLotesSupport
{
    /**
     * Actualiza registros en lotes ordenados por PK, reconsultando candidatos en cada vuelta.
     *
     * Salvaguardas anti-loop:
     * - tope de iteraciones (`max_iteraciones`);
     * - verificación final de candidatos pendientes (`verificar_pendientes`);
     * - retry con backoff por lote ante deadlock / lock wait timeout.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $queryCandidatos
     * @param  array<string, mixed>  $valoresUpdate
     * @param  array<string, mixed>  $opciones
     */
    public static function actualizarCandidatosEnLotes(Builder $queryCandidatos, array $valoresUpdate, array $opciones = []): int
    {
        $tamanoLote = max(1, (int) ($opciones['tamano_lote'] ?? 100));
        $maxIteraciones = max(1, (int) ($opciones['max_iteraciones'] ?? 500));
        $reintentos = max(1, (int) ($opciones['reintentos_deadlock'] ?? 5));
        $esperaMs = max(50, (int) ($opciones['espera_reintento_ms'] ?? 150));
        $contexto = (string) ($opciones['contexto'] ?? 'eloquent.actualizacion_lotes');
        $verificarPendientes = ! array_key_exists('verificar_pendientes', $opciones)
            || (bool) $opciones['verificar_pendientes'];

        $model = $queryCandidatos->getModel();
        $keyName = $model->getQualifiedKeyName();

        $total = 0;
        $ultimoId = 0;
        $iteracion = 0;

        while (true) {
            $iteracion++;
            if ($iteracion > $maxIteraciones) {
                self::fallarPorLimiteIteraciones($queryCandidatos, $contexto, $maxIteraciones, $ultimoId, $total, $opciones);
            }

            $ids = (clone $queryCandidatos)
                ->reorder()
                ->where($keyName, '>', $ultimoId)
                ->orderBy($keyName)
                ->limit($tamanoLote)
                ->pluck($keyName);

            if ($ids->isEmpty()) {
                break;
            }

            $ultimoId = (int) $ids->max();

            $afectadas = (int) DbContencionSupport::ejecutarConReintento(
                function () use ($queryCandidatos, $ids, $valoresUpdate, $keyName): int {
                    return (clone $queryCandidatos)
                        ->whereIn($keyName, $ids->all())
                        ->update($valoresUpdate);
                },
                [
                    'max_intentos' => $reintentos,
                    'espera_inicial_ms' => $esperaMs,
                    'contexto' => $contexto,
                ]
            );

            $total += $afectadas;
        }

        if ($verificarPendientes) {
            self::exigirSinCandidatosPendientes($queryCandidatos, $contexto, $total, $opciones);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $opciones
     */
    private static function fallarPorLimiteIteraciones(
        Builder $queryCandidatos,
        string $contexto,
        int $maxIteraciones,
        int $ultimoId,
        int $total,
        array $opciones,
    ): void {
        $model = $queryCandidatos->getModel();
        $keyName = $model->getQualifiedKeyName();
        $pendientes = (clone $queryCandidatos)
            ->where($keyName, '>', $ultimoId)
            ->count();

        Log::error($contexto.': límite de iteraciones alcanzado con candidatos pendientes', [
            'max_iteraciones' => $maxIteraciones,
            'ultimo_id' => $ultimoId,
            'pendientes_estimados' => $pendientes,
            'total_actualizadas' => $total,
            'opciones' => self::opcionesParaLog($opciones),
        ]);

        throw new RuntimeException(
            'No se completó la actualización por lotes: se alcanzó el límite de '
            .$maxIteraciones.' iteración(es) con candidatos pendientes. '
            .'Revise logs ('.$contexto.') o contacte soporte.'
        );
    }

    /**
     * @param  array<string, mixed>  $opciones
     */
    private static function exigirSinCandidatosPendientes(
        Builder $queryCandidatos,
        string $contexto,
        int $total,
        array $opciones,
    ): void {
        $pendientes = (clone $queryCandidatos)->count();
        if ($pendientes <= 0) {
            return;
        }

        Log::error($contexto.': quedaron candidatos sin actualizar tras el proceso por lotes', [
            'pendientes' => $pendientes,
            'total_actualizadas' => $total,
            'opciones' => self::opcionesParaLog($opciones),
        ]);

        throw new RuntimeException(
            'Quedaron '.$pendientes.' registro(s) pendientes de actualizar tras el proceso por lotes. '
            .'Intente nuevamente o contacte soporte.'
        );
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array<string, mixed>
     */
    private static function opcionesParaLog(array $opciones): array
    {
        unset($opciones['contexto']);

        return $opciones;
    }
}
