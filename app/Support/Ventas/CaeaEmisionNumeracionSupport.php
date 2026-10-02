<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeradorFiscalSupport;
use InvalidArgumentException;

/**
 * Numeración CAEA (PV mod A), siempre desde el ERP.
 *
 * Cada serie es venta.codigo_afip + punto de venta. codigo_afip es el tipo AFIP
 * (1/2/3 factura-ND-NC A, 6/7/8 factura-ND-NC B, 11/12/13 letra C). No hay un
 * numerador único del PV y no se lee el último de Anita.
 * El Bierzo reserva en venta_serie_numerador con esa misma clave.
 */
final class CaeaEmisionNumeracionSupport
{
    public static function tipoAnitaDesdeTipotransaccion(
        Tipotransaccion $tipotransaccion,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
        string $letra = 'A',
    ): string {
        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            $tipotransaccion->codigo ?? 0,
            $letra,
            $modoFacturacionCliente,
            $totalComprobante,
        );
        if ($codigoAfip >= 200) {
            return substr((string) ($tipotransaccion->abreviatura ?? 'F'), 0, 1).'CE';
        }

        $codigo = (string) ($tipotransaccion->codigo ?? '');
        if ($codigo >= '200') {
            return substr((string) ($tipotransaccion->abreviatura ?? ''), 0, 1).'CE';
        }

        return (string) ($tipotransaccion->abreviatura ?? 'FAC');
    }

    public static function reservarSiguienteNumeroErp(
        int $puntoventaId,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): int {
        if ($puntoventaId <= 0 || (int) ($tipotransaccion->id ?? 0) <= 0) {
            return 0;
        }

        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            (int) ($tipotransaccion->codigo ?? 0),
            $letraComprobante,
            $modoFacturacionCliente,
            $totalComprobante,
        );

        $ultimoErp = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            $puntoventaId,
            (int) ($tipotransaccion->codigo ?? 0),
            $letraComprobante,
            $empresaId,
            $modoFacturacionCliente,
            $totalComprobante,
        );

        // La serie es por codigo_afip (FAC B = 6, NCB = 8). No subir al máximo de
        // todo el PV: una NC tomaría el siguiente de la factura y ARCA frena las
        // dos correlatividades (Rebisco PV 00030, sep/2026: NCB 54768/54769 con
        // último autorizado 25 y hueco en FAC 54768).

        if (EntornoEmpresaSupport::esElBierzo()) {
            // Reserva atómica en venta_serie_numerador. El piso es el máximo
            // ya grabado en el ERP (y el piso de config del PV), nunca Anita.
            // Al arrancar cada punto de venta se carga ultimo_numero a mano.
            $piso = self::aplicarPisoCaea($puntoventaId, $ultimoErp, $codigoAfip);

            return VentaNumeradorFiscalSupport::reservarSiguiente(
                $puntoventaId,
                $codigoAfip,
                $empresaId,
                $piso,
            );
        }

        return self::aplicarPisoCaea($puntoventaId, $ultimoErp, $codigoAfip) + 1;
    }

    /**
     * Piso operativo solo de facturas A/B/C (AFIP 1, 6 y 11).
     * ND, NC, exportación y FCE siguen el máximo de su propio codigo_afip.
     */
    public static function aplicarPisoCaea(int $puntoventaId, int $ultimoErp, ?int $codigoAfip = null): int
    {
        if ($codigoAfip !== null && ! in_array($codigoAfip, [1, 6, 11], true)) {
            return $ultimoErp;
        }

        return max($ultimoErp, self::pisoCaeaPorPuntoventaId($puntoventaId));
    }

    public static function pisoCaeaPorPuntoventaId(int $puntoventaId): int
    {
        $pisos = config('facturacion.CAEA_PISO_NUMERO_POR_CODIGO');
        if ($puntoventaId <= 0 || ! is_array($pisos) || $pisos === []) {
            return 0;
        }

        $codigo = trim((string) (Puntoventa::query()->whereKey($puntoventaId)->value('codigo') ?? ''));
        if ($codigo === '') {
            return 0;
        }

        $padded = str_pad(ctype_digit($codigo) ? $codigo : (string) preg_replace('/\D+/', '', $codigo), 5, '0', STR_PAD_LEFT);

        return (int) ($pisos[$padded] ?? $pisos[$codigo] ?? 0);
    }

    /**
     * Reserva el siguiente número ERP y lo aplica al payload de emisión.
     *
     * @param  array<string, mixed>  $payload
     * @return null si ok; mensaje de error si falla (solo PV mod A)
     */
    public static function aplicarReservaNumeracionAlPayload(
        array &$payload,
        Puntoventa $puntoventa,
        Tipotransaccion $tipotransaccion,
        string $letraComprobante = 'B',
        bool $lockYaAdquirido = false,
    ): ?string {
        if (($puntoventa->modofacturacion ?? '') !== 'A') {
            return null;
        }

        if (! empty($payload['numerocomprobante_forzado'])) {
            $payload['_omitir_numera_anita_fin'] = ! EntornoEmpresaSupport::esElBierzo();

            return null;
        }

        $lock = null;
        if (! $lockYaAdquirido) {
            try {
                $lock = PuntoventaEmisionLock::adquirir((int) $puntoventa->id);
            } catch (InvalidArgumentException $e) {
                return $e->getMessage();
            }
        }

        try {
            $empresaId = (int) ($puntoventa->empresa_id ?? 0);
            $modoCliente = isset($payload['modofacturacion_cliente'])
                ? (string) $payload['modofacturacion_cliente']
                : null;
            $totalComprobante = isset($payload['total_comprobante'])
                ? (float) $payload['total_comprobante']
                : null;

            $numero = self::reservarSiguienteNumeroErp(
                (int) $puntoventa->id,
                $tipotransaccion,
                $letraComprobante,
                $empresaId > 0 ? $empresaId : null,
                $modoCliente,
                $totalComprobante,
            );

            if ($numero <= 0) {
                return 'No pudo reservar número de comprobante CAEA en ERP (PV '.($puntoventa->codigo ?? '').').';
            }

            $payload['numerocomprobante_forzado'] = $numero;
            // AGG CAEA: el ERP ya numeró; no tocar compemis.
            // El Bierzo: Anita sigue vivo; numeraAnita al cierre mantiene el numerador.
            $payload['_omitir_numera_anita_fin'] = ! EntornoEmpresaSupport::esElBierzo();
        } finally {
            if (! $lockYaAdquirido) {
                PuntoventaEmisionLock::liberar($lock);
            }
        }

        return null;
    }

    /**
     * Asigna numerocomprobante_forzado en payload (sin lock; caller debe serializar emisión).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function marcarNumerocomprobanteForzadoEnPayload(array &$payload, int $numero): void
    {
        if ($numero <= 0) {
            return;
        }

        $payload['numerocomprobante_forzado'] = $numero;
        $payload['_omitir_numera_anita_fin'] = true;
    }
}
