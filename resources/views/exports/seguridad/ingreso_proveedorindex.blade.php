@php
    use App\Support\Seguridad\IngresoProveedorEstados;
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="11" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="11"><strong>Carga de Tickets - Ingreso de Proveedores</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Proveedor / Empresa</th>
            <th>Motivo de visita</th>
            <th>Sala / Punto de ingreso</th>
            <th>Sector</th>
            <th>Área de destino</th>
            <th>Generó Usuario</th>
            <th>Estado</th>
            <th>Título</th>
            <th>Comentario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                <td>{{ \App\Support\Seguridad\IngresoProveedorVisitanteSupport::etiquetaOrigen($data) }}{{ \App\Support\Seguridad\IngresoProveedorVisitanteSupport::esVisitante($data) ? ' (Visitante)' : '' }}</td>
                <td>{{ $data->motivos->nombre ?? '' }}</td>
                <td>{{ $data->puntos->nombre ?? '' }}</td>
                <td>{{ $data->sectores->nombre ?? '' }}</td>
                <td>{{ $data->areas->nombre ?? '' }}</td>
                <td>{{ $data->usuarios->nombre ?? '' }}</td>
                <td>{{ IngresoProveedorEstados::etiqueta((string) $data->estado) }}</td>
                <td>{{ $data->titulo }}</td>
                <td>{{ $data->comentario }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
