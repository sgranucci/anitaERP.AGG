<table>
    <thead>
        <tr>
            <th>Número</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Centro costo</th>
            <th>Estado</th>
            <th>Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($requisicion as $data)
        <tr>
            <td>{{ $data->numerorequisicion }}</td>
            <td>{{ date('d/m/Y', strtotime($data->fecha)) }}</td>
            <td>{{ $data->nombreempresa }}</td>
            <td>{{ $data->nombrecentrocosto }}</td>
            <td>{{ $data->estado }}</td>
            <td>{{ $data->monto }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
