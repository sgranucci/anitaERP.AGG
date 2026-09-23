@php
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $puedeVerComprobante = $puede_ver_comprobante ?? false;
    $puedeVerProveedor = $puede_ver_proveedor ?? false;
    $puedeVerTipo = $puede_ver_tipotransaccion ?? false;
    $paraPdf = $para_pdf ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $columnas = $resultado['columnas'] ?? \App\Support\Compras\IvaCompras\IvaComprasColumnasSupport::columnas();
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>N.Pro.</th>
        <th>Proveedor</th>
        <th>CUIT</th>
        <th>Fec.Mov.</th>
        <th>Fec.Iva</th>
        <th>Tip</th>
        <th>Nro.Comp.</th>
        @foreach ($columnas as $col)
            <th class="text-right" style="white-space:nowrap;min-width:6.5rem;">{{ $col['label'] }}</th>
        @endforeach
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        @php
            $proveedorId = (int) ($fila['proveedor_id'] ?? 0);
            $tipoId = (int) ($fila['tipotransaccion_compra_id'] ?? 0);
            $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
        @endphp
        <tr>
            <td style="white-space:nowrap;">
                @if (! $paraPdf && $puedeVerProveedor && $proveedorId > 0)
                    <a href="{{ route('editar_proveedor', array_merge(['id' => $proveedorId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['proveedor_codigo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['proveedor_codigo'] ?? '' }}
                @endif
            </td>
            <td>
                @if (! $paraPdf && $puedeVerProveedor && $proveedorId > 0)
                    <a href="{{ route('editar_proveedor', array_merge(['id' => $proveedorId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['proveedor_nombre'] ?? '' }}
                    </a>
                @else
                    {{ $fila['proveedor_nombre'] ?? '' }}
                @endif
            </td>
            <td style="white-space:nowrap;">{{ $fila['cuit'] ?? '' }}</td>
            <td style="white-space:nowrap;">{{ $fila['fecha_mov'] ?? '' }}</td>
            <td style="white-space:nowrap;">{{ $fila['fecha_iva'] ?? '' }}</td>
            <td style="white-space:nowrap;">
                @if (! $paraPdf && $puedeVerTipo && $tipoId > 0)
                    <a href="{{ route('editar_tipotransaccion_compra', array_merge(['id' => $tipoId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['tipo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['tipo'] ?? '' }}
                @endif
            </td>
            <td style="white-space:nowrap;">
                @if (! $paraPdf && $puedeVerComprobante && $cpId > 0)
                    <a href="{{ route('editar_comprobante_proveedor', array_merge(['id' => $cpId], $queryConsulta)) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['comprobante'] ?? '' }}
                    </a>
                @else
                    {{ $fila['comprobante'] ?? '' }}
                @endif
            </td>
            @foreach ($columnas as $col)
                <td class="text-right" style="white-space:nowrap;">{{ $formatear($fila['columnas'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ 7 + count($columnas) }}" class="text-center text-muted">Sin comprobantes en el período.</td>
        </tr>
    @endforelse
    @if (! empty($resultado['totales_general']) && count($filas) > 0)
        <tr class="font-weight-bold" style="background:#D6EAF8;">
            <td colspan="7">TOTAL GENERAL</td>
            @foreach ($columnas as $col)
                <td class="text-right" style="white-space:nowrap;">{{ $formatear($resultado['totales_general'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endif
</tbody>
