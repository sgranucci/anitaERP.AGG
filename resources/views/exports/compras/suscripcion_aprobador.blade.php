<table>
    <thead>
        <tr>
            <th colspan="5">Aprobadores de suscripciones — {{ now()->format('d/m/Y H:i') }}</th>
        </tr>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Centro de costo</th>
            <th>Gerente</th>
            <th>Suscripciones</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['id'] }}</td>
                <td>{{ $fila['empresa'] }}</td>
                <td>{{ trim(($fila['codigo'] ?? '').' '.($fila['nombre'] ?? '')) }}</td>
                <td>{{ $fila['usuario_nombre'] }}{{ $fila['usuario_codigo'] ? ' ('.$fila['usuario_codigo'].')' : '' }}</td>
                <td>{{ $fila['suscripciones'] ?: 0 }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
