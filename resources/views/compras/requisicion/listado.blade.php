<table class="table table-bordered">
    <thead>
        <tr>
            <th>Número</th>
            <th>Fecha</th>
            <th>Empresa</th>
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
            <td>{{ $data->estado }}</td>
            <td>{{ number_format($data->monto ?? 0, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
