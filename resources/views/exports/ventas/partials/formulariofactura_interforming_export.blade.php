{{-- Layout FAE Interforming (Anita). Solo exportación. --}}
@php
    $ifExp = $interformingExport ?? \App\Support\Ventas\InterformingFacturaExportacionPdfSupport::contextoVista($venta);
    $nroAnita = \App\Support\Ventas\InterformingFacturaExportacionPdfSupport::numeroComprobanteAnita($venta);
    $abrevMon = $venta->monedas->abreviatura ?? 'U$S';
    $incotermAbr = $ifExp['incoterm_abrev'] !== '' ? $ifExp['incoterm_abrev'] : 'FCA';
    $totalDoc = 0.0;
    foreach ($conceptosTotales as $ct) {
        if (($ct['concepto'] ?? '') === 'Total') {
            $totalDoc = (float) ($ct['importe'] ?? 0);
        }
    }
    if ($totalDoc <= 0) {
        $totalDoc = (float) ($venta->total ?? 0);
    }
    $cuitEmp = $venta->puntoventas->empresas->nroinscripcion ?? '';
    $decCant = (int) config('facturacion.DECIMAL_CANTIDAD');
    $lugarIncoterm = $ifExp['lugar_emision'];
    if (str_contains($lugarIncoterm, ',')) {
        $lugarIncoterm = trim((string) substr($lugarIncoterm, strrpos($lugarIncoterm, ',') + 1));
    }
    $lugarIncoterm = mb_strtoupper($lugarIncoterm);
@endphp
<style>
    .fae-if { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
    .fae-if table { width: 100%; border-collapse: collapse; }
    .fae-if td { vertical-align: top; }
    .fae-if .letra-celda { width: 56px; text-align: center; padding-top: 2px; }
    .fae-if .letra-tabla { width: 46px; margin: 0 auto; border-collapse: collapse; }
    .fae-if .letra-caja {
        width: 46px; height: 40px; border: 1.5px solid #111;
        font-size: 24px; font-weight: bold; line-height: 38px; text-align: center;
    }
    .fae-if .codigo-caja {
        width: 46px; border: 1px solid #111; font-size: 6.5px; line-height: 10px;
        padding: 1px 0; text-align: center;
    }
    .fae-if .titulo { font-size: 12px; font-weight: bold; text-align: right; }
    .fae-if .nro { font-size: 11px; font-weight: bold; text-align: right; margin: 2px 0 6px; }
    .fae-if .fecha-caja {
        border: 1px solid #111; border-radius: 5px; padding: 2px 6px;
        text-align: center; width: 84px;
    }
    .fae-if .fecha-caja .lbl { font-size: 7px; }
    .fae-if .fecha-caja .val { font-size: 11px; font-weight: bold; }
    .fae-if .iva-exento { text-align: right; font-size: 8px; font-weight: bold; margin: 8px 0 10px; }
    .fae-if .lbl-meta { font-size: 7.5px; color: #333; }
    .fae-if .items-head td {
        background: none; font-size: 8px; font-weight: bold;
        padding: 2px 4px 4px; border: none; border-bottom: 0.6pt solid #888;
    }
    .fae-if .items-row td {
        padding: 5px 4px; border: none; font-size: 9px;
    }
    .fae-if .caja {
        border: 1px solid #555; border-radius: 5px; padding: 5px 7px;
    }
    .fae-if .tr { text-align: right; }
    .fae-if .tc { text-align: center; }
    .fae-if .tl { text-align: left; }
    {{-- Pie anclado abajo como FAE Anita (INCOTERMS + cajas + web) --}}
    .fae-if-hoja {
        position: relative;
        min-height: 255mm;
    }
    .fae-if-pie {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
    }
</style>
<div class="fae-if fae-if-hoja">
    {{-- Fila 1: logo/empresa | E | título+nro --}}
    <table>
        <tr>
            <td style="width:40%;">
                @if ($logoEmpresaDataUri)
                    <img src="{{ $logoEmpresaDataUri }}" style="max-height:46px;max-width:140px;" alt="">
                @endif
                <div style="font-size:11px;font-weight:bold;margin-top:2px;">{{ $venta->puntoventas->empresas->nombre ?? '' }}</div>
            </td>
            <td class="letra-celda tc">
                <table class="letra-tabla" align="center">
                    <tr>
                        <td class="letra-caja">E</td>
                    </tr>
                    <tr>
                        <td class="codigo-caja">Codigo 19</td>
                    </tr>
                </table>
            </td>
            <td style="width:46%;">
                <div class="titulo">FACTURA DE EXPORTACION</div>
                <div class="nro">Nro: {{ $nroAnita }}</div>
                <div style="text-align:right;">
                    <div class="fecha-caja" style="display:inline-block;text-align:center;">
                        <div class="lbl">Fecha</div>
                        <div class="val">{{ date('d-m-Y', strtotime($venta->fecha ?? '')) }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Fila 2: domicilio/tel | vacío | datos fiscales emisor --}}
    <table style="margin-top:2px;">
        <tr>
            <td style="width:40%;">
                <div>{{ $ifExp['domicilio_emisor'] }}</div>
                @if ($ifExp['telefono_emisor'] !== '')
                    <div>Tel.: {{ $ifExp['telefono_emisor'] }}</div>
                @endif
                @if ($ifExp['fax'] !== '')
                    <div>Fax: {{ $ifExp['fax'] }}</div>
                @endif
                <div>{{ $ifExp['leyenda_iva'] }}</div>
            </td>
            <td style="width:14%;">&nbsp;</td>
            <td style="width:46%;font-size:8px;">
                CUIT: {{ $cuitEmp }}<br>
                @if ($ifExp['caja_jub'] !== '')
                    CAJA JUB. IND. Y COM: {{ $ifExp['caja_jub'] }}<br>
                @endif
                ING.BRUTOS: {{ $ifExp['iibb'] }}<br>
                INICIO DE ACTIVIDADES: {{ $ifExp['inicio_act'] }}
            </td>
        </tr>
    </table>

    <div class="iva-exento">IVA EXENTO OPERACION DE EXPORTACION</div>

    <table>
        <tr>
            <td style="width:52%;">
                @php
                    $nombreIvaCli = $venta->clientes?->condicionivas?->nombre
                        ?? $venta->condicionivas?->nombre
                        ?? '';
                    $cuitPaisCli = trim((string) ($venta->nroinscripcion ?? ''));
                    if ($cuitPaisCli === '' && $venta->clientes) {
                        $cuitPaisCli = trim((string) ($venta->clientes->nroinscripcion ?? $venta->clientes->numerodocumento ?? ''));
                    }
                    $etiqCuitPais = trim((string) (
                        $venta->clientes?->tipodocumentos?->abreviatura
                        ?? $venta->clientes?->tipodocumentos?->nombre
                        ?? 'CUIT'
                    ));
                    if ($etiqCuitPais === '') {
                        $etiqCuitPais = 'CUIT';
                    }
                @endphp
                <strong>{{ $venta->nombre ?? $venta->clientes->nombre ?? '' }}</strong>
                @if (!empty($venta->clientes->codigo))
                    ({{ str_pad((string) $venta->clientes->codigo, 6, '0', STR_PAD_LEFT) }})
                @endif
                <br>{{ $venta->domicilio }}
                @if (trim((string) ($venta->codigopostal ?? '')) !== '')
                    <br>({{ $venta->codigopostal }})
                    @if (!empty($venta->localidades->nombre))
                        - {{ $venta->localidades->nombre }}
                    @endif
                @endif
                @if (!empty($venta->provincias->nombre))
                    <br>{{ $venta->provincias->nombre }}
                @endif
                @if (!empty($venta->paises->nombre) && empty($venta->provincias->nombre))
                    <br>{{ $venta->paises->nombre }}
                @endif
                @if ($nombreIvaCli !== '')
                    <br>I.V.A.: {{ $nombreIvaCli }}
                @endif
                @if ($cuitPaisCli !== '')
                    <br>{{ $etiqCuitPais }}: {{ $cuitPaisCli }}
                @endif
                <br><br>
                <span class="lbl-meta">DIVISA</span> &nbsp; <strong>{{ $abrevMon }}</strong>
                <br>
                <span class="lbl-meta">DESTINO DE EMBARQUE</span> &nbsp; {{ $ifExp['destino'] }}
            </td>
            <td style="width:48%;">
                <table>
                    <tr>
                        <td class="lbl-meta" style="width:38%;">LUGAR DE EMISION</td>
                        <td>{{ $ifExp['lugar_emision'] }}</td>
                    </tr>
                    <tr>
                        <td class="lbl-meta">LISTA DE EMPAQUE</td>
                        <td>{{ $nroAnita }}</td>
                    </tr>
                    <tr>
                        <td class="lbl-meta">COND.DE PAGO</td>
                        <td>{{ $ifExp['forma_pago'] }}</td>
                    </tr>
                    <tr>
                        <td class="lbl-meta">INCOTERMS</td>
                        <td>{{ $incotermAbr }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top:10px;">
        <tr class="items-head">
            <td class="tl" style="width:12%;">CANTIDAD</td>
            <td class="tl" style="width:52%;">DETALLE</td>
            <td class="tr" style="width:18%;">PRECIO UNITARIO</td>
            <td class="tr" style="width:18%;">PRECIO TOTAL</td>
        </tr>
        @foreach ($itemsPagina as $item)
            @php
                $cant = (float) ($item['cantidad'] ?? 0);
                $precio = (float) ($item['precio'] ?? 0);
                $importe = round($cant * $precio, 2);
            @endphp
            <tr class="items-row">
                <td class="tr">{{ number_format($cant, $decCant, ',', '.') }}</td>
                <td class="tl">{{ $item['detalle'] ?? '' }}</td>
                <td class="tr">{{ number_format($precio, 3, ',', '.') }}</td>
                <td class="tr">{{ number_format($importe, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    @if ($esUltima ?? true)
        <div class="fae-if-pie">
            <div style="margin-bottom:8px;">
                @if ($incotermAbr !== '')
                    <div>INCOTERMS {{ $incotermAbr }}{{ $lugarIncoterm !== '' ? ' '.$lugarIncoterm : '' }}</div>
                @endif
                @if (!empty($venta->leyenda))
                    <div>{{ $venta->leyenda }}</div>
                @elseif (!empty($ifExp['exportacion']->leyendaexportacion))
                    <div>{{ $ifExp['exportacion']->leyendaexportacion }}</div>
                @elseif (!empty($ifExp['exportacion']->mercaderia))
                    <div>{{ $ifExp['exportacion']->mercaderia }}</div>
                @endif
            </div>

            <table>
                <tr>
                    <td style="width:48%;padding-right:8px;">
                        <div class="caja">
                            <table>
                                <tr>
                                    <td>CANT.DE BULTOS</td>
                                    <td class="tr">{{ number_format($ifExp['bultos'], 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>PESO NETO</td>
                                    <td class="tr">{{ number_format($ifExp['peso_neto'], 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>TRANSPORTE</td>
                                    <td class="tr">{{ $venta->transportes->nombre ?? '' }}</td>
                                </tr>
                            </table>
                        </div>
                        @if ($qrDataUri !== '')
                            <div style="margin-top:8px;">
                                <img src="{{ $qrDataUri }}" width="70" height="70" alt="QR">
                            </div>
                        @endif
                    </td>
                    <td style="width:52%;">
                        <div class="caja">
                            <table>
                                <tr>
                                    <td>SUBTOTAL {{ $incotermAbr }} {{ $abrevMon }}</td>
                                    <td class="tr">{{ number_format($totalDoc, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:bold;padding-top:4px;border-top:1px solid #111;">TOTAL {{ $incotermAbr }} {{ $abrevMon }}</td>
                                    <td class="tr" style="font-weight:bold;padding-top:4px;border-top:1px solid #111;">{{ number_format($totalDoc, 2, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div style="padding-top:8px;font-size:8px;">
                            CAE Nro.: {{ $venta->cae }}<br>
                            Fecha de vto. de CAE:
                            {{ $venta->fechavencimientocae ? date('d/m/y', strtotime($venta->fechavencimientocae)) : '' }}
                        </div>
                    </td>
                </tr>
            </table>

            @if ($ifExp['web'] !== '')
                <div class="tc" style="margin-top:10px;font-size:8px;">{{ $ifExp['web'] }}</div>
            @endif
        </div>
    @endif
</div>
