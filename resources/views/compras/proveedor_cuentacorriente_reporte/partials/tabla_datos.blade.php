@php
    use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
    $modoDeuda = ($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
        !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA;
    $mostrarLinks = ! empty($mostrarLinks);
    $paraPdf = ! empty($para_pdf);
    $paraExcel = ! empty($para_excel);
    $fmt = static function ($valor) use ($paraExcel) {
        if ($valor === null || $valor === '') {
            return '';
        }
        $n = (float) $valor;
        if ($paraExcel) {
            return number_format($n, 2, '.', '');
        }

        return number_format($n, 2, ',', '.');
    };
    $colSpan = 10 + (($mostrarLinks && ! $paraPdf && ! $paraExcel) ? 1 : 0);
    $pdfUnaLinea = static function ($texto) use ($paraPdf) {
        $texto = (string) $texto;
        if (! $paraPdf || $texto === '') {
            return $texto;
        }

        return str_replace(' ', "\u{00A0}", $texto);
    };
@endphp
<thead>
    <tr>
        @if ($paraPdf)
            <th class="col-nowrap" style="width: 4%;">Código</th>
            <th class="col-texto" style="width: 22%;">Proveedor</th>
            <th class="col-texto" style="width: 7%;">Empresa</th>
            <th class="col-nowrap" style="width: 6.5%;">Fecha</th>
            <th class="col-nowrap" style="width: 6.5%;">Vencimiento</th>
            <th class="col-texto" style="width: 20%;">Comprobante</th>
            <th class="col-nowrap" style="width: 4%;">Moneda</th>
            @if ($modoDeuda)
                <th class="text-right" style="width: 10%;">Importe</th>
                <th class="text-right" style="width: 9%;">Aplicado</th>
                <th class="text-right" style="width: 11%;">Saldo pend.</th>
            @else
                <th class="text-right" style="width: 10%;">Debe</th>
                <th class="text-right" style="width: 9%;">Haber</th>
                <th class="text-right" style="width: 11%;">Saldo</th>
            @endif
        @else
            <th>Código</th>
            <th>Proveedor</th>
            <th>Empresa</th>
            <th>Fecha</th>
            <th>Vencimiento</th>
            <th>Comprobante</th>
            <th>Moneda</th>
            @if ($modoDeuda)
                <th class="text-right">Importe</th>
                <th class="text-right">Aplicado</th>
                <th class="text-right">Saldo pend.</th>
            @else
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
                <th class="text-right">Saldo</th>
            @endif
            @if ($mostrarLinks && ! $paraExcel)
                <th></th>
            @endif
        @endif
    </tr>
</thead>
<tbody>
@forelse ($filas as $fila)
    @php
        $tipo = $fila['tipo'] ?? 'movimiento';
        $esHeaderEmpresa = $tipo === 'header_empresa';
        $esHeader = $tipo === 'header_proveedor';
        $esTotal = $tipo === 'total_proveedor';
        $esApl = $tipo === 'aplicacion';
        $esSaldoAnt = $tipo === 'saldo_anterior';
        $trClass = $esHeaderEmpresa
            ? 'cc-rep-header-empresa'
            : ($esHeader ? 'cc-rep-header' : ($esTotal ? 'cc-rep-total' : ($esApl ? 'cc-rep-apl' : ($esSaldoAnt ? 'cc-rep-saldo-ant' : ''))));
        $clsNowrap = $paraPdf ? 'col-nowrap' : '';
        $clsTexto = $paraPdf ? 'col-texto' : '';
    @endphp
    @if ($esHeaderEmpresa)
        <tr class="{{ $trClass }}">
            <td colspan="{{ $colSpan }}">
                <strong>Empresa: {{ $fila['nombreempresa'] ?? $fila['empresa_nombre'] ?? '' }}</strong>
            </td>
        </tr>
        @continue
    @endif
    <tr class="{{ $trClass }}">
        <td class="{{ $clsNowrap }}">
            @if ($esHeader || $esTotal)
                @if ($mostrarLinks && ! empty($puede_ver_proveedor) && ! empty($fila['proveedor_id']))
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_proveedor', ['id' => $fila['proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['proveedor_codigo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['proveedor_codigo'] ?? '' }}
                @endif
            @endif
        </td>
        <td class="{{ $clsTexto }}">
            @if ($esHeader)
                <strong>{{ $pdfUnaLinea($fila['proveedor_nombre'] ?? '') }}</strong>
            @elseif ($esTotal)
                <strong>{{ $pdfUnaLinea('Total '.($fila['proveedor_nombre'] ?? '')) }}</strong>
            @elseif ($esSaldoAnt)
                <em>{{ $pdfUnaLinea($fila['comprobante'] ?? 'Saldo anterior') }}</em>
            @endif
        </td>
        <td class="{{ $clsTexto }}">{{ ($esHeader || $esApl || $esSaldoAnt || $tipo === 'movimiento') ? $pdfUnaLinea($fila['nombreempresa'] ?? '') : '' }}</td>
        <td class="{{ $clsNowrap }}">{{ $fila['fecha'] ?? '' }}</td>
        <td class="{{ $clsNowrap }}">{{ $fila['fechavencimiento'] ?? '' }}</td>
        <td class="{{ $clsTexto }}">
            @if (! $esHeader)
                {{ $pdfUnaLinea($fila['comprobante'] ?? '') }}
            @endif
        </td>
        <td class="{{ $clsNowrap }}">{{ $fila['etiqueta_moneda'] ?? ($fila['abreviatura'] ?? '') }}</td>
        @if ($modoDeuda)
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['importe'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['importe'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['aplicado'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['aplicado'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['saldo_pendiente'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['saldo_pendiente'] ?? null) }}
                @endif
            </td>
        @else
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['debe'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['debe'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['haber'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['haber'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['saldo'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['saldo'] ?? null) }}
                @endif
            </td>
        @endif
        @if ($mostrarLinks && ! $paraPdf && ! $paraExcel)
            <td class="text-nowrap">
                @if (($fila['comprobante_proveedor_id'] ?? 0) > 0 && ! empty($puede_ver_comprobante))
                    <a class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_comprobante_proveedor', ['id' => $fila['comprobante_proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                        title="Ver comprobante">
                        <i class="fas fa-file-invoice"></i>
                    </a>
                @endif
                @if (($fila['pagoproveedor_id'] ?? 0) > 0 && ! empty($puede_ver_pago))
                    <a class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_pagoproveedor', ['id' => $fila['pagoproveedor_id']]) }}"
                        title="Ver orden de pago">
                        <i class="fa fa-money-bill"></i>
                    </a>
                @endif
                @if (($fila['proveedor_id'] ?? 0) > 0 && $esHeader && ! empty($puede_ver_proveedor))
                    <a class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                        href="{{ route('listar_cuentacorriente_proveedor', ['id' => $fila['proveedor_id']]) }}"
                        title="Abrir ficha CC del proveedor">
                        <i class="fa fa-folder-open"></i>
                    </a>
                @endif
            </td>
        @endif
    </tr>
@empty
    <tr>
        <td colspan="12" class="text-muted text-center">Sin datos.</td>
    </tr>
@endforelse
</tbody>
