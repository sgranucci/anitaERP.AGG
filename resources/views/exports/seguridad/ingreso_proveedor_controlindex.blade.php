<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="11" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="11"><strong>Control de ingreso — Movimientos del día</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Empresa</th>
            <th>DNI</th>
            <th>Persona</th>
            <th>Proveedor</th>
            <th>Motivo</th>
            <th>Punto</th>
            <th>Estado</th>
            <th>Entró</th>
            <th>Salió</th>
            <th>Min</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $p)
            <tr>
                <td>{{ $p['ticket_id'] ?? '' }}</td>
                <td>{{ $p['empresa'] ?? '' }}</td>
                <td>{{ $p['documento'] ?? '' }}</td>
                <td>{{ $p['nombre'] ?? '' }}</td>
                <td>{{ $p['proveedor'] ?? '' }}</td>
                <td>{{ $p['motivo'] ?? '' }}</td>
                <td>{{ $p['punto'] ?? '' }}</td>
                <td>{{ $p['estado'] ?? '' }}</td>
                <td>{{ $p['hora_ingreso'] ?? '' }}</td>
                <td>{{ $p['hora_egreso'] ?? '' }}</td>
                <td>{{ $p['minutos_en_planta'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
