@php
    $mostrarCentrocosto = \App\Support\Contable\MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros ?? []);
    $colSpan = $mostrarCentrocosto ? 19 : 18;
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
        <td colspan="{{ $colSpan }}"><strong>{{ $titulo ?? 'Mayor plano (Anita)' }}</strong></td>
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
            </td>
        </tr>
    @endif

    <tr>
        <th>Empresa</th>
        <th>Nro.Asi.</th>
        <th>Fecha</th>
        <th>Cuenta</th>
        <th>Descripcion</th>
        @if ($mostrarCentrocosto)
            <th>C.Costo</th>
        @endif
        <th>Mon</th>
        <th>Cotizacion</th>
        <th>Debe</th>
        <th>Haber</th>
        <th>Detalle</th>
        <th>Cód. emisor</th>
        <th>Nombre emisor</th>
        <th>Usuario</th>
        <th>fecha ult. mod</th>
        <th>O.Compra</th>
        <th>proyecto CAPEX</th>
        <th>Qué se compró (OC)</th>
        <th>Numeros de Facturas</th>
    </tr>
    @foreach ($filas as $f)
        @php $fila = is_array($f) ? $f : (array) $f; @endphp
        <tr>
            <td>{{ $fila['empresa_id'] ?? '' }}</td>
            <td>{{ $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '' }}</td>
            <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
            <td>{{ $fila['cuenta_codigo'] ?? '' }}</td>
            <td>{{ $fila['cuenta_nombre'] ?? '' }}</td>
            @if ($mostrarCentrocosto)
                <td>{{ $fila['centrocosto_codigo'] ?? '' }}</td>
            @endif
            <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
            <td>{{ $fmtCotiz($fila['cotizacion'] ?? null) }}</td>
            <td>{{ $fmt($fila['debe'] ?? null) }}</td>
            <td>{{ $fmt($fila['haber'] ?? null) }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $fila['emisor'] ?? '' }}</td>
            <td>{{ $fila['emisor_nombre'] ?? '' }}</td>
            <td>{{ $fila['usuario'] ?? '' }}</td>
            <td>{{ $fila['fecha_ult_mod'] ?? '' }}</td>
            <td>{{ (int) ($fila['nro_oc'] ?? 0) > 0 ? $fila['nro_oc'] : '' }}</td>
            <td>{{ $fila['proyecto_capex'] ?? '' }}</td>
            <td>{{ $fila['observacion_oc'] ?? '' }}</td>
            <td>{{ $fila['numeros_facturas'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
