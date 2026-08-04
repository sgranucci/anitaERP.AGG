<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Recuento {{ $recuento->codigo }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 8px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 14%; }
        .num { text-align: right; white-space: nowrap; }
        .dif-neg { color: #a00; font-weight: bold; }
        .dif-cero { color: #060; }
        .grupo { background: #d5e8f5; font-weight: bold; }
        .subtotal { background: #f0f0f0; font-weight: bold; }
        .total-general { background: #e8e8e8; font-weight: bold; }
    </style>
</head>
<body>
    @php
        use App\Support\Configuracion\EmpresaLogoArchivo;
        use App\Support\Stock\RecuentoDetalleExportSupport;

        $nombreEmpresaLogo = optional($recuento->empresa)->nombre;
        $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresaLogo);
        $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
        $detalleExport = $detalleExport
            ?? RecuentoDetalleExportSupport::agruparPorTipoArticulo($recuento->items);
    @endphp
    <table style="width:100%; margin-bottom:10px;">
        <tr>
            <td style="width:55%; border:none;">
                @if ($logoEmpresaDataUri)
                    <img src="{{ $logoEmpresaDataUri }}" alt="" style="max-width:180px; max-height:56px;">
                @endif
                <div style="font-size:12px; font-weight:bold; margin-top:4px;">{{ $nombreEmpresaLogo ?? '—' }}</div>
            </td>
            <td style="text-align:right; border:none;">
                <h1>Recuento {{ $recuento->codigo }}</h1>
                <p style="margin:0; color:#555;">Generado el {{ date('d/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <h2>Datos generales</h2>
    <table class="cabecera">
        <tr>
            <td class="lbl">Fecha</td><td>{{ optional($recuento->fecha)->format('d/m/Y') }}</td>
            <td class="lbl">Estado</td><td>{{ \App\Models\Stock\Recuento::etiquetaEstado($recuento->estado) }}</td>
        </tr>
        <tr>
            <td class="lbl">Depósito</td><td>{{ optional($recuento->deposito)->etiqueta() }}</td>
            <td class="lbl">Usuario</td><td>{{ optional($recuento->usuario)->nombre }}</td>
        </tr>
        <tr>
            <td class="lbl">Comentario</td><td colspan="3">{{ $recuento->comentario ?? '—' }}</td>
        </tr>
    </table>

    <h2>Detalle de conteo</h2>
    <p style="margin:0 0 6px 0; color:#555; font-size:8px;">
        Ordenado por tipo de art&iacute;culo y SKU.
        Costo unitario = precio de &uacute;ltima compra: primero Anita (stkmae.stkm_pre_compra3);
        si no hay dato, &uacute;ltima COM confirmada en el ERP; si no, costo/PPP del art&iacute;culo.
    </p>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Descripción</th>
                <th>Color</th>
                <th>Talle</th>
                <th>UM</th>
                <th class="num">Saldo sistema</th>
                <th class="num">Contado</th>
                <th class="num">Diferencia a ajustar</th>
                <th class="num">Costo u/c</th>
                <th class="num">Valor contado</th>
                <th class="num">Valor dif.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($detalleExport['grupos'] as $grupo)
                <tr class="grupo">
                    <td colspan="11">
                        Tipo: {{ $grupo['tipo_nombre'] }}
                        ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})
                    </td>
                </tr>
                @foreach ($grupo['items'] as $linea)
                    @php
                        $item = $linea['item'];
                        $dif = $linea['diferencia'];
                        $costoUc = $linea['costo_uc'];
                        $valorContado = $linea['valor_contado'];
                        $valorDif = $linea['valor_dif'];
                        $claseDif = abs($dif) > 1e-9 ? 'dif-neg' : 'dif-cero';
                    @endphp
                    <tr>
                        <td>{{ optional($item->articulos)->sku }}</td>
                        <td>{{ $item->detalle ?: optional($item->articulos)->descripcion }}</td>
                        <td>{{ optional($item->color)->nombre ?? '—' }}</td>
                        <td>{{ optional($item->talle)->nombre ?? '—' }}</td>
                        <td>{{ optional($item->unidadmedida)->abreviatura ?? optional($item->articulos?->unidadesdemedidas)->abreviatura }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->saldo_sistema, 6, '.', ''), '0'), '.') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->cantidad_contada, 6, '.', ''), '0'), '.') }}</td>
                        <td class="num {{ $claseDif }}">
                            {{ rtrim(rtrim(number_format($dif, 6, '.', ''), '0'), '.') }}
                        </td>
                        <td class="num">
                            @if ($costoUc !== null)
                                {{ number_format((float) $costoUc, 4, '.', '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">
                            @if ($valorContado !== null)
                                {{ number_format($valorContado, 2, '.', '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">
                            @if ($valorDif !== null)
                                {{ number_format($valorDif, 2, '.', '') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="9" class="num">Subtotal {{ $grupo['tipo_nombre'] }}</td>
                    <td class="num">{{ number_format($grupo['subtotal_valor_contado'], 2, '.', '') }}</td>
                    <td class="num">{{ number_format($grupo['subtotal_valor_dif'], 2, '.', '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">Sin l&iacute;neas de conteo.</td>
                </tr>
            @endforelse
        </tbody>
        @if (($detalleExport['cantidad_lineas'] ?? 0) > 0)
            <tfoot>
                <tr class="total-general">
                    <th colspan="9" class="num">Total general (costo u/c)</th>
                    <th class="num">{{ number_format($detalleExport['total_valor_contado'], 2, '.', '') }}</th>
                    <th class="num">{{ number_format($detalleExport['total_valor_dif'], 2, '.', '') }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
