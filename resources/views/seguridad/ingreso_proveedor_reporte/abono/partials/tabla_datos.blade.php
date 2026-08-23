@php
    $enPantalla = $en_pantalla ?? false;
    $filasVista = $filas ?? [];
    $puedeVerOc = $enPantalla && ($puede_ver_oc ?? false);
    $puedeVerProveedor = $enPantalla && ($puede_ver_proveedor ?? false);
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>Proveedor</th>
        <th>C&oacute;digo</th>
        <th>Contrato / Abono</th>
        <th>Empresa</th>
        <th>Estado OC</th>
        <th>Vigencia</th>
        <th>M&iacute;n. ingresos</th>
        <th>Tickets Finalizado</th>
        <th>Estado</th>
    </tr>
</thead>
<tbody>
@forelse ($filasVista as $fila)
    <tr @if ($enPantalla && ($fila['resultado_codigo'] ?? '') === 'REVISAR') class="ingreso-reporte-rechazado" @endif>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerProveedor,
                'id' => $fila['proveedor_id'] ?? 0,
                'routeName' => 'editar_proveedor',
                'texto' => $fila['proveedor'] ?? '',
            ])
        </td>
        <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
        <td>
            @include('presupuesto.partials.celda_link_consulta', [
                'mostrarLinks' => $enPantalla,
                'puede' => $puedeVerOc,
                'id' => $fila['oc_id'] ?? 0,
                'routeName' => 'editar_ordencompra',
                'texto' => $fila['oc_numero'] ?? '',
            ])
        </td>
        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
        <td>{{ $fila['estado_oc'] ?? '' }}</td>
        <td>{{ trim(($fila['vigencia_desde'] ?? '').' — '.($fila['vigencia_hasta'] ?? ''), ' —') }}</td>
        <td>{{ $fila['minimo'] ?? 0 }}</td>
        <td>{{ $fila['tickets_finalizados'] ?? 0 }}</td>
        <td>{{ $fila['resultado'] ?? '' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="9" class="text-center text-muted">Sin contratos en el per&iacute;odo.</td>
    </tr>
@endforelse
</tbody>
