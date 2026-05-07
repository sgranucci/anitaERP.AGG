<table class="table table-bordered">
    <thead>
        <tr>
            <th>Id</th>
            <th>Fecha</th>
            <th>Nombre</th>
            <th>Proveedor</th>
            <th>Estado</th>
            <th>Usuario alta</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($listas as $data)
        <tr>
            <td>{{ $data->id }}</td>
            <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
            <td>{{ $data->nombre }}</td>
            <td>{{ $data->nombreproveedor }}</td>
            <td>{{ $data->estado }}</td>
            <td>{{ $data->nombreusuario }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
