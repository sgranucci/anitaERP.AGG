<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use InvalidArgumentException;

/**
 * Numeración CAEA (PV mod A).
 *
 * AGG / gastronomía: reserva en ERP (max venta.numerocomprobante).
 * El Bierzo: max(ERP, Anita bridge) mientras Informix sigue vivo; al grabar se avanza compemis.
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

        if (EntornoEmpresaSupport::esElBierzo()) {
            $puntoventa = Puntoventa::query()->find($puntoventaId);
            $sucursal = trim((string) ($puntoventa->codigo ?? ''));
            $tipoAnita = self::tipoAnitaDesdeTipotransaccion(
                $tipotransaccion,
                $modoFacturacionCliente,
                $totalComprobante,
                $letraComprobante,
            );
            $path = PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId)
                ? PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA
                : null;
            $ultimoAnita = app(VentaRepositoryInterface::class)->maxNumeroComprobanteAnitaBridge(
                $tipoAnita,
                $letraComprobante,
                $sucursal,
                $path,
            );
            $ultimoErp = max($ultimoErp, $ultimoAnita);
        }

        return self::aplicarPisoCaea($puntoventaId, $ultimoErp, $codigoAfip) + 1;
    }

    /**
     * Piso operativo FAC/CAEA del PV (hueco Anita): el próximo es max(ERP, piso)+1.
     * FCE/NCE/DCE (codigo_afip >= 200) no usan ese piso: solo la última de su serie.
     */
    public static function aplicarPisoCaea(int $puntoventaId, int $ultimoErp, ?int $codigoAfip = null): int
    {
        if ($codigoAfip !== null && $codigoAfip >= 200) {
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
