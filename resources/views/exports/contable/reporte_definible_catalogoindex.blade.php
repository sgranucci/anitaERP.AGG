<table>
    <tr>
        <td colspan="7"><strong style="font-size:16px;">Reportes contables definibles</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Título 1</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Activo</th>
            <th>Rubros</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $item)
            <tr>
                <td>{{ $item->codigo }}</td>
                <td>{{ $item->nombre }}</td>
                <td>{{ $item->titulo1 }}</td>
                <td>{{ $item->tipo }}</td>
                <td>{{ $item->origen }}</td>
                <td>{{ $item->activo ? 'Sí' : 'No' }}</td>
                <td>{{ $item->rubros_count ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
