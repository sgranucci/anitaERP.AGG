@php
    $enPantalla = $enPantalla ?? true;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
@endphp
<table class="table table-sm table-bordered {{ $enPantalla ? 'table-hover' : 'data' }}" @if($enPantalla) id="tabla-paginada" @endif>
    <thead>
        <tr @if(!$enPantalla) style="background:#85C1E9" @else style="background:#85C1E9;color:#17202A" @endif>
            <th>Legajo</th>
            <th>Empleado</th>
            <th>Fecha</th>
            <th>Prenda</th>
            <th>Color</th>
            <th>Talle</th>
            <th>SKU</th>
            <th class="text-right">Cant.</th>
            <th>Vence</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($datos as $r)
            @php
                $venc = $r->vence_el ?? null;
                $vencido = $venc && \Carbon\Carbon::parse($venc)->isPast();
            @endphp
            <tr>
                <td>{{ $r->legajo }}</td>
                <td>
                    @if ($enPantalla && ($puede_ver_empleado ?? false) && $r->empleado_id)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ route('editar_empleado_sueldos', ['id' => $r->empleado_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $r->empleado_nombre }}</a>
                    @else
                        {{ $r->empleado_nombre }}
                    @endif
                </td>
                <td>{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td>
                <td>{{ $r->prenda_codigo }} - {{ $r->prenda }}</td>
                <td>{{ $r->color }}</td>
                <td>{{ $r->talle }}</td>
                <td>{{ $r->sku }}</td>
                <td class="text-right">{{ $fmt($r->cantidad) }}</td>
                <td class="{{ $enPantalla && $vencido ? 'text-danger font-weight-bold' : '' }}">{{ $venc ? \Carbon\Carbon::parse($venc)->format('d/m/Y') : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted">Sin entregas para los filtros seleccionados.</td></tr>
        @endforelse
    </tbody>
</table>
