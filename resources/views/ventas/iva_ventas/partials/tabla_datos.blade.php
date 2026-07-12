@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $puedeVerVenta = $puede_ver_venta ?? false;
    $puedeVerCliente = $puede_ver_cliente ?? false;
    $puedeVerPuntoventa = $puede_ver_puntoventa ?? false;
    $puedeVerTipotransaccion = $puede_ver_tipotransaccion ?? false;
    $paraPdf = $para_pdf ?? false;
    $clasificarHost = $clasificar_por_host ?? false;
    $mostrarSecciones = $mostrar_secciones ?? true;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $columnas = $resultado['columnas'] ?? \App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport::COLUMNAS;
    $colSpan = 7 + count($columnas) + ($clasificarHost ? 1 : 0);
    $seccionAnterior = null;
    $hostAnterior = null;
    $claseFila = static function (array $fila): string {
        $clases = [];
        if ($fila['anulada'] ?? false) {
            $clases[] = 'text-muted';
        }
        if (($fila['tipo_fila'] ?? '') === 'resumen_b') {
            $clases[] = 'iva-ventas-resumen-b';
        }

        return implode(' ', $clases);
    };
@endphp
<thead>
    <tr>
        <th>Cliente</th>
        <th>Nombre</th>
        <th>CUIT</th>
        <th>Fecha</th>
        <th>PV</th>
        @if ($clasificarHost)
            <th>Host</th>
        @endif
        <th>Tipo</th>
        <th>Comprobante</th>
        @foreach ($columnas as $col)
            <th class="text-right">{{ $col['label'] }}</th>
        @endforeach
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        @php
            $seccion = $fila['seccion'] ?? '';
            $host = (string) ($fila['host'] ?? '');
            $clienteId = (int) ($fila['cliente_id'] ?? 0);
            $pvId = (int) ($fila['puntoventa_id'] ?? 0);
            $tipoId = (int) ($fila['tipotransaccion_id'] ?? 0);
        @endphp
        @if ($mostrarSecciones && $seccion !== $seccionAnterior)
            <tr class="font-weight-bold" style="background-color: #d6eaf8;">
                <td colspan="{{ $colSpan }}">{{ $fila['seccion_label'] ?? $seccion }}</td>
            </tr>
            @php $seccionAnterior = $seccion; $hostAnterior = null; @endphp
        @endif
        @if ($mostrarSecciones && $clasificarHost && $host !== $hostAnterior)
            <tr class="font-weight-bold" style="background-color: #e9ecef;">
                <td colspan="{{ $colSpan }}">Host: {{ $host }}</td>
            </tr>
            @php $hostAnterior = $host; @endphp
        @endif
        <tr class="{{ $claseFila($fila) }}">
            <td>
                @if (! $paraPdf && $puedeVerCliente && $clienteId > 0)
                    <a href="{{ route('editar_cliente', array_merge(['id' => $clienteId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['cliente_codigo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['cliente_codigo'] ?? '' }}
                @endif
            </td>
            <td>
                @if (! $paraPdf && $puedeVerCliente && $clienteId > 0)
                    <a href="{{ route('editar_cliente', array_merge(['id' => $clienteId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['cliente_nombre'] ?? '' }}
                    </a>
                @else
                    {{ $fila['cliente_nombre'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['cuit'] ?? '' }}</td>
            <td>{{ $fila['fecha_mov'] ?? '' }}</td>
            <td>
                @if (! $paraPdf && $puedeVerPuntoventa && $pvId > 0)
                    <a href="{{ route('editar_puntoventa', array_merge(['id' => $pvId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary" title="{{ $fila['puntoventa_nombre'] ?? '' }}">
                        {{ $fila['puntoventa_codigo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['puntoventa_codigo'] ?? '' }}
                @endif
            </td>
            @if ($clasificarHost)
                <td>{{ $host }}</td>
            @endif
            <td>
                @if (! $paraPdf && $puedeVerTipotransaccion && $tipoId > 0)
                    <a href="{{ route('editar_tipotransaccion', array_merge(['id' => $tipoId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['tipo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['tipo'] ?? '' }}
                @endif
            </td>
            <td>
                @if (! $paraPdf && $puedeVerVenta && (int) ($fila['venta_id'] ?? 0) > 0)
                    <a href="{{ route('editar_factura', array_merge(['id' => $fila['venta_id']], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['comprobante'] ?? '' }}
                    </a>
                @else
                    {{ $fila['comprobante'] ?? '' }}
                @endif
            </td>
            @foreach ($columnas as $col)
                <td class="text-right">{{ $formatear($fila['columnas'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ $colSpan }}" class="text-center text-muted">Sin registros</td>
        </tr>
    @endforelse
    @if ($mostrarSecciones && count($filas) > 0 && ! empty($resultado['totales_general']))
        <tr class="font-weight-bold" style="background-color: #d6eaf8;">
            <td colspan="{{ 7 + ($clasificarHost ? 1 : 0) }}">TOTAL GENERAL</td>
            @foreach ($columnas as $col)
                <td class="text-right">{{ $formatear($resultado['totales_general'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endif
</tbody>
