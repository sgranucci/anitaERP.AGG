@php
    $esMotivo = ($tipo ?? '') === 'motivo';
    $columnas = $esMotivo ? 5 : 4;
@endphp
<table>
    <tr>
        <td colspan="{{ $columnas }}"><strong>{{ $titulo }}</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Nombre</th>
            @if ($esMotivo)
                <th>Exige OC</th>
            @endif
            <th>Activo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->codigo }}</td>
                <td>{{ $data->nombre }}</td>
                @if ($esMotivo)
                    <td>{{ $data->exige_oc ? 'Sí' : 'No' }}</td>
                @endif
                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
