<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;

/**
 * URL del QR fiscal ARCA (misma estructura que FacturacionService::listaUnaFactura).
 */
final class ArcaFacturaQrSupport
{
    public static function urlParaVenta(Venta $venta): string
    {
        $venta->loadMissing([
            'puntoventas.empresas',
            'tipotransacciones',
            'clientes.tipodocumentos',
            'monedas',
        ]);

        $cotizacion = (float) ($venta->cotizacion ?? 1);
        if ((int) $venta->moneda_id === 1) {
            $cotizacion = 1.;
        }

        $codigoComprobante = explode(' ', (string) $venta->codigo);
        $letra = substr($codigoComprobante[1] ?? '', 0, 1);
        $tipoCmp = (int) ($venta->tipotransacciones->codigo ?? 0);
        if ($letra === 'B') {
            $tipoCmp += 5;
        }

        $tipoCodAut = match ($venta->puntoventas->modofacturacion ?? '') {
            'C' => 'E',
            default => 'A',
        };

        $tipoDocRec = self::tipoDocumentoReceptor($venta);
        $nroDocRec = self::numeroDocumentoReceptor($venta);

        $datosCmp = [
            'ver' => 1,
            'fecha' => $venta->fecha,
            'cuit' => (int) str_replace('-', '', (string) ($venta->puntoventas->empresas->nroinscripcion ?? '')),
            'ptoVta' => (int) ($venta->puntoventas->codigo ?? 0),
            'tipoCmp' => $tipoCmp,
            'nroCmp' => (int) $venta->numerocomprobante,
            'importe' => (float) number_format((float) $venta->total, 2, '.', ''),
            'moneda' => (string) ($venta->monedas->abreviatura ?? 'PES'),
            'ctz' => $cotizacion,
            'tipoDocRec' => $tipoDocRec,
            'nroDocRec' => $nroDocRec,
            'tipoCodAut' => $tipoCodAut,
            'codAut' => (int) ($venta->cae ?? 0),
        ];

        return 'https://www.arca.gob.ar/fe/qr/?p='.base64_encode((string) json_encode($datosCmp, JSON_UNESCAPED_UNICODE));
    }

    private static function tipoDocumentoReceptor(Venta $venta): int
    {
        if (GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta)) {
            return (int) config('arca_wsfe.receptor.consumidor_final_tipo_documento', 99);
        }

        return (int) ($venta->clientes->tipodocumentos->codigoexterno ?? 99);
    }

    private static function numeroDocumentoReceptor(Venta $venta): int
    {
        $doc = GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta)
            ? GastronomiaVentaDisplaySupport::documentoReceptorFactura($venta)
            : (string) ($venta->clientes->numerodocumento ?? '0');

        $digits = preg_replace('/\D+/', '', $doc) ?? '';

        return $digits !== '' ? (int) $digits : 0;
    }
}
