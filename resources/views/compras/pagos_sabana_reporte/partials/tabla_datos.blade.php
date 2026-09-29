@php
    use App\Support\Compras\PagosSabanaColumnasSupport as Col;
    use App\Support\Export\ExcelFormatoNumero;

    $columnasLocal = $columnas ?? [];
    $filasLocal = $filas ?? [];
    $paraExcel = ! empty($para_excel);
    $paraExport = ! empty($para_export) || $paraExcel;
    $paraPdf = $paraExport && ! $paraExcel;
    $soloFilas = ! empty($solo_filas);
    $cabeceraEnFilas = ! empty($cabecera_en_filas);
    $puedeVerProveedor = ! empty($puede_ver_proveedor);
    $puedeVerPp = ! empty($puede_ver_pagoproveedor);
    $puedeVerIe = ! empty($puede_ver_ingresoegreso);
    $puedeVerComp = ! empty($puede_ver_comprobante);
    $puedeVerOc = ! empty($puede_ver_ordencompra);
    $puedeVerSp = ! empty($puede_ver_solicitudpago);
    $importesTotales = $totales['importes'] ?? [];
    $fmtImporteExcel = $paraExcel
        ? ExcelFormatoNumero::formateadorMonto(ExcelFormatoNumero::preferenciaGlobal())
        : null;
    $pesoTotal = 0;
    foreach ($columnasLocal as $colPeso) {
        $pesoTotal += Col::pesoAncho((string) ($colPeso['clave'] ?? ''), (string) ($colPeso['tipo'] ?? ''));
    }
    $pesoTotal = max(1, $pesoTotal);
    $estiloCelda = static function (array $col, bool $pdf, bool $encabezado = false) use ($pesoTotal): string {
        $clave = (string) ($col['clave'] ?? '');
        $tipo = (string) ($col['tipo'] ?? '');
        $align = Col::alineacion($tipo, $clave);
        $nowrap = ! ($pdf && $encabezado) && (
            in_array($tipo, [Col::TIPO_IMPORTE, Col::TIPO_ENTERO, Col::TIPO_FECHA], true)
            || in_array($clave, ['tip', 'tipo_medio', 'empresa', 'proveedor_codigo', 'numero_op', 'fecha_cheque'], true)
            || $encabezado
        );
        if ($pdf) {
            $pct = round(Col::pesoAncho($clave, $tipo) / $pesoTotal * 100, 2);

            return 'text-align:'.$align.';vertical-align:middle;width:'.$pct.'%;'
                .($nowrap ? 'white-space:nowrap;' : 'word-wrap:break-word;');
        }

        $px = Col::anchoHtmlPx($clave, $tipo);

        return 'text-align:'.$align.';vertical-align:middle;min-width:'.$px.'px;width:'.$px.'px;'
            .($nowrap ? 'white-space:nowrap;' : '');
    };
@endphp

@if (! $soloFilas)
<table class="{{ $paraPdf ? 'data' : 'table table-sm table-bordered table-hover mb-0' }}" id="{{ $paraExport ? 'tabla-export' : 'tabla-paginada' }}" style="{{ $paraPdf ? 'width:100%;table-layout:fixed;border-collapse:collapse;font-size:7px;' : 'font-size:12px;width:max-content;min-width:100%;border-collapse:collapse;' }}">
    <colgroup>
        @foreach ($columnasLocal as $col)
            @php
                $claveCol = (string) ($col['clave'] ?? '');
                $tipoCol = (string) ($col['tipo'] ?? '');
            @endphp
            <col style="{{ $paraPdf
                ? 'width:'.round(Col::pesoAncho($claveCol, $tipoCol) / $pesoTotal * 100, 2).'%;'
                : 'width:'.Col::anchoHtmlPx($claveCol, $tipoCol).'px;' }}">
        @endforeach
    </colgroup>
@endif

@if (! $soloFilas || $cabeceraEnFilas)
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            @foreach ($columnasLocal as $col)
                <th style="background:#85C1E9;color:#17202A;font-weight:bold;{{ $estiloCelda($col, $paraPdf, true) }}">
                    {{ $col['etiqueta'] }}
                </th>
            @endforeach
        </tr>
    </thead>
@endif

    <tbody>
        @forelse ($filasLocal as $fila)
            @if (($fila['tipo_fila'] ?? '') === 'header_empresa')
                <tr class="table-secondary">
                    <td colspan="{{ max(1, count($columnasLocal)) }}">
                        <strong>{{ $fila['empresa_nombre'] ?? $fila['nombreempresa'] ?? '' }}</strong>
                    </td>
                </tr>
                @continue
            @endif
            <tr>
                @foreach ($columnasLocal as $col)
                    @php
                        $clave = $col['clave'];
                        $valor = $fila[$clave] ?? '';
                        $esImporte = ($col['tipo'] ?? '') === Col::TIPO_IMPORTE;
                        $esFecha = ($col['tipo'] ?? '') === Col::TIPO_FECHA;
                    @endphp
                    <td style="{{ $estiloCelda($col, $paraPdf) }}">
                        @if ($clave === 'proveedor_codigo' && $puedeVerProveedor && ! empty($fila['proveedor_id']))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('editar_proveedor', $fila['proveedor_id']) }}?origen=modal_consulta&vista=consulta">
                                {{ $valor }}
                            </a>
                        @elseif ($clave === 'numero_op' && ! $paraExport)
                            @if (! empty($fila['pagoproveedor_id']) && $puedeVerPp)
                                <a class="text-primary font-weight-bold" target="_blank" rel="noopener"
                                   href="{{ route('editar_pagoproveedor', $fila['pagoproveedor_id']) }}?origen=modal_consulta&vista=consulta">
                                    {{ $valor }}
                                </a>
                            @elseif (! empty($fila['caja_movimiento_id']) && $puedeVerIe)
                                <a class="text-primary font-weight-bold" target="_blank" rel="noopener"
                                   href="{{ route('editar_ingresoegreso', $fila['caja_movimiento_id']) }}?origen=modal_consulta&vista=consulta">
                                    {{ $valor }}
                                </a>
                            @else
                                {{ $valor }}
                            @endif
                        @elseif ($clave === 'comprobantes' && ! $paraExport && $puedeVerComp && ! empty($fila['comprobantes_links']))
                            @foreach ($fila['comprobantes_links'] as $i => $comp)
                                @if ($i > 0)
                                    <span> | </span>
                                @endif
                                @if (! empty($comp['id']))
                                    <a class="text-primary" target="_blank" rel="noopener"
                                       href="{{ route('editar_comprobante_proveedor', $comp['id']) }}?origen=modal_consulta&vista=consulta">
                                        {{ $comp['etiqueta'] }}
                                    </a>
                                @else
                                    {{ $comp['etiqueta'] ?? '' }}
                                @endif
                            @endforeach
                        @elseif ($clave === 'ordenes_compra' && ! $paraExport && $puedeVerOc && ! empty($fila['ordenes_compra_links']))
                            @foreach ($fila['ordenes_compra_links'] as $i => $oc)
                                @if ($i > 0)
                                    <span> | </span>
                                @endif
                                @if (! empty($oc['id']))
                                    <a class="text-primary" target="_blank" rel="noopener"
                                       href="{{ route('editar_ordencompra', $oc['id']) }}?origen=modal_consulta&vista=consulta">
                                        {{ $oc['numero'] }}
                                    </a>
                                @else
                                    {{ $oc['numero'] ?? '' }}
                                @endif
                            @endforeach
                        @elseif ($clave === 'detalle' && ! $paraExport && $puedeVerSp && ! empty($fila['solicitudpago_id']))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('editar_solicitudpago', $fila['solicitudpago_id']) }}?origen=modal_consulta&vista=consulta">
                                {{ $valor }}
                            </a>
                        @elseif ($esImporte)
                            @if (abs((float) $valor) >= 0.005)
                                @if ($fmtImporteExcel)
                                    {{ $fmtImporteExcel($valor) }}
                                @else
                                    {{ number_format((float) $valor, 2, ',', '.') }}
                                @endif
                            @endif
                        @elseif ($esFecha && $valor)
                            {{ \Carbon\Carbon::parse($valor)->format('d/m/Y') }}
                        @else
                            {{ $valor }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            @if (! $paraExport)
                <tr>
                    <td colspan="{{ max(1, count($columnasLocal)) }}" class="text-center text-muted">
                        Sin pagos en el rango consultado.
                    </td>
                </tr>
            @endif
        @endforelse
    </tbody>

    @if (! $soloFilas && ! empty($importesTotales) && count($filasLocal) > 0)
        <tfoot>
            <tr style="background:#D6EAF8;font-weight:600;">
                @foreach ($columnasLocal as $idx => $col)
                    <td style="background:#D6EAF8;font-weight:600;{{ $estiloCelda($col, $paraPdf) }}">
                        @if ($idx === 0)
                            Totales ({{ (int) ($totales['cantidad'] ?? 0) }})
                        @elseif (($col['tipo'] ?? '') === Col::TIPO_IMPORTE)
                            @php $totalCol = (float) ($importesTotales[$col['clave']] ?? 0); @endphp
                            @if ($fmtImporteExcel)
                                {{ $fmtImporteExcel($totalCol) }}
                            @else
                                {{ number_format($totalCol, 2, ',', '.') }}
                            @endif
                        @endif
                    </td>
                @endforeach
            </tr>
        </tfoot>
    @endif

@if (! $soloFilas)
</table>
@endif
