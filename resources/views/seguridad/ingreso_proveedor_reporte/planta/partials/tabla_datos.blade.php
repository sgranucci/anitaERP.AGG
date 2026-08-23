@php
    $enPantalla = $en_pantalla ?? false;
    $filasVista = $filas ?? [];
    $puedeVerTicket = $enPantalla && ($puede_ver_ticket ?? false);
    $puedeVerProveedor = $enPantalla && ($puede_ver_proveedor ?? false);
    $puedeVerEmpresa = $enPantalla && ($puede_ver_empresa ?? false);
    $puedeVerUsuario = $enPantalla && ($puede_ver_usuario ?? false);
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>Ticket</th>
        <th>Fecha</th>
        <th>Empresa</th>
        <th>Origen</th>
        <th>Motivo</th>
        <th>Punto</th>
        <th>&Aacute;rea</th>
        <th>Sector</th>
        <th>Patente</th>
        <th>Persona</th>
        <th>DNI</th>
        <th>Ingreso</th>
        <th>Egreso</th>
        <th>Tiempo</th>
        <th>En planta</th>
        <th>Registr&oacute; ENTRO</th>
        <th>Registr&oacute; SALIO</th>
    </tr>
</thead>
<tbody>
@forelse ($filasVista as $fila)
    <tr>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerTicket,
                'id' => $fila['ticket_id'] ?? 0,
                'routeName' => 'editar_ingreso_proveedor',
                'texto' => $fila['ticket_id'] ?? '',
            ])
        </td>
        <td>{{ $fila['fecha'] ?? '' }}</td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerEmpresa,
                'id' => $fila['empresa_id'] ?? 0,
                'routeName' => 'editar_empresa',
                'texto' => $fila['nombreempresa'] ?? '',
            ])
        </td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerProveedor,
                'id' => $fila['proveedor_id'] ?? 0,
                'routeName' => 'editar_proveedor',
                'texto' => $fila['origen'] ?? '',
            ])
        </td>
        <td>{{ $fila['motivo'] ?? '' }}</td>
        <td>{{ $fila['punto'] ?? '' }}</td>
        <td>{{ $fila['area'] ?? '' }}</td>
        <td>{{ $fila['sector'] ?? '' }}</td>
        <td>{{ $fila['patente'] ?? '' }}</td>
        <td>{{ $fila['persona'] ?? '' }}</td>
        <td>{{ $fila['documento'] ?? '' }}</td>
        <td>{{ trim(($fila['fecha_ingreso'] ?? '').' '.($fila['hora_ingreso'] ?? '')) }}</td>
        <td>{{ trim(($fila['fecha_egreso'] ?? '').' '.($fila['hora_egreso'] ?? '')) }}</td>
        <td>{{ $fila['minutos_fmt'] ?? '' }}</td>
        <td>{{ $fila['en_planta'] ?? '' }}</td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerUsuario,
                'id' => $fila['usuario_ingreso_id'] ?? 0,
                'routeName' => 'editar_usuario',
                'texto' => $fila['usuario_ingreso'] ?? '',
            ])
        </td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerUsuario,
                'id' => $fila['usuario_egreso_id'] ?? 0,
                'routeName' => 'editar_usuario',
                'texto' => $fila['usuario_egreso'] ?? '',
            ])
        </td>
    </tr>
@empty
    <tr>
        <td colspan="17" class="text-center text-muted">No hay ingresos a planta en el per&iacute;odo.</td>
    </tr>
@endforelse
</tbody>
