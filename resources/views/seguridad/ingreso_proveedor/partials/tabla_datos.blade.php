@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
    $puedeVerEditar = $puede_ver_editar ?? false;
    $retorno = $retornoListadoQuery ?? [];
@endphp
<table class="table table-striped table-bordered table-hover" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Motivo de visita</th>
            <th>Sala / Punto de ingreso</th>
            <th>Sector</th>
            <th>&Aacute;rea de destino</th>
            <th>Gener&oacute; Usuario</th>
            <th>Estado</th>
            <th>T&iacute;tulo</th>
            <th>Comentario</th>
            @if ($puedeVerEditar)
                <th class="width80" data-orderable="false"></th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>
                    @if ($puedeVerEditar && can('editar-ingreso-proveedor', false))
                        <a href="{{ route('editar_ingreso_proveedor', ['id' => $data->id] + $retorno) }}" class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
                    @else
                        {{ $data->id }}
                    @endif
                </td>
                <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                <td>{{ $data->empresas->nombre ?? '' }}</td>
                <td>
                    {{ IngresoProveedorVisitanteSupport::etiquetaOrigen($data) }}
                    @if (IngresoProveedorVisitanteSupport::esVisitante($data))
                        <span class="badge badge-secondary">Visitante</span>
                    @endif
                </td>
                <td>{{ $data->motivos->nombre ?? '' }}</td>
                <td>{{ $data->puntos->nombre ?? '' }}</td>
                <td>{{ $data->sectores->nombre ?? '' }}</td>
                <td>{{ $data->areas->nombre ?? '' }}</td>
                <td>{{ $data->usuarios->nombre ?? '' }}</td>
                <td>
                    <span class="badge badge-{{ IngresoProveedorEstados::badge((string) $data->estado) }}">
                        {{ IngresoProveedorEstados::etiqueta((string) $data->estado) }}
                    </span>
                </td>
                <td>{{ $data->titulo }}</td>
                <td>{{ $data->comentario }}</td>
                @if ($puedeVerEditar)
                    <td>
                        @if (can('editar-ingreso-proveedor', false))
                            <a href="{{ route('editar_ingreso_proveedor', ['id' => $data->id] + $retorno) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if (can('borrar-ingreso-proveedor', false))
                            <form action="{{ route('eliminar_ingreso_proveedor', $data->id) }}" class="d-inline form-eliminar" method="POST">
                                @csrf @method("delete")
                                <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
