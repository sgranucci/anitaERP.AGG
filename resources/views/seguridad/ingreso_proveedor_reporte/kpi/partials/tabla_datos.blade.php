@php
    $puedeVer = $puede_ver_ticket ?? false;
    $filasVista = $filas ?? [];
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>Ticket</th>
        <th>Fecha</th>
        <th>Empresa</th>
        <th>Tipo</th>
        <th>Origen</th>
        <th>C&oacute;d. prov.</th>
        <th>OC</th>
        <th>Motivo</th>
        <th>Punto</th>
        <th>&Aacute;rea</th>
        <th>Sector</th>
        <th>Patente</th>
        <th>Estado</th>
        <th>Persona</th>
        <th>DNI</th>
        <th>Ingreso</th>
        <th>Egreso</th>
        <th>Tiempo</th>
        <th>En planta</th>
        <th>Gener&oacute;</th>
        <th>Autoriz&oacute;</th>
        <th>ENTRO</th>
        <th>SALIO</th>
        <th>T&iacute;tulo</th>
        <th>Comentario</th>
    </tr>
</thead>
<tbody>
@forelse ($filasVista as $fila)
    <tr>
        <td>
            @if ($puedeVer && !empty($fila['ticket_id']))
                <a href="{{ route('editar_ingreso_proveedor', ['id' => $fila['ticket_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="text-primary" target="_blank" rel="noopener">{{ $fila['ticket_id'] }}</a>
            @else
                {{ $fila['ticket_id'] ?? '' }}
            @endif
        </td>
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
        <td>{{ trim(($fila['fecha_ingreso'] ?? '').' '.($fila['hora_ingreso'] ?? '')) }}</td>
        <td>{{ trim(($fila['fecha_egreso'] ?? '').' '.($fila['hora_egreso'] ?? '')) }}</td>
        <td>{{ $fila['minutos_fmt'] ?? '' }}</td>
        <td>{{ $fila['en_planta'] ?? '' }}</td>
        <td>{{ $fila['usuario_genero'] ?? '' }}</td>
        <td>{{ $fila['usuario_autorizo'] ?? '' }}</td>
        <td>{{ $fila['usuario_ingreso'] ?? '' }}</td>
        <td>{{ $fila['usuario_egreso'] ?? '' }}</td>
        <td>{{ $fila['titulo'] ?? '' }}</td>
        <td>{{ $fila['comentario'] ?? '' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="25" class="text-center text-muted">Sin movimientos en el per&iacute;odo.</td>
    </tr>
@endforelse
</tbody>
