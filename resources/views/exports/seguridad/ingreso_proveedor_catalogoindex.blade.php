<table>
    <tr>
        <td colspan="4"><strong>{{ $titulo }}</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Activo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->codigo }}</td>
                <td>{{ $data->nombre }}</td>
                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
