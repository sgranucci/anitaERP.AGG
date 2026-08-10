@php
    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerArticulo = ! $paraPdf && ($puede_ver_articulo ?? false);
    $puedeVerProveedor = ! $paraPdf && ($puede_ver_proveedor ?? false);
    $puedeVerCuenta = ! $paraPdf && ($puede_ver_cuentacontable ?? false);
    $modo = $modo ?? \App\Support\Compras\ArticuloCuentaOcReporteFiltros::MODO_RESUMEN;
    $esDetalle = $modo === \App\Support\Compras\ArticuloCuentaOcReporteFiltros::MODO_DETALLE;
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>C&oacute;digo</th>
        <th>Descripci&oacute;n</th>
        <th>Cta. ERP</th>
        <th>Nombre cta. ERP</th>
        <th>Cta. Anita</th>
        <th>Nombre cta. Anita</th>
        <th>Coincide</th>
        @if ($esDetalle)
            <th>C&oacute;d.Prov.</th>
            <th>Proveedor</th>
            <th class="text-right">Veces</th>
        @else
            <th>Proveedores (veces)</th>
            <th class="text-right"># Prov.</th>
        @endif
        <th>Referencias OC</th>
        <th class="text-right"># OC</th>
        <th>Empresa</th>
    </tr>
</thead>
<tbody>
@forelse ($filas ?? [] as $fila)
    @php
        $diff = ! empty($fila['cuenta_diferencia']);
        $filaClass = ($diff && ! $paraPdf && ! $paraExcel) ? 'table-warning' : '';
    @endphp
    <tr class="{{ $filaClass }}">
        <td>
            @if ($puedeVerArticulo && (int) ($fila['articulo_id'] ?? 0) > 0)
                <a href="{{ route('editar_articulo', array_merge(['id' => $fila['articulo_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['sku'] ?? '' }}</a>
            @else
                {{ $fila['sku'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['descripcion_articulo'] ?? '' }}</td>
        <td>
            @if ($puedeVerCuenta && (int) ($fila['cuentacontable_id'] ?? 0) > 0)
                <a href="{{ route('editar_cuentacontable', array_merge(['id' => $fila['cuentacontable_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['cuenta_codigo'] ?? '' }}</a>
            @else
                {{ $fila['cuenta_codigo'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['cuenta_nombre'] ?? '' }}</td>
        <td>
            @if ($puedeVerCuenta && (int) ($fila['cuenta_anita_id'] ?? 0) > 0)
                <a href="{{ route('editar_cuentacontable', array_merge(['id' => $fila['cuenta_anita_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['cuenta_anita_codigo'] ?? '' }}</a>
            @else
                {{ $fila['cuenta_anita_codigo'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['cuenta_anita_nombre'] ?? '' }}</td>
        <td class="{{ $diff ? 'text-danger font-weight-bold' : 'text-success' }}">
            {{ $fila['cuenta_coincide_texto'] ?? ($diff ? 'No' : 'Sí') }}
        </td>
        @if ($esDetalle)
            <td>
                @if ($puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0)
                    <a href="{{ route('editar_proveedor', array_merge(['id' => $fila['proveedor_id']], $queryConsulta)) }}"
                        class="text-primary" target="_blank" rel="noopener">{{ $fila['codigoproveedor'] ?? '' }}</a>
                @else
                    {{ $fila['codigoproveedor'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['nombreproveedor'] ?? '' }}</td>
            <td class="text-right">
                @if ($paraExcel)
                    {{ (int) ($fila['veces'] ?? 0) }}
                @else
                    {{ number_format((int) ($fila['veces'] ?? 0), 0, ',', '.') }}
                @endif
            </td>
        @else
            <td>{{ $fila['proveedores_texto'] ?? '' }}</td>
            <td class="text-right">
                @if ($paraExcel)
                    {{ (int) ($fila['cantidad_proveedores'] ?? 0) }}
                @else
                    {{ number_format((int) ($fila['cantidad_proveedores'] ?? 0), 0, ',', '.') }}
                @endif
            </td>
        @endif
        <td>{{ $fila['refs_oc'] ?? '' }}</td>
        <td class="text-right">
            @if ($paraExcel)
                {{ (int) ($fila['cantidad_oc'] ?? 0) }}
            @else
                {{ number_format((int) ($fila['cantidad_oc'] ?? 0), 0, ',', '.') }}
            @endif
        </td>
        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="13" class="text-center text-muted">Sin datos para los filtros indicados.</td>
    </tr>
@endforelse
</tbody>
