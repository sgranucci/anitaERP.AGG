@php
    $presentacion = $presentacion ?? 'pantalla';
    $filas = $datas ?? [];
@endphp
<thead>
    @if ($presentacion === 'pdf')
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => 'Marketplaces',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => 3,
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? (is_countable($filas) ? count($filas) : 0),
        ])
    @endif
    <tr @class(['columnas' => $presentacion === 'pdf']) @if ($presentacion === 'pantalla') style="background:#85C1E9;color:#17202A;" @endif>
        <th>Código</th>
        <th>Nombre</th>
        <th>Activo</th>
        @if ($presentacion === 'pantalla')
            <th>Asignaciones</th>
            <th></th>
        @endif
    </tr>
</thead>
<tbody>
    @forelse ($filas as $data)
        <tr>
            <td>{{ $data->codigo }}</td>
            <td>{{ $data->nombre }}</td>
            <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
            @if ($presentacion === 'pantalla')
                <td>{{ (int) ($data->asignaciones_count ?? 0) }}</td>
                <td class="text-nowrap">
                    @if (can('editar-marketplace-facturacion-local', false))
                        <a href="{{ route('editar_marketplace', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                            <i class="fa fa-edit"></i>
                        </a>
                    @endif
                    @if (can('eliminar-marketplace-facturacion-local', false))
                        <a href="{{ route('eliminar_marketplace', $data->id) }}" class="btn-accion-tabla tooltipsC eliminar-registro" title="Eliminar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </a>
                    @endif
                </td>
            @endif
        </tr>
    @empty
        @if ($presentacion === 'pantalla')
            <tr>
                <td colspan="5" class="text-center text-muted">No hay marketplaces.</td>
            </tr>
        @endif
    @endforelse
</tbody>
