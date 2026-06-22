<?php

namespace App\Services\Configuracion\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LibroIvaDigitalVentasGenerador
{
    /**
     * @return array{
     *     ventas_cbte: string,
     *     ventas_alicuotas: string,
     *     resumen: array<string, int|float>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $query = $this->queryVentas($empresaId, $desde, $hasta);

        $lineasCbte = [];
        $lineasAlicuotas = [];
        $totalImporte = 0.0;
        $totalIva = 0.0;
        $conteo = 0;
        $conteoConAlicuotas = 0;

        $query->orderBy('fecha')
            ->orderBy('puntoventa_id')
            ->orderBy('tipotransaccion_id')
            ->orderBy('numerocomprobante')
            ->chunkById(200, function ($ventas) use (&$lineasCbte, &$lineasAlicuotas, &$totalImporte, &$totalIva, &$conteo, &$conteoConAlicuotas): void {
                /** @var Collection<int, Venta> $ventas */
                $ventas->load([
                    'venta_impuestos',
                    'tipotransacciones',
                    'puntoventas',
                    'clientes.tipodocumentos',
                    'clientes.condicionivas',
                    'monedas',
                ]);

                foreach ($ventas as $venta) {
                    $registro = $this->armarRegistroVenta($venta);
                    if ($registro === null) {
                        continue;
                    }

                    $lineasCbte[] = LibroIvaDigitalFormatoSupport::registroVentasCbte($registro['cabecera']);
                    if ($registro['alicuotas'] !== []) {
                        $conteoConAlicuotas++;
                    }
                    foreach ($registro['alicuotas'] as $alicuota) {
                        $lineasAlicuotas[] = LibroIvaDigitalFormatoSupport::registroVentasAlicuota($alicuota);
                        $totalIva += abs((float) ($alicuota['impuesto_liquidado'] ?? 0));
                    }

                    $totalImporte += abs((float) $venta->total);
                    $conteo++;
                }
            }, 'venta.id', 'id');

        return [
            'ventas_cbte' => implode("\r\n", $lineasCbte),
            'ventas_alicuotas' => implode("\r\n", $lineasAlicuotas),
            'resumen' => [
                'comprobantes' => $conteo,
                'comprobantes_con_alicuotas' => $conteoConAlicuotas,
                'alicuotas' => count($lineasAlicuotas),
                'importe_total' => round($totalImporte, 2),
                'total_iva' => round($totalIva, 2),
            ],
        ];
    }

    private function queryVentas(int $empresaId, string $desde, string $hasta): Builder
    {
        return Venta::query()
            ->whereNull('deleted_at')
            ->whereHas('puntoventas', fn (Builder $q) => $q->where('empresa_id', $empresaId))
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('cae')
            ->where('cae', '<>', '');
    }

    /**
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    private function armarRegistroVenta(Venta $venta): ?array
    {
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
        $codigoBase = (string) ($venta->tipotransacciones->codigo ?? '001');
        $tipoComprobante = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoBase, $letra);
        $puntoVenta = (int) ($venta->puntoventas->codigo ?? 0);
        $numero = (int) $venta->numerocomprobante;

        $totales = $this->desglosarImpuestos($venta, $letra);
        $comprador = $this->resolverComprador($venta);

        $codigoOperacion = ' ';
        if (! in_array($letra, ['B', 'C'], true)) {
            if ($totales['cantidad_alicuotas'] === 0 && $totales['operaciones_exentas'] > 0) {
                $codigoOperacion = 'E';
            } elseif ($totales['cantidad_alicuotas'] === 0 && $totales['no_gravado'] > 0) {
                $codigoOperacion = 'N';
            }
        }

        $signoRaw = (int) ($venta->tipotransacciones?->getRawOriginal('signo') ?? 1);
        $signo = $signoRaw < 0 ? -1 : 1;

        $cabecera = [
            'fecha' => date('Ymd', strtotime((string) $venta->fecha)),
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'numero_hasta' => $numero,
            'codigo_documento' => $comprador['codigo_documento'],
            'numero_identificacion' => $comprador['numero_identificacion'],
            'nombre_comprador' => $comprador['nombre'],
            'importe_total' => $signo * $totales['importe_total'],
            'no_integra_neto' => $signo * $totales['no_integra_neto'],
            'percepcion_no_categorizados' => 0,
            'operaciones_exentas' => $signo * $totales['operaciones_exentas'],
            'percepciones_nacionales' => $signo * $totales['percepciones_nacionales'],
            'percepciones_iibb' => $signo * $totales['percepciones_iibb'],
            'percepciones_municipales' => 0,
            'impuestos_internos' => $signo * $totales['impuestos_internos'],
            'codigo_moneda' => LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
                $venta->monedas->codigo ?? null,
                $venta->monedas->nombre ?? null,
            ),
            'tipo_cambio' => (float) ($venta->cotizacion ?: 1),
            'cantidad_alicuotas' => $totales['cantidad_alicuotas'],
            'codigo_operacion' => $codigoOperacion,
            'otros_tributos' => 0,
            'fecha_vencimiento' => '00000000',
        ];

        $alicuotas = [];
        foreach ($totales['alicuotas'] as $row) {
            $alicuotas[] = [
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $puntoVenta,
                'numero_comprobante' => $numero,
                'neto_gravado' => $signo * $row['neto'],
                'alicuota_iva' => $row['codigo_lid'],
                'impuesto_liquidado' => $signo * $row['iva'],
            ];
        }

        return ['cabecera' => $cabecera, 'alicuotas' => $alicuotas];
    }

    /**
     * @return array{
     *     importe_total: float,
     *     no_integra_neto: float,
     *     no_gravado: float,
     *     operaciones_exentas: float,
     *     percepciones_nacionales: float,
     *     percepciones_iibb: float,
     *     impuestos_internos: float,
     *     cantidad_alicuotas: int,
     *     alicuotas: list<array{neto: float, iva: float, codigo_lid: string, tasa: float}>
     * }
     */
    private function desglosarImpuestos(Venta $venta, string $letra): array
    {
        $importeTotal = 0.0;
        $noIntegra = 0.0;
        $noGravado = 0.0;
        $exento = 0.0;
        $percNac = 0.0;
        $percIibb = 0.0;
        $impInterno = 0.0;
        $alicuotas = [];

        foreach ($venta->venta_impuestos as $imp) {
            $concepto = (string) $imp->concepto;
            $importe = abs((float) $imp->importe);

            if (stripos($concepto, 'Total') === 0) {
                $importeTotal = $importe;
                continue;
            }
            if (stripos($concepto, 'Subtotal') === 0) {
                continue;
            }
            if (stripos($concepto, 'No Gravado') !== false) {
                $noGravado += $importe;
                continue;
            }
            if (stripos($concepto, 'Exento') !== false) {
                $exento += $importe;
                continue;
            }
            if (stripos($concepto, 'Impuesto Interno') !== false) {
                $impInterno += $importe;
                continue;
            }
            if (stripos($concepto, 'Percepcion IVA') !== false || stripos($concepto, 'Perc. IVA') !== false) {
                $percNac += $importe;
                continue;
            }
            if (stripos($concepto, 'Perc.') !== false || stripos($concepto, 'Percepcion IIBB') !== false) {
                $percIibb += $importe;
                continue;
            }
            if (stripos($concepto, 'Gravado al') !== false) {
                $tasa = round((float) $imp->tasa, 3);
                $alicuotas[$tasa]['neto'] = ($alicuotas[$tasa]['neto'] ?? 0) + $importe;
                $alicuotas[$tasa]['tasa'] = $tasa;
                continue;
            }
            if (stripos($concepto, 'Iva ') !== false || stripos($concepto, 'IVA') === 0) {
                $tasa = round((float) $imp->tasa, 3);
                $alicuotas[$tasa]['iva'] = ($alicuotas[$tasa]['iva'] ?? 0) + $importe;
                $alicuotas[$tasa]['tasa'] = $tasa;
            }
        }

        if ($importeTotal <= 0) {
            $importeTotal = abs((float) $venta->total);
        }

        $filasAlicuota = [];
        foreach ($alicuotas as $tasa => $row) {
            if (($row['neto'] ?? 0) <= 0 && ($row['iva'] ?? 0) <= 0) {
                continue;
            }
            $filasAlicuota[] = [
                'neto' => (float) ($row['neto'] ?? 0),
                'iva' => (float) ($row['iva'] ?? 0),
                'tasa' => (float) $tasa,
                'codigo_lid' => LibroIvaDigitalMapeosSupport::codigoAlicuotaLid((float) $tasa),
            ];
        }

        $cantidadAlicuotas = in_array($letra, ['B', 'C'], true) ? 0 : count($filasAlicuota);

        return [
            'importe_total' => $importeTotal,
            'no_integra_neto' => $noIntegra,
            'no_gravado' => $noGravado,
            'operaciones_exentas' => $exento + $noGravado,
            'percepciones_nacionales' => $percNac,
            'percepciones_iibb' => $percIibb,
            'impuestos_internos' => $impInterno,
            'cantidad_alicuotas' => $cantidadAlicuotas,
            'alicuotas' => $cantidadAlicuotas > 0 ? $filasAlicuota : [],
        ];
    }

    /**
     * @return array{codigo_documento: string, numero_identificacion: string, nombre: string}
     */
    private function resolverComprador(Venta $venta): array
    {
        $condicion = $venta->clientes?->condicionivas;
        if ($condicion === null && $venta->condicioniva_id) {
            $condicion = \App\Models\Configuracion\Condicioniva::find($venta->condicioniva_id);
        }
        $esConsumidorFinal = in_array((int) ($condicion->id ?? 0), [3], true)
            || stripos((string) ($condicion->nombre ?? ''), 'consumidor final') !== false;

        $cuit = preg_replace('/\D+/', '', (string) ($venta->nroinscripcion ?: $venta->clientes?->numerodocumento ?: '')) ?? '';

        if ($esConsumidorFinal && strlen($cuit) < 11) {
            return [
                'codigo_documento' => '99',
                'numero_identificacion' => '0',
                'nombre' => stripos((string) $venta->nombre, 'GLOBAL') !== false
                    ? 'VENTA GLOBAL DIARIA'
                    : '-CONSUMIDOR FINAL-',
            ];
        }

        $codigoDoc = str_pad(
            (string) ($venta->clientes?->tipodocumentos?->codigoexterno ?: (strlen($cuit) === 11 ? '80' : '96')),
            2,
            '0',
            STR_PAD_LEFT,
        );

        return [
            'codigo_documento' => $codigoDoc,
            'numero_identificacion' => $cuit !== '' ? $cuit : '0',
            'nombre' => (string) ($venta->nombre ?: $venta->clientes?->nombre ?: ''),
        ];
    }
}
