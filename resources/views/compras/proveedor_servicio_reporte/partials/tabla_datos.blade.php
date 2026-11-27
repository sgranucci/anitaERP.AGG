@php
    $paraPdf = $para_pdf ?? false;
    $paraExcel = ! empty($para_excel);
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $puedeVerProveedor = ! $paraPdf && ($puede_ver_proveedor ?? false);
    $pdfCabecera = $paraPdf && ! $paraExcel;
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    @if ($pdfCabecera)
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo ?? '',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => 8,
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? 0,
        ])
    @endif
    <tr class="columnas">
        <th>C&oacute;digo</th>
        <th>Proveedor</th>
        <th>CUIT</th>
        <th>Estado</th>
        <th>Tipo de servicio</th>
        <th>Empresa</th>
        <th>Cliente / medidor</th>
        <th>Detalle</th>
    </tr>
</thead>
<tbody>
@forelse ($filas ?? [] as $fila)
    @php
        $suspendido = ($fila['proveedor_estado'] ?? '') === \App\Models\Compras\Proveedor::ESTADO_SUSPENDIDO;
    @endphp
    <tr class="{{ ($suspendido && ! $paraPdf) ? 'table-warning' : '' }}">
        <td>
            @if ($puedeVerProveedor && (int) ($fila['proveedor_id'] ?? 0) > 0)
                <a href="{{ route('editar_proveedor', array_merge(['id' => $fila['proveedor_id']], $queryConsulta)) }}"
                    class="text-primary" target="_blank" rel="noopener">{{ $fila['codigoproveedor'] ?? '' }}</a>
            @else
                {{ $fila['codigoproveedor'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
        <td>{{ $fila['cuit'] ?? '' }}</td>
        <td>{{ $fila['proveedor_estado'] ?? '' }}</td>
        <td>{{ $fila['tiposervicio'] ?? '' }}</td>
        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
        <td>{{ $fila['cliente'] ?? '' }}</td>
        <td>{{ $fila['detalle'] ?? '' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="text-center text-muted">No hay proveedores con clientes de servicios para los filtros elegidos.</td>
    </tr>
@endforelse
</tbody>
