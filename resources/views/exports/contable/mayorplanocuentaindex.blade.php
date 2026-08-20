@php
    $multiempresa = (bool) ($multiempresa ?? false);
    $colSpan = $multiempresa ? 17 : 16;
    $totales = is_array($totales ?? null) ? $totales : [];
    $cantidadLineas = (int) ($totales['cantidad_filas'] ?? 0);
    $formatoExcel = \App\Support\Export\ExcelFormatoNumero::normalizar(
        $excel_formato_numero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()
    );
    $fmt = \App\Support\Export\ExcelFormatoNumero::formateadorMonto($formatoExcel, 2);
    $fmtCotiz = \App\Support\Export\ExcelFormatoNumero::formateadorMonto($formatoExcel, 4);
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $colSpan }}" style="height: 52px;">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colSpan }}"><strong>{{ $titulo ?? 'Mayor analítico por cuenta contable' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colSpan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colSpan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ($cantidadLineas > 0)
        <tr>
            <td colspan="{{ $colSpan }}">
                {{ $cantidadLineas }} movimiento(s)
                · Debe {{ number_format((float) ($totales['total_debe'] ?? 0), 2, ',', '.') }}
                · Haber {{ number_format((float) ($totales['total_haber'] ?? 0), 2, ',', '.') }}
                · {{ (int) ($totales['cantidad_cuentas'] ?? 0) }} cuenta(s)
            </td>
        </tr>
    @endif

    <tr>
        <th>Fecha</th>
        <th>N.Asi.</th>
        <th>Tip</th>
        <th>Comprobante</th>
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción mov.</th>
        <th>Centro de costo</th>
        <th>O.Compra</th>
        <th>Mon</th>
        <th>Cotiz.</th>
        <th>Mon. Ref.</th>
        <th>Debe</th>
        <th>Haber</th>
        <th>Saldo del mes</th>
        <th>Saldo ejerc.</th>
        @if ($multiempresa)
            <th>Empr.</th>
        @endif
    </tr>
    @foreach ($filas as $f)
        @php
            $fila = is_array($f) ? $f : (array) $f;
            $tipo = $fila['tipo_fila'] ?? 'detalle';
        @endphp
        @if ($tipo === 'header_empresa')
            <tr>
                <td colspan="{{ $colSpan }}">Empresa: {{ $fila['nombreempresa'] ?? '' }}</td>
            </tr>
        @elseif ($tipo === 'header_cuenta')
            <tr>
                <td colspan="{{ $colSpan }}">Cuenta: {{ $fila['cuenta_codigo'] ?? '' }} {{ $fila['cuenta_nombre'] ?? '' }}</td>
            </tr>
        @elseif ($tipo === 'header_cc')
            <tr>
                <td colspan="{{ $colSpan }}">
                    Centro de costo: {{ ($fila['centrocosto_codigo'] ?? '') !== '' ? $fila['centrocosto_codigo'] : 'Sin CC' }}
                    {{ $fila['centrocosto_nombre'] ?? '' }}
                </td>
            </tr>
        @elseif ($tipo === 'saldo_inicial')
            <tr>
                <td>Saldo Inicial</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>{{ $fmt($fila['saldo_ejercicio'] ?? null) }}</td>
                @if ($multiempresa)
                    <td></td>
                @endif
            </tr>
        @elseif ($tipo === 'total_cuenta' || $tipo === 'total_cc')
            <tr>
                <td colspan="12">
                    {{ $tipo === 'total_cc' ? 'Total centro de costo '.(($fila['centrocosto_codigo'] ?? '') !== '' ? $fila['centrocosto_codigo'] : 'Sin CC') : 'Total cuenta '.($fila['cuenta_codigo'] ?? '') }}
                </td>
                <td>{{ $fmt($fila['debe'] ?? null) }}</td>
                <td>{{ $fmt($fila['haber'] ?? null) }}</td>
                <td></td>
                <td></td>
                @if ($multiempresa)
                    <td></td>
                @endif
            </tr>
        @else
            <tr>
                <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                <td>{{ $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '' }}</td>
                <td>{{ $fila['tipo_comp'] ?? '' }}</td>
                <td>{{ $fila['comprobante'] ?? '' }}</td>
                <td>{{ ($fila['emisor_fmt'] ?? '') !== '' ? $fila['emisor_fmt'] : ($fila['emisor'] ?? '') }}</td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>
                    {{ ($fila['centrocosto_codigo'] ?? '') !== '' ? $fila['centrocosto_codigo'] : 'Sin CC' }}
                    {{ $fila['centrocosto_nombre'] ?? '' }}
                </td>
                <td>{{ (int) ($fila['nro_oc'] ?? 0) > 0 ? $fila['nro_oc'] : '' }}</td>
                <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
                <td>{{ $fmtCotiz($fila['cotizacion'] ?? null) }}</td>
                <td>{{ $fmt($fila['mon_referencia'] ?? null) }}</td>
                <td>{{ $fmt($fila['debe'] ?? null) }}</td>
                <td>{{ $fmt($fila['haber'] ?? null) }}</td>
                <td>{{ $fmt($fila['saldo_mes'] ?? null) }}</td>
                <td>{{ $fmt($fila['saldo_ejercicio'] ?? null) }}</td>
                @if ($multiempresa)
                    <td>{{ $fila['empresa_id'] ?? '' }}</td>
                @endif
            </tr>
        @endif
    @endforeach
</table>
