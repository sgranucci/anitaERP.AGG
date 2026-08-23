@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $enPantalla = $en_pantalla ?? false;
    $filasVista = $filas ?? [];
    $puedeVerTicket = $enPantalla && ($puede_ver_ticket ?? false);
    $puedeVerOc = $enPantalla && ($puede_ver_oc ?? false);
    $puedeVerProveedor = $enPantalla && ($puede_ver_proveedor ?? false);
    $puedeVerEmpresa = $enPantalla && ($puede_ver_empresa ?? false);
    $puedeVerUsuario = $enPantalla && ($puede_ver_usuario ?? false);
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
    @php
        $esRechazado = $enPantalla && (($fila['estado_codigo'] ?? '') === IngresoProveedorEstados::RECHAZADO);
    @endphp
    <tr @if ($esRechazado) class="ingreso-reporte-rechazado" @endif>
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
        <td>{{ $fila['tipo'] ?? '' }}</td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerProveedor,
                'id' => $fila['proveedor_id'] ?? 0,
                'routeName' => 'editar_proveedor',
                'texto' => $fila['origen'] ?? '',
            ])
        </td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerProveedor,
                'id' => $fila['proveedor_id'] ?? 0,
                'routeName' => 'editar_proveedor',
                'texto' => $fila['proveedor_codigo'] ?? '',
            ])
        </td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerOc,
                'id' => $fila['oc_id'] ?? 0,
                'routeName' => 'editar_ordencompra',
                'texto' => $fila['oc_numero'] ?? '',
            ])
        </td>
        <td>{{ $fila['motivo'] ?? '' }}</td>
        <td>{{ $fila['punto'] ?? '' }}</td>
        <td>{{ $fila['area'] ?? '' }}</td>
        <td>{{ $fila['sector'] ?? '' }}</td>
        <td>{{ $fila['patente'] ?? '' }}</td>
        <td>
            @if ($enPantalla && !empty($fila['estado_codigo']))
                <span class="badge badge-{{ IngresoProveedorEstados::badge((string) $fila['estado_codigo']) }}">
                    {{ $fila['estado'] ?? '' }}
                </span>
            @else
                {{ $fila['estado'] ?? '' }}
            @endif
        </td>
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
                'id' => $fila['usuario_id'] ?? 0,
                'routeName' => 'editar_usuario',
                'texto' => $fila['usuario_genero'] ?? '',
            ])
        </td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerUsuario,
                'id' => $fila['usuario_autorizo_id'] ?? 0,
                'routeName' => 'editar_usuario',
                'texto' => $fila['usuario_autorizo'] ?? '',
            ])
        </td>
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
        <td>{{ $fila['titulo'] ?? '' }}</td>
        <td>{{ $fila['comentario'] ?? '' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="25" class="text-center text-muted">Sin movimientos en el per&iacute;odo.</td>
    </tr>
@endforelse
</tbody>
