<?php

namespace App\Support\Ventas;

use Carbon\Carbon;

/**
 * Separa observaciones de habilitación del historial de anulaciones de cierre
 * para comprobantes PDF (evita rebalse por JSON snapshot).
 */
final class GastronomiaTurnoObservacionHabilitacionSupport
{
    private const PREFIJO_ANULACION = '[Anulación cierre';

    /**
     * @return array{
     *     texto_habilitacion: string,
     *     anulaciones: list<array{
     *         fecha_anulacion: string,
     *         usuario: string,
     *         pc: string,
     *         cierre_numero: string,
     *         cierre_fecha: string,
     *         motivo: string
     *     }>
     * }
     */
    public static function parse(?string $observacion): array
    {
        $obs = trim((string) $observacion);
        if ($obs === '') {
            return [
                'texto_habilitacion' => '',
                'anulaciones' => [],
            ];
        }

        $pos = mb_strpos($obs, self::PREFIJO_ANULACION);
        if ($pos === false) {
            return [
                'texto_habilitacion' => $obs,
                'anulaciones' => [],
            ];
        }

        $texto = trim(mb_substr($obs, 0, $pos));
        $bloqueAnulaciones = mb_substr($obs, $pos);

        return [
            'texto_habilitacion' => $texto,
            'anulaciones' => self::parseAnulaciones($bloqueAnulaciones),
        ];
    }

    /**
     * Nota compacta al corregir el monto de habilitación con turno aún abierto.
     */
    public static function notaModificacionMonto(
        int $usuarioId,
        string $usuarioNombre,
        string $identificadorPc,
        float $montoAnterior,
        float $montoNuevo,
        ?string $motivo,
    ): string {
        $motivoLimpio = trim((string) $motivo);
        $linea = '[Corrección monto hab. '.now()->format('Y-m-d H:i')
            .' user #'.$usuarioId.' '.$usuarioNombre
            .' PC '.$identificadorPc
            .'] $'.number_format($montoAnterior, 2, '.', '')
            .' → $'.number_format($montoNuevo, 2, '.', '');
        if ($motivoLimpio !== '') {
            $linea .= '. Motivo: '.$motivoLimpio;
        }

        return $linea;
    }

    /**
     * Nota compacta para append al anular (sin JSON; el snapshot queda en Log).
     */
    public static function notaAnulacionCierre(
        int $usuarioId,
        string $usuarioNombre,
        string $identificadorPc,
        ?int $numeroCierre,
        ?Carbon $cierreEn,
        ?string $motivo,
    ): string {
        $motivoLimpio = trim((string) $motivo);
        if ($motivoLimpio === '') {
            $motivoLimpio = '(sin detalle)';
        }

        return '[Anulación cierre '.now()->format('Y-m-d H:i')
            .' user #'.$usuarioId.' '.$usuarioNombre
            .' PC '.$identificadorPc
            .'] Cierre #'.($numeroCierre ?? '—')
            .' del '.($cierreEn?->format('d/m/Y H:i') ?? '—')
            .'. Motivo: '.$motivoLimpio;
    }

    /**
     * Nota compacta al corregir arqueo / ajustes de un cierre definitivo ya registrado.
     */
    public static function notaCorreccionArqueoCierre(
        int $usuarioId,
        string $usuarioNombre,
        ?int $numeroCierre,
        ?Carbon $cierreEn,
        ?string $motivo,
    ): string {
        $motivoLimpio = trim((string) $motivo);
        if ($motivoLimpio === '') {
            $motivoLimpio = '(sin detalle)';
        }

        return '[Corrección arqueo '.now()->format('Y-m-d H:i')
            .' user #'.$usuarioId.' '.$usuarioNombre
            .'] Cierre #'.($numeroCierre ?? '—')
            .' del '.($cierreEn?->format('d/m/Y H:i') ?? '—')
            .'. Motivo: '.$motivoLimpio;
    }

    /**
     * @return list<array{
     *     fecha_anulacion: string,
     *     usuario: string,
     *     pc: string,
     *     cierre_numero: string,
     *     cierre_fecha: string,
     *     motivo: string
     * }>
     */
    private static function parseAnulaciones(string $bloque): array
    {
        $partes = preg_split('/(?=\[Anulación cierre)/u', $bloque, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];

        foreach ($partes as $parte) {
            $parsed = self::parseUnaAnulacion(trim($parte));
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     fecha_anulacion: string,
     *     usuario: string,
     *     pc: string,
     *     cierre_numero: string,
     *     cierre_fecha: string,
     *     motivo: string
     * }|null
     */
    private static function parseUnaAnulacion(string $linea): ?array
    {
        if ($linea === '') {
            return null;
        }

        if (preg_match(
            '/^\[Anulación cierre\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})\s+user\s+#(\d+)\s+(.+?)\s+PC\s+([^\]]+)\]\s*'
            .'Cierre\s+#(\S+)\s+del\s+(.+?)\.\s*Motivo:\s*(.+?)(?:\.\s*Snapshot:|$)/su',
            $linea,
            $m
        )) {
            return [
                'fecha_anulacion' => trim($m[1]),
                'usuario' => trim($m[3]).' #'.trim($m[2]),
                'pc' => trim($m[4]),
                'cierre_numero' => trim($m[5]),
                'cierre_fecha' => trim($m[6]),
                'motivo' => trim($m[7]) !== '' ? trim($m[7]) : '(sin detalle)',
            ];
        }

        $resumen = self::textoSinSnapshot($linea);
        if ($resumen === '') {
            return null;
        }

        return [
            'fecha_anulacion' => '',
            'usuario' => '',
            'pc' => '',
            'cierre_numero' => '',
            'cierre_fecha' => '',
            'motivo' => mb_substr($resumen, 0, 300),
        ];
    }

    private static function textoSinSnapshot(string $linea): string
    {
        $sinJson = preg_replace('/\s*\.?\s*Snapshot:\s*\{.*$/su', '', $linea);

        return trim((string) $sinJson);
    }
}
