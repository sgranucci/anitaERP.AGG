<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\Venta;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Reconstruye el payload ARCA (dataCAE) desde una venta ya emitida bajo CAEA.
 */
final class ArcaCaeaInformeDatosDesdeVentaSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function construir(Venta $venta): array
    {
        $venta->loadMissing([
            'venta_impuestos',
            'venta_emisiones',
            'tipotransacciones',
            'puntoventas',
            'clientes.tipodocumentos',
            'monedas',
        ]);

        $puntoventa = $venta->puntoventas;
        if ($puntoventa === null) {
            throw new InvalidArgumentException('Venta '.$venta->id.': sin punto de venta.');
        }

        if (($puntoventa->modofacturacion ?? '') !== 'A') {
            throw new InvalidArgumentException('Venta '.$venta->id.': el punto de venta no es CAEA.');
        }

        $tipo = $venta->tipotransacciones;
        if ($tipo === null) {
            throw new InvalidArgumentException('Venta '.$venta->id.': sin tipo de transacción.');
        }

        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
        $cbteTipo = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada((string) $tipo->codigo, (string) $venta->codigo);
        if ($cbteTipo <= 0) {
            throw new InvalidArgumentException('Venta '.$venta->id.': no se pudo resolver tipo AFIP.');
        }

        [$tipodoc, $numerodocumento] = self::resolverDocumentoReceptor($venta);
        [$nogravado, $gravado, $exento, $iva, $tributos, $impuestos, $totalTributo] = self::armarImportes($venta);

        $fechaFactura = Carbon::parse((string) $venta->fecha);
        $fechaAsignacion = $fechaFactura->copy()->startOfMonth();

        $monedaCodigo = self::monedaAfipDesdeVenta($venta);
        $cotizacion = self::cotizacionAfipDesdeVenta($venta, $monedaCodigo);

        $items = [];
        foreach ($venta->venta_emisiones as $emision) {
            $items[] = [
                'articulo_id' => (int) $emision->articulo_id,
                'cantidad' => (float) $emision->cantidad,
                'precio' => (float) $emision->precio,
                'detalle' => (string) ($emision->detalle ?? ''),
                'impuesto_id' => (int) ($emision->impuesto_id ?? 0),
            ];
        }

        return [
            'cbte_tipo' => $cbteTipo,
            'letra' => $letra,
            'tipodoc' => $tipodoc,
            'numerodocumento' => $numerodocumento,
            'condicioniva_id' => (int) ($venta->condicioniva_id ?? 0),
            'numerocomprobante' => (int) $venta->numerocomprobante,
            'fechacomprobante' => $fechaFactura->format('Ymd'),
            'cbte_fch_hs_gen' => self::fechaHoraGeneracion($venta),
            'total' => abs((float) $venta->total),
            'nogravado' => $nogravado,
            'gravado' => $gravado,
            'exento' => $exento,
            'iva' => $iva,
            'tributo' => $totalTributo,
            'fechavencimiento' => $fechaFactura->format('Ymd'),
            'moneda' => $monedaCodigo,
            'cotizacion' => $cotizacion,
            'tributos' => $tributos,
            'impuestos' => $impuestos,
            'comprobantesasociados' => [],
            'fechaasignaciondesde' => $fechaAsignacion->format('Ymd'),
            'fechaasignacionhasta' => $fechaFactura->format('Ymd'),
            'concepto' => 1,
            'items' => $items,
        ];
    }

    public static function fechaHoraGeneracion(Venta $venta): string
    {
        $ts = $venta->created_at ?? null;
        if ($ts instanceof Carbon) {
            return $ts->format('YmdHis');
        }

        return Carbon::parse((string) $venta->fecha)->format('Ymd').'120000';
    }

    public static function monedaAfipDesdeVenta(Venta $venta): string
    {
        $moneda = $venta->monedas;
        if ($moneda === null) {
            return 'PES';
        }

        $codigoErp = trim((string) ($moneda->codigo ?? ''));
        if ($codigoErp === '') {
            $codigoErp = trim((string) ($moneda->abreviatura ?? ''));
        }

        return LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
            $codigoErp,
            (string) ($moneda->nombre ?? ''),
        );
    }

    /**
     * WSFE [726]: con MonId=PES, MonCotiz es obligatorio e igual a 1.
     * En gastronomía/CAEA a veces queda grabada una cotización diaria de otra moneda.
     */
    public static function cotizacionAfipDesdeVenta(Venta $venta, ?string $monedaAfip = null): float
    {
        $monedaAfip = $monedaAfip ?? self::monedaAfipDesdeVenta($venta);
        if (strtoupper(trim($monedaAfip)) === 'PES') {
            return 1.0;
        }

        $cotizacion = (float) ($venta->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            return 1.0;
        }

        return $cotizacion;
    }

    /**
     * @return array{0:int,1:string}
     */
    private static function resolverDocumentoReceptor(Venta $venta): array
    {
        $cliente = $venta->clientes;
        $docDigitos = preg_replace('/\D+/', '', (string) ($cliente?->numerodocumento ?? $venta->nroinscripcion ?? '')) ?? '';
        $tipodocExt = (int) ($cliente?->tipodocumentos?->codigoexterno ?? 0);

        if ($docDigitos !== '' && (int) $docDigitos > 0 && $tipodocExt > 0 && $tipodocExt !== 99) {
            return [$tipodocExt, $docDigitos];
        }

        return [
            (int) config('arca_wsfe.receptor.consumidor_final_tipo_documento', 99),
            '0',
        ];
    }

    /**
     * @return array{
     *   0: float,
     *   1: float,
     *   2: float,
     *   3: float,
     *   4: list<array<string, mixed>>,
     *   5: list<array<string, mixed>>,
     *   6: float
     * }
     */
    private static function armarImportes(Venta $venta): array
    {
        $nogravado = 0.0;
        $gravado = 0.0;
        $exento = 0.0;
        $iva = 0.0;
        $tributos = [];
        $impuestos = [];
        $totalTributo = 0.0;

        $impuestoIds = $venta->venta_impuestos
            ->pluck('impuesto_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        $impuestosMap = $impuestoIds !== []
            ? Impuesto::query()->whereIn('id', $impuestoIds)->get()->keyBy('id')
            : collect();

        foreach ($venta->venta_impuestos as $row) {
            $concepto = trim((string) $row->concepto);
            $importe = abs((float) $row->importe);
            $base = abs((float) $row->baseimponible);
            $tasa = (float) $row->tasa;

            if ($concepto === 'No Gravado') {
                $nogravado += $importe;

                continue;
            }

            if ($concepto === 'Exento') {
                $exento += $importe;

                continue;
            }

            if (str_starts_with($concepto, 'Iva ')) {
                $iva += $importe;
                $imp = $impuestosMap->get((int) $row->impuesto_id);
                $impuestos[] = [
                    'id' => (int) ($imp?->codigoarca ?? 5),
                    'base_imp' => $base,
                    'importe' => $importe,
                ];

                continue;
            }

            if (str_starts_with($concepto, 'Gravado')) {
                $gravado += $base > 0 ? $base : $importe;

                continue;
            }

            if ($concepto === 'Percepcion IVA') {
                $tributos[] = [
                    'id' => 1,
                    'base_imp' => $base,
                    'alicuota' => $tasa,
                    'desc' => $concepto,
                    'importe' => $importe,
                ];
                $totalTributo += $importe;

                continue;
            }

            if ($concepto === 'Impuesto Interno' && $importe != 0.0) {
                $tributos[] = [
                    'id' => 4,
                    'base_imp' => $base,
                    'alicuota' => $tasa,
                    'desc' => $concepto,
                    'importe' => $importe,
                ];
                $totalTributo += $importe;

                continue;
            }

            if ((int) ($row->provincia_id ?? 0) > 0 && $importe != 0.0) {
                $tributos[] = [
                    'id' => 2,
                    'base_imp' => $base,
                    'alicuota' => $tasa,
                    'desc' => $concepto,
                    'importe' => $importe,
                ];
                $totalTributo += $importe;
            }
        }

        if ($gravado <= 0.0) {
            $gravado = GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales(
                self::conceptosDesdeImpuestos($venta),
                abs((float) $venta->total),
            );
        }

        if ($impuestos === [] && $iva > 0.0) {
            $impuestos[] = [
                'id' => 5,
                'base_imp' => $gravado,
                'importe' => $iva,
            ];
        }

        return [$nogravado, $gravado, $exento, $iva, $tributos, $impuestos, $totalTributo];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function conceptosDesdeImpuestos(Venta $venta): array
    {
        $out = [];
        foreach ($venta->venta_impuestos as $row) {
            $out[] = [
                'concepto' => (string) $row->concepto,
                'importe' => abs((float) $row->importe),
                'baseimponible' => abs((float) $row->baseimponible),
                'tasa' => (float) $row->tasa,
                'codigoarca' => 5,
                'codigo' => '',
                'jurisdiccion' => (int) ($row->provincia_id ?? 0) > 0 ? 1 : 0,
            ];
        }

        return $out;
    }
}
