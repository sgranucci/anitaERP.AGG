<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="7" style="height:52px;"></td></tr>
    @endif
    <tr>
        <td colspan="7"><strong style="font-size:16pt;">Tablero conciliación bancaria</strong></td>
    </tr>
    <tr>
        <td colspan="7">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="7">
            Período {{ sprintf('%02d/%d', (int) ($meta['mes'] ?? 0), (int) ($meta['anio'] ?? 0)) }}
            · Cuentas {{ (int) ($resumen['cuentas'] ?? 0) }}
            · Cerradas {{ (int) ($resumen['cerradas'] ?? 0) }}
            · Aprobadas {{ (int) ($resumen['aprobadas'] ?? 0) }}
            · Pares {{ (int) ($resumen['pares'] ?? 0) }}
            · Grupos {{ (int) ($resumen['grupos'] ?? 0) }}
        </td>
    </tr>
    <thead>
        <tr>
            <th>Código</th>
            <th>Cuenta</th>
            <th>Extracto</th>
            <th>Pares</th>
            <th>Grupos</th>
            <th>Hashes C/B</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td>{{ !empty($fila['tiene_interbanking']) ? 'IB/EXT' : 'Sin extracto' }}</td>
                <td>{{ $fila['pares_mes'] }}</td>
                <td>{{ $fila['grupos_mes'] }}</td>
                <td>{{ $fila['hashes_contables'] }} / {{ $fila['hashes_banco'] }}</td>
                <td>{{ $fila['estado'] ?? 'sin_ejecucion' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
