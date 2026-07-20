@php
    use App\Support\Sueldos\SaldoVacacionesReporteConsulta;
    use App\Support\Sueldos\EmpleadoEstados;
    $verEmpleado = $puede_ver_empleado ?? false;
@endphp
<table id="tabla-saldo-vacaciones" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.82rem;">
    <thead>
        <tr>
            <th>Empresa</th>
            <th class="text-right" style="width:80px">Legajo</th>
            <th>Empleado</th>
            <th class="text-center" style="width:100px">Estado</th>
            <th class="text-center" style="width:100px">Ingreso</th>
            <th class="text-center" style="width:90px">Antig.</th>
            <th class="text-right num" style="width:100px">Devengado</th>
            <th class="text-right num" style="width:100px">Consumido</th>
            <th class="text-right num" style="width:90px">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($datas as $data)
            @php
                $saldo = (float) $data->saldo;
                $antig = SaldoVacacionesReporteConsulta::aniosAntiguedad($data);
            @endphp
            <tr>
                <td>{{ $data->empresa_nombre }}</td>
                <td class="text-right">{{ $data->legajo }}</td>
                <td>
                    @if ($verEmpleado && $data->id)
                        <a href="{{ route('editar_empleado_sueldos', ['id' => $data->id]) }}?origen=modal_consulta&vista=consulta"
                           class="text-primary" target="_blank" rel="noopener">
                            {{ $data->nombre }}
                        </a>
                    @else
                        {{ $data->nombre }}
                    @endif
                </td>
                <td class="text-center">
                    <span class="badge {{ EmpleadoEstados::badge($data->estado) }}">{{ EmpleadoEstados::label($data->estado) }}</span>
                </td>
                <td class="text-center">{{ $data->fecha_ingreso ? \Carbon\Carbon::parse($data->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
                <td class="text-center">{{ $antig }} a.</td>
                <td class="text-right num">{{ number_format((float) $data->devengado, 2, ',', '.') }}</td>
                <td class="text-right num">{{ number_format((float) $data->consumido, 2, ',', '.') }}</td>
                <td class="text-right num">
                    <strong class="{{ $saldo < 0 ? 'text-danger' : ($saldo > 0 ? 'text-success' : '') }}">
                        {{ number_format($saldo, 2, ',', '.') }}
                    </strong>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center text-muted py-3">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
