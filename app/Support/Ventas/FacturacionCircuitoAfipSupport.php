<?php

namespace App\Support\Ventas;

/**
 * Circuito AFIP exportación vs local para facturación admin, pedidos y remitos.
 *
 * POS gastronomía, estacionamiento y facturación local sí pueden pasar por
 * {@see \App\Services\Ventas\FacturacionService::generaComprobanteGeneral}, pero
 * {@see \App\Services\Ventas\FacturacionService} omite esta matriz vía esEmisionPos.
 *
 * Matriz:
 * - Exportación: cliente letra E y/o doc PEX/FAE/FAF → PV modo E (WSFEX)
 *   + tipo factura FAE (Interforming) / FAF (Ferli)
 *   + NC/ND NCE/NDE (Interforming) o NCD en PV export (Ferli; NCD también es local)
 * - Local: resto → no usar PV exportación ni tipos exclusivos de export (FAE/FAF/NCE/NDE)
 */
final class FacturacionCircuitoAfipSupport
{
    public const CIRCUITO_EXPORTACION = 'exportacion';

    public const CIRCUITO_LOCAL = 'local';

    /**
     * Facturas solo de exportación (no aparecen en circuito local).
     *
     * @var list<string>
     */
    private const ABREV_EXPORT_FACTURA = ['FAE', 'FAF'];

    /**
     * NC/ND exclusivas de exportación (no FCE MiPyME 203+).
     *
     * @var list<string>
     */
    private const ABREV_EXPORT_NC_ND = ['NCE', 'NDE'];

    /**
     * NC/ND que Ferli usa en exportación y también en local (no filtrar del circuito local).
     *
     * @var list<string>
     */
    private const ABREV_NC_COMPATIBLE_EXPORT = ['NCD'];

    /** @var list<int> */
    private const CODIGOS_AFIP_EXPORT = [19, 20, 21];

    /**
     * @param  object|null  $puntoventa  Con modofacturacion / webservice
     * @param  object|null  $tipotransaccion  Con abreviatura / codigo
     */
    public static function resolverCircuito(
        ?object $puntoventa,
        ?object $tipotransaccion,
        ?string $letraCliente = null,
        ?string $codigoDocumento = null,
    ): string {
        if (self::documentoEsExportacion($codigoDocumento)) {
            return self::CIRCUITO_EXPORTACION;
        }

        if (strtoupper(trim((string) $letraCliente)) === 'E') {
            return self::CIRCUITO_EXPORTACION;
        }

        if (self::esTipoExportacion($tipotransaccion)) {
            return self::CIRCUITO_EXPORTACION;
        }

        if (self::esPuntoventaExportacion($puntoventa)) {
            return self::CIRCUITO_EXPORTACION;
        }

        return self::CIRCUITO_LOCAL;
    }

    public static function documentoEsExportacion(?string $codigoDocumento): bool
    {
        $codigo = strtoupper(trim((string) $codigoDocumento));
        if ($codigo === '') {
            return false;
        }

        return str_starts_with($codigo, 'PEX')
            || str_contains($codigo, 'PEX')
            || str_starts_with($codigo, 'REX')
            || str_contains($codigo, '-REX')
            || str_starts_with($codigo, 'FAE')
            || str_starts_with($codigo, 'FAF');
    }

    /**
     * Tipo exclusivo de exportación (sale del select en circuito local).
     */
    public static function esTipoExportacion(?object $tipo): bool
    {
        if (! $tipo) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        $codigo = (int) preg_replace('/\D+/', '', (string) ($tipo->codigo ?? ''));

        if (in_array($abrev, self::ABREV_EXPORT_FACTURA, true)) {
            return true;
        }

        if (in_array($codigo, self::CODIGOS_AFIP_EXPORT, true)) {
            return true;
        }

        // NCE/NDE Anita exportación (021/020), no FCE MiPyME (203+)
        if (in_array($abrev, self::ABREV_EXPORT_NC_ND, true) && $codigo > 0 && $codigo < 200) {
            return true;
        }

        return false;
    }

    /**
     * Tipo admitido al emitir en circuito exportación (incluye NCD Ferli).
     */
    public static function esTipoPermitidoEnExportacion(?object $tipo): bool
    {
        if (self::esTipoExportacion($tipo)) {
            return true;
        }

        if (! $tipo) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));

        return in_array($abrev, self::ABREV_NC_COMPATIBLE_EXPORT, true);
    }

    public static function esPuntoventaExportacion(?object $puntoventa): bool
    {
        if (! $puntoventa) {
            return false;
        }

        $modo = strtoupper(trim((string) ($puntoventa->modofacturacion ?? '')));
        if ($modo === 'E') {
            return true;
        }

        $ws = strtolower(trim((string) ($puntoventa->webservice ?? '')));

        return in_array($ws, ArcaPuntoventaWebserviceSupport::ALIASES_WSFEX, true);
    }

    /**
     * Mensaje de error o null si la combinación es válida.
     */
    public static function mensajeErrorSiInvalido(
        ?object $puntoventa,
        ?object $tipotransaccion,
        ?string $letraCliente = null,
        ?string $codigoDocumento = null,
        int $incotermId = 0,
    ): ?string {
        if (! $puntoventa || ! $tipotransaccion) {
            return null;
        }

        $circuito = self::resolverCircuito($puntoventa, $tipotransaccion, $letraCliente, $codigoDocumento);
        $letra = strtoupper(trim((string) $letraCliente));
        $abrev = strtoupper(trim((string) ($tipotransaccion->abreviatura ?? '')));
        $pvLabel = trim((string) ($puntoventa->codigo ?? '')).' '.trim((string) ($puntoventa->nombre ?? ''));
        $tipoLabel = $abrev !== '' ? $abrev : (string) ($tipotransaccion->nombre ?? 'tipo');

        if ($circuito === self::CIRCUITO_EXPORTACION) {
            if ($letra !== '' && $letra !== 'E') {
                return 'Circuito exportación: el cliente debe tener condición IVA letra E (exento exportación).';
            }

            if (! self::esPuntoventaExportacion($puntoventa)) {
                return 'Circuito exportación: use un punto de venta modo E / WSFEX'
                    .($pvLabel !== ' ' ? " (no «{$pvLabel}»)." : '.');
            }

            if (! self::esTipoPermitidoEnExportacion($tipotransaccion)) {
                return 'Circuito exportación: use tipo FAE/FAF o NCE/NDE/NCD,'
                    ." no «{$tipoLabel}».";
            }

            if (in_array($abrev, self::ABREV_EXPORT_FACTURA, true) && $incotermId <= 0) {
                return 'Factura de exportación: indique incoterm (FOB, CIF, EXW, etc.).';
            }

            return null;
        }

        // Circuito local
        if (self::esPuntoventaExportacion($puntoventa)) {
            return 'Circuito local: no use punto de venta de exportación (modo E / WSFEX)'
                .($pvLabel !== ' ' ? " «{$pvLabel}»." : '.');
        }

        if (self::esTipoExportacion($tipotransaccion)) {
            return "Circuito local: no use tipo de exportación «{$tipoLabel}» (reserve FAE/FAF/NCE/NDE para letra E).";
        }

        return null;
    }

    /**
     * @param  iterable<int, object>  $puntoventas
     * @return list<object>
     */
    public static function filtrarPuntoventasPorCircuito(iterable $puntoventas, string $circuito): array
    {
        $out = [];
        foreach ($puntoventas as $pv) {
            $esExp = self::esPuntoventaExportacion($pv);
            if ($circuito === self::CIRCUITO_EXPORTACION ? $esExp : ! $esExp) {
                $out[] = $pv;
            }
        }

        return $out;
    }

    /**
     * @param  iterable<int, object>  $tipos
     * @return list<object>
     */
    public static function filtrarTiposPorCircuito(iterable $tipos, string $circuito): array
    {
        $out = [];
        foreach ($tipos as $tipo) {
            if ($circuito === self::CIRCUITO_EXPORTACION) {
                if (self::esTipoPermitidoEnExportacion($tipo)) {
                    $out[] = $tipo;
                }
            } elseif (! self::esTipoExportacion($tipo)) {
                // Local: excluye FAE/FAF/NCE/NDE; deja NCD (dual Ferli)
                $out[] = $tipo;
            }
        }

        return $out;
    }

    public static function letraClienteDesdeModelo(?object $cliente): string
    {
        if (! $cliente) {
            return '';
        }

        $cond = $cliente->condicionivas ?? $cliente->condicioniva ?? null;
        if (is_object($cond) && isset($cond->letra)) {
            return strtoupper(trim((string) $cond->letra));
        }

        $condicionivaId = (int) ($cliente->condicioniva_id ?? 0);
        if ($condicionivaId <= 0) {
            return '';
        }

        $letra = \App\Models\Configuracion\Condicioniva::query()
            ->whereKey($condicionivaId)
            ->value('letra');

        return strtoupper(trim((string) ($letra ?? '')));
    }
}
