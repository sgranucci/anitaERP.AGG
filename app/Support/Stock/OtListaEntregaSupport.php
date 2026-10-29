<?php

namespace App\Support\Stock;

/**
 * ¿La OT está terminada para entregar o consumir?
 *
 * Usa la misma lectura que la consulta Stock por OT: entrega inmediata sí;
 * en producción o pendiente de fabricación no. El resto del sistema avisa
 * con este resultado y no reimplementa las tareas de cierre ni el empaque.
 */
final class OtListaEntregaSupport
{
    /**
     * @param  array{situacion?: string, en_produccion?: bool}|null  $metaOt
     * @return array{lista: bool, situacion: string, en_produccion: bool, mensaje: ?string}
     */
    public static function evaluar(
        int $ordentrabajoId,
        int $depositoId = 0,
        ?array $metaOt = null,
        string $codigo = '',
        bool $enProduccionForzada = false,
        bool $esAltapExcel = false
    ): array {
        if ($ordentrabajoId <= 0 && ! $enProduccionForzada) {
            return self::entregaInmediata();
        }

        $sit = ReporteStockOtSituacionSupport::situacionFila(
            $enProduccionForzada,
            $depositoId,
            $metaOt,
            $esAltapExcel
        );

        return self::desdeSituacion(
            (string) $sit['situacion'],
            (bool) $sit['en_produccion'],
            $codigo
        );
    }

    /**
     * @param  list<int>  $ordentrabajoIds
     */
    public static function avisoDeIds(array $ordentrabajoIds, int $depositoId, string $codigo): ?string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ordentrabajoIds))));
        if ($ids === []) {
            return null;
        }

        $metas = ReporteStockOtSituacionSupport::situacionesPorOrdentrabajoIds($ids);
        $mensajes = [];
        foreach ($ids as $id) {
            $ev = self::evaluar($id, $depositoId, $metas[$id] ?? null, $codigo);
            if ($ev['lista'] || $ev['mensaje'] === null) {
                continue;
            }
            $mensajes[$ev['mensaje']] = $ev['mensaje'];
        }

        if ($mensajes === []) {
            return null;
        }

        return implode(' ', array_values($mensajes));
    }

    /**
     * Completa cada fila de la consulta de lotes/OT con la situación de Stock por OT.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function aplicarAFilas(array $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            if (($fila['origen'] ?? '') !== 'OT') {
                continue;
            }
            $otId = (int) ($fila['ordentrabajo_id'] ?? 0);
            if ($otId > 0) {
                $ids[] = $otId;
            }
        }

        $metas = ReporteStockOtSituacionSupport::situacionesPorOrdentrabajoIds($ids);
        foreach ($filas as $i => $fila) {
            $otId = (int) ($fila['ordentrabajo_id'] ?? 0);
            $codigo = trim((string) ($fila['lote'] ?? ''));
            if (($fila['origen'] ?? '') !== 'OT' || $otId <= 0) {
                $filas[$i] = self::volcarEnFila($fila, self::entregaInmediata());

                continue;
            }

            $filas[$i] = self::volcarEnFila($fila, self::evaluar(
                $otId,
                (int) ($fila['deposito_id'] ?? 0),
                $metas[$otId] ?? null,
                $codigo
            ));
        }

        return $filas;
    }

    /**
     * @return array{lista: bool, situacion: string, en_produccion: bool, mensaje: ?string}
     */
    public static function desdeSituacion(string $situacion, bool $enProduccion, string $codigo = ''): array
    {
        $lista = ReporteStockOtSituacionSupport::listaParaEntrega($situacion, $enProduccion);

        return [
            'lista' => $lista,
            'situacion' => $situacion,
            'en_produccion' => $enProduccion,
            'mensaje' => $lista ? null : self::mensaje($codigo, $situacion),
        ];
    }

    public static function mensaje(string $codigo, string $situacion): string
    {
        $codigo = trim($codigo);
        $quien = $codigo !== '' ? 'La OT '.$codigo : 'La OT';

        return $quien.' no está terminada ('.$situacion.').';
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array{lista: bool, situacion: string, en_produccion: bool, mensaje: ?string}  $evaluacion
     * @return array<string, mixed>
     */
    private static function volcarEnFila(array $fila, array $evaluacion): array
    {
        $fila['situacion'] = $evaluacion['situacion'];
        $fila['en_produccion'] = $evaluacion['en_produccion'];
        $fila['lista_entrega'] = $evaluacion['lista'];
        $fila['aviso_ot'] = $evaluacion['mensaje'];

        return $fila;
    }

    /**
     * @return array{lista: bool, situacion: string, en_produccion: bool, mensaje: null}
     */
    private static function entregaInmediata(): array
    {
        return [
            'lista' => true,
            'situacion' => ReporteStockOtSituacionSupport::ENTREGA_INMEDIATA,
            'en_produccion' => false,
            'mensaje' => null,
        ];
    }
}
