@php
    $itemsRin = array_values(is_array($itemsFactura ?? null) ? $itemsFactura : []);
    $paginasRin = \App\Support\Ventas\FacturaPdfPaginacionSupport::paginas($itemsRin, 'admin');
    $decCantRin = (int) config('facturacion.DECIMAL_CANTIDAD');
    $totalParesRin = 0.0;
    foreach ($itemsRin as $itRin) {
        $totalParesRin += (float) ($itRin['cantidad'] ?? 0);
    }
    $nroRin = \App\Support\Ventas\FerliRinNumeracionSupport::numeroImpreso((int) ($venta->numerocomprobante ?? 0));
    $empresaRin = $venta->puntoventas->empresas ?? null;
    $empresaRinId = (int) ($empresaRin->id ?? $venta->puntoventas->empresa_id ?? 0);
    $membreteRin = \App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfSupport::membreteParaVenta(
        $venta,
        $empresaRinId > 0 ? $empresaRinId : null
    );
    $lugarRin = trim((string) ($membreteRin[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_LUGAR] ?? ''));
    $fechaRin = $venta->fecha ? date('d-m-y', strtotime((string) $venta->fecha)) : '';
    $lugarFechaRin = trim($lugarRin.($fechaRin !== '' ? ' '.$fechaRin : ''));
    $domPvRin = trim((string) ($venta->puntoventas->domicilio ?? ''));
    if ($domPvRin === '-' || $domPvRin === '.') {
        $domPvRin = '';
    }
    $locPvRin = trim((string) ($venta->puntoventas->localidades->nombre ?? ''));
    $cpPvRin = trim((string) ($venta->puntoventas->codigopostal ?? ''));
    $provPvRin = trim((string) ($venta->puntoventas->provincias->nombre ?? ''));
    $paisPvRin = trim((string) ($venta->puntoventas->paises->nombre ?? ''));
    if (preg_match('/^\d+-(.+)$/', $paisPvRin, $paisRinMatch)) {
        $paisPvRin = trim($paisRinMatch[1]);
    }
    $codCliRin = trim((string) ($venta->clientes?->codigo ?? ''));
    $nombreCliRin = trim((string) ($venta->clientes?->nombre ?? $venta->nombre ?? ''));
    $ivaRin = trim((string) (
        $venta->clientes?->condicionivas?->nombre
        ?? $venta->condicionivas?->nombre
        ?? ''
    ));
    $cuitRin = \App\Support\Ventas\GastronomiaVentaDisplaySupport::documentoReceptorFactura($venta);
    if ($cuitRin === '') {
        $cuitRin = trim((string) ($venta->nroinscripcion ?? ''));
    }
    $venta->loadMissing('cliente_cuentacorrientes');
    $vencRin = $venta->cliente_cuentacorrientes->sortBy('fechavencimiento')->first();
    $fechaVencRin = $vencRin && $vencRin->fechavencimiento
        ? $vencRin->fechavencimiento->format('d-m-y')
        : $fechaRin;
    $condicionRin = trim((string) (
        $venta->condicionventas->nombre
        ?? $venta->clientes?->condicionventas?->nombre
        ?? 'Contado'
    ));
    $copiaRin = mb_strtoupper(trim((string) ($copiaLeyenda ?? 'ORIGINAL')));
@endphp
<style type="text/css">
    .rin-top, .rin-partes, .rin-cajas, .rin-items { width: 100%; border-collapse: collapse; }
    .rin-top td { border: 0; vertical-align: top; padding: 0; }
    .rin-copia { width: 42%; font-size: 13px; font-weight: bold; padding-top: 18px; }
    .rin-nro { width: 58%; text-align: right; font-size: 13px; line-height: 1.35; }
    .rin-partes { margin-top: 8px; }
    .rin-partes td { border: 0; vertical-align: top; font-size: 12px; line-height: 1.35; padding: 0; }
    .rin-emisor { width: 52%; }
    .rin-empresa { font-size: 15px; font-weight: bold; }
    .rin-cliente { width: 48%; padding-left: 12px; }
    .rin-cajas { margin-top: 10px; }
    .rin-cajas td { width: 50%; border: 1px solid #222; text-align: center; padding: 4px 6px 8px 6px; font-size: 13px; }
    .rin-caja-titulo { font-size: 11px; font-weight: bold; letter-spacing: 0.3px; margin-bottom: 4px; }
    .rin-items { margin-top: 8px; font-size: 11px; }
    .rin-items th, .rin-items td { border: 1px solid #222; padding: 3px 4px; vertical-align: middle; }
    .rin-items thead th { background: #4a4a4a; color: #fff; font-weight: bold; text-align: center; white-space: nowrap; font-size: 10px; }
    .rin-items td.num, .rin-items th.num { text-align: right; }
    .rin-items td.cen { text-align: center; }
    .rin-cierre td { border-left: 0; border-right: 0; border-bottom: 0; font-weight: bold; font-size: 12px; padding-top: 6px; }
    .rin-cierre td.num { border-top: 1px solid #222; }
</style>
@foreach ($paginasRin as $pagIdxRin => $itemsPaginaRin)
    @php
        $esUltimaRin = $pagIdxRin === array_key_last($paginasRin);
        $saltoRin = ($facturaPdfSaltoAntes ?? false) || $pagIdxRin > 0;
    @endphp
    <div class="page {{ $saltoRin ? 'salto-pagina' : '' }}">
        <table class="rin-top">
            <tr>
                <td class="rin-copia">{{ $copiaRin }}</td>
                <td class="rin-nro">
                    Remito Interno Nro: {{ $nroRin }}<br>
                    LUGAR Y FECHA: {{ $lugarFechaRin }}
                </td>
            </tr>
        </table>
        <table class="rin-partes">
            <tr>
                <td class="rin-emisor">
                    <div class="rin-empresa">{{ $empresaRin->nombre ?? '' }}</div>
                    @if ($domPvRin !== '')
                        {{ $domPvRin }}<br>
                    @endif
                    @if ($locPvRin !== '' || $cpPvRin !== '')
                        {{ $locPvRin }}
                        @if ($cpPvRin !== '')
                            ({{ $cpPvRin }})
                        @endif
                        <br>
                    @endif
                    @if ($provPvRin !== '')
                        {{ $provPvRin }}<br>
                    @endif
                    @if ($paisPvRin !== '')
                        {{ $paisPvRin }}
                    @endif
                </td>
                <td class="rin-cliente">
                    CLIENTE: {{ $codCliRin !== '' ? $codCliRin : $nombreCliRin }}
                    @if ($codCliRin !== '' && $nombreCliRin !== '')
                        <br>{{ $nombreCliRin }}
                    @endif
                    @if ($ivaRin !== '')
                        <br>I.V.A.: {{ $ivaRin }}
                    @endif
                    @if ($cuitRin !== '')
                        <br>C.U.I.T.: {{ $cuitRin }}
                    @endif
                </td>
            </tr>
        </table>
        <table class="rin-cajas">
            <tr>
                <td>
                    <div class="rin-caja-titulo">FECHA DE VENCIMIENTO</div>
                    {{ $fechaVencRin }}
                </td>
                <td>
                    <div class="rin-caja-titulo">CONDICION DE VENTA</div>
                    {{ $condicionRin }}
                </td>
            </tr>
        </table>
        <table class="rin-items">
            <thead>
                <tr>
                    <th style="width:16%;">ARTICULO</th>
                    <th style="width:30%;">DESCRIPCION</th>
                    <th class="cen" style="width:18%;">CANTIDAD PARES</th>
                    <th class="num" style="width:18%;">PRECIO UNITARIO</th>
                    <th class="num" style="width:18%;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($itemsPaginaRin as $itemRin)
                    @php
                        $detalleRin = trim((string) ($itemRin['detalle'] ?? ''));
                        $colorRin = trim((string) ($itemRin['color'] ?? ''));
                        if ($colorRin !== '' && $detalleRin !== '' && ! str_contains(mb_strtoupper($detalleRin), mb_strtoupper($colorRin))) {
                            $detalleRin .= ' '.$colorRin;
                        } elseif ($detalleRin === '' && $colorRin !== '') {
                            $detalleRin = $colorRin;
                        }
                        $cantRin = (float) ($itemRin['cantidad'] ?? 0);
                        $precioRin = (float) ($itemRin['precio'] ?? 0);
                        $importeRin = round($precioRin * $cantRin, 2);
                    @endphp
                    <tr>
                        <td>{{ $itemRin['sku'] ?? '' }}</td>
                        <td>{{ $detalleRin }}</td>
                        <td class="cen">{{ number_format($cantRin, $decCantRin, ',', '.') }}</td>
                        <td class="num">{{ number_format($precioRin, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($importeRin, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @if ($esUltimaRin)
                    <tr class="rin-cierre">
                        <td></td>
                        <td></td>
                        <td class="cen num">{{ number_format($totalParesRin, $decCantRin, ',', '.') }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
@endforeach
