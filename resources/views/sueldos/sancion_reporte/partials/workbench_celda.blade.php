@php
    $alineado = in_array($key, ['cant_dias', 'importe_perdida', 'legajo', 'nro_interno'], true);
@endphp
<td class="{{ $alineado ? 'text-right text-nowrap' : '' }}">
    @if ($key === 'legajo' && can('editar-empleado-sueldos', false) && ! empty($data->empleado_id))
        <a class="text-primary" target="_blank" rel="noopener"
           href="{{ route('editar_empleado_sueldos', ['id' => $data->empleado_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
            {{ \App\Support\Sueldos\SancionReporteListadoColumnas::valorCelda($data, $key) }}
        </a>
    @else
        {{ \App\Support\Sueldos\SancionReporteListadoColumnas::valorCelda($data, $key) }}
    @endif
</td>
