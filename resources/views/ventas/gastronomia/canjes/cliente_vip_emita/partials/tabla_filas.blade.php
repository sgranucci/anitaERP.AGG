@foreach ($filas as $fila)
    <tr>
        <td>{{ $fila['sala'] }}</td>
        <td>{{ $fila['origen'] }}</td>
        <td>{{ $fila['cuenta_wigos'] }}</td>
        <td>{{ $fila['nombre_apellido'] }}</td>
        <td>{{ $fila['alias'] }}</td>
        <td>{{ $fila['nivel_tarjeta'] }}</td>
        <td>{{ $fila['vip'] }}</td>
        <td>{{ $fila['ultima_visita'] }}</td>
    </tr>
@endforeach
