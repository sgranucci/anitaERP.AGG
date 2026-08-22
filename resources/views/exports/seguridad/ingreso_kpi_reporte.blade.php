<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="27" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="27"><strong>{{ $titulo ?? 'Reporte tickets e ingresos' }}@if(!empty($subtitulo)) — {{ $subtitulo }}@endif</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Cód. prov.</th>
            <th>OC</th>
            <th>Motivo</th>
            <th>Punto</th>
            <th>Área</th>
            <th>Sector</th>
            <th>Patente</th>
            <th>Estado</th>
            <th>Persona</th>
            <th>DNI</th>
            <th>Fecha ingreso</th>
            <th>Hora ingreso</th>
            <th>Fecha egreso</th>
            <th>Hora egreso</th>
            <th>Minutos</th>
            <th>En planta</th>
            <th>Generó</th>
            <th>Autorizó</th>
            <th>ENTRO</th>
            <th>SALIO</th>
            <th>Título</th>
            <th>Comentario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['ticket_id'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>{{ $fila['origen'] ?? '' }}</td>
                <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
                <td>{{ $fila['oc_numero'] ?? '' }}</td>
                <td>{{ $fila['motivo'] ?? '' }}</td>
                <td>{{ $fila['punto'] ?? '' }}</td>
                <td>{{ $fila['area'] ?? '' }}</td>
                <td>{{ $fila['sector'] ?? '' }}</td>
                <td>{{ $fila['patente'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td>{{ $fila['persona'] ?? '' }}</td>
                <td>{{ $fila['documento'] ?? '' }}</td>
                <td>{{ $fila['fecha_ingreso'] ?? '' }}</td>
                <td>{{ $fila['hora_ingreso'] ?? '' }}</td>
                <td>{{ $fila['fecha_egreso'] ?? '' }}</td>
                <td>{{ $fila['hora_egreso'] ?? '' }}</td>
                <td>{{ $fila['minutos'] ?? '' }}</td>
                <td>{{ $fila['en_planta'] ?? '' }}</td>
                <td>{{ $fila['usuario_genero'] ?? '' }}</td>
                <td>{{ $fila['usuario_autorizo'] ?? '' }}</td>
                <td>{{ $fila['usuario_ingreso'] ?? '' }}</td>
                <td>{{ $fila['usuario_egreso'] ?? '' }}</td>
                <td>{{ $fila['titulo'] ?? '' }}</td>
                <td>{{ $fila['comentario'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
