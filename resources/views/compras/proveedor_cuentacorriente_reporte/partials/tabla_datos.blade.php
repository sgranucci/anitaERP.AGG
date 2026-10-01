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
    $colSpan = ($modoDeuda ? 11 : 10) + (($mostrarLinks && ! $paraPdf && ! $paraExcel) ? 1 : 0);
    $pdfCortar = static function ($texto, int $max) use ($paraPdf) {
        $texto = trim(preg_replace('/\s+/u', ' ', (string) $texto) ?? '');
        if (! $paraPdf || $texto === '' || mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $max - 1)).'…';
    };
@endphp
<thead>
    <tr>
        @if ($paraPdf)
            <th class="col-nowrap" style="width: 5%;">Código</th>
            <th style="width: {{ $modoDeuda ? '13%' : '16%' }};">Proveedor</th>
            <th style="width: {{ $modoDeuda ? '10%' : '11%' }};">Empresa</th>
            <th class="col-nowrap" style="width: {{ $modoDeuda ? '7%' : '8%' }};">Fecha</th>
            <th class="col-nowrap" style="width: {{ $modoDeuda ? '7%' : '8%' }};">Vencimiento</th>
            <th style="width: {{ $modoDeuda ? '18%' : '23%' }};">Comprobante</th>
            <th class="col-nowrap" style="width: 5%;">Moneda</th>
            @if ($modoDeuda)
                <th class="text-right" style="width: 8%;">Importe</th>
                <th class="text-right" style="width: 8%;">Aplicado</th>
                <th class="text-right" style="width: 9%;">Saldo pend.</th>
                <th class="text-right" style="width: 10%;">Saldo</th>
            @else
                <th class="text-right" style="width: 8%;">Debe</th>
                <th class="text-right" style="width: 8%;">Haber</th>
                <th class="text-right" style="width: 8%;">Saldo</th>
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
                <th class="text-right" title="Monto total del comprobante">Importe</th>
                <th class="text-right" title="Total ya aplicado o pagado">Aplicado</th>
                <th class="text-right" title="Saldo de este comprobante">Saldo pend.</th>
                <th class="text-right" title="Saldo acumulado del proveedor después de cada comprobante">Saldo</th>
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
        $mostrarIdentidad = $esHeader || $esTotal || ($paraPdf && ($tipo === 'movimiento' || $esSaldoAnt));
    @endphp
    @if ($esHeader && $paraPdf)
        @continue
    @endif
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
            @if ($mostrarIdentidad)
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
        <td>
            @if ($esHeader)
                <strong>{{ $fila['proveedor_nombre'] ?? '' }}</strong>
            @elseif ($esTotal)
                <strong>{{ $pdfCortar('Total '.($fila['proveedor_nombre'] ?? ''), 34) }}</strong>
            @elseif ($paraPdf && $tipo === 'movimiento')
                {{ $pdfCortar($fila['proveedor_nombre'] ?? '', 28) }}
            @elseif ($esSaldoAnt)
                <em>{{ $fila['comprobante'] ?? 'Saldo anterior' }}</em>
            @endif
        </td>
        <td>{{ ($esHeader || $esApl || $esSaldoAnt || $tipo === 'movimiento') ? $pdfCortar($fila['nombreempresa'] ?? '', 18) : '' }}</td>
        <td class="{{ $clsNowrap }}">{{ $fila['fecha'] ?? '' }}</td>
        <td class="{{ $clsNowrap }}">{{ $fila['fechavencimiento'] ?? '' }}</td>
        <td>
            @if (! $esHeader)
                {{ $pdfCortar($fila['comprobante'] ?? '', 42) }}
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
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['saldo_parcial'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['saldo_parcial'] ?? null) }}
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
        <td colspan="{{ $colSpan }}" class="text-muted text-center">Sin datos.</td>
    </tr>
@endforelse
</tbody>
