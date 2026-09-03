@php
    $multiempresa = (bool) ($multiempresa ?? false);
    $soloVentas = ! empty($filtros['solo_movimientos_ventas']);
    $resumen = is_array($resumen ?? null) ? $resumen : [];
    $mostrarCcResumen = collect($resumen)->contains(fn ($row) => array_key_exists('centrocosto_codigo', $row));
    $mostrarCentrocosto = \App\Support\Contable\MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros ?? []);
    $colSpan = $soloVentas
        ? (8 + ($mostrarCcResumen ? 1 : 0))
        : (($multiempresa ? 17 : 16) - ($mostrarCentrocosto ? 0 : 1));
    $colSpanAntesImportes = $mostrarCentrocosto ? 12 : 11;
    $vaciasSaldoInicial = $mostrarCentrocosto ? 14 : 13;
    $totales = is_array($totales ?? null) ? $totales : [];
    $cantidadLineas = (int) ($totales['cantidad_filas'] ?? 0);
    $formatoExcel = \App\Support\Export\ExcelFormatoNumero::normalizar(
        $excel_formato_numero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal()
    );
    $fmt = \App\Support\Export\ExcelFormatoNumero::formateadorMonto($formatoExcel, 2);
    $fmtCotiz = \App\Support\Export\ExcelFormatoNumero::formateadorMonto($formatoExcel, 4);
    $cuadre = $cuadre_cobro_ventas ?? null;
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

    @if ($soloVentas)
    <tr>
        <th>Cuenta</th>
        <th>Nombre</th>
        @if ($mostrarCcResumen)
            <th>Centro de costo</th>
        @endif
        <th>Saldo inicial</th>
        <th>Debe</th>
        <th>Haber</th>
        <th>Neto (H-D)</th>
        <th>Saldo</th>
        <th>Líneas</th>
    </tr>
    @foreach ($resumen as $row)
        @php
            $debe = (float) ($row['total_debe'] ?? 0);
            $haber = (float) ($row['total_haber'] ?? 0);
            $saldoIni = (float) ($row['saldo_inicial'] ?? 0);
        @endphp
        <tr>
            <td>{{ $row['cuenta_codigo'] ?? '' }}</td>
            <td>{{ $row['cuenta_nombre'] ?? '' }}</td>
            @if ($mostrarCcResumen)
                <td>
                    {{ ($row['centrocosto_codigo'] ?? '') !== '' ? $row['centrocosto_codigo'] : 'Sin CC' }}
                    @if (! empty($row['centrocosto_nombre']))
                        {{ $row['centrocosto_nombre'] }}
                    @endif
                </td>
            @endif
            <td>{{ $fmt($saldoIni) }}</td>
            <td>{{ $fmt($debe) }}</td>
            <td>{{ $fmt($haber) }}</td>
            <td>{{ $fmt($haber - $debe) }}</td>
            <td>{{ $fmt($saldoIni + $debe - $haber) }}</td>
            <td>{{ (int) ($row['cantidad_lineas'] ?? 0) }}</td>
        </tr>
    @endforeach
    @if (! empty($cuadre))
        <tr>
            <td colspan="{{ $colSpan }}"></td>
        </tr>
        <tr>
            <td colspan="{{ $colSpan }}">Cuadre total comprobantes (listado IVA ventas / subdiario)</td>
        </tr>
        <tr>
            <th>Concepto</th>
            <th>Neto Debe (D-H)</th>
            @for ($i = 2; $i < $colSpan; $i++)
                <th></th>
            @endfor
        </tr>
        <tr>
            <td>Deudores por ventas ({{ $cuadre['deudores_codigo'] ?: '113100' }})</td>
            <td>{{ $fmt($cuadre['deudores'] ?? 0) }}</td>
            @for ($i = 2; $i < $colSpan; $i++)
                <td></td>
            @endfor
        </tr>
        <tr>
            <td>Caja contado ({{ $cuadre['caja_codigo'] ?: '111100' }})</td>
            <td>{{ $fmt($cuadre['caja'] ?? 0) }}</td>
            @for ($i = 2; $i < $colSpan; $i++)
                <td></td>
            @endfor
        </tr>
        <tr>
            <td>Total cobro (= columna Total del listado)</td>
            <td>{{ $fmt($cuadre['total_cobro'] ?? 0) }}</td>
            @for ($i = 2; $i < $colSpan; $i++)
                <td></td>
            @endfor
        </tr>
    @endif
    @else
    <tr>
        <th>Fecha</th>
        <th>N.Asi.</th>
        <th>Tip</th>
        <th>Comprobante</th>
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción mov.</th>
        @if ($mostrarCentrocosto)
            <th>Centro de costo</th>
        @endif
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
                @for ($i = 0; $i < $vaciasSaldoInicial; $i++)
                    <td></td>
                @endfor
                <td>{{ $fmt($fila['saldo_ejercicio'] ?? null) }}</td>
                @if ($multiempresa)
                    <td></td>
                @endif
            </tr>
        @elseif ($tipo === 'total_cuenta' || $tipo === 'total_cc')
            <tr>
                <td colspan="{{ $colSpanAntesImportes }}">
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
                @if ($mostrarCentrocosto)
                    <td>
                        {{ ($fila['centrocosto_codigo'] ?? '') !== '' ? $fila['centrocosto_codigo'] : 'Sin CC' }}
                        {{ $fila['centrocosto_nombre'] ?? '' }}
                    </td>
                @endif
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
    @endif
</table>
