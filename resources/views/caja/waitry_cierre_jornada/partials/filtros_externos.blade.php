@php
    $empresasBar = $empresa_query ?? $empresas ?? collect();
    $empresaActual = (int) ($empresa_id ?? 0);
    $fecha = (string) ($fecha_jornada ?? '');

    $urlEmpresa = function ($id) use ($fecha) {
        $q = ['empresa_id' => $id];
        if ($fecha !== '') {
            $q['fecha_jornada'] = $fecha;
        }

        return route('waitry_cierre_jornada', $q);
    };
@endphp
@if ($empresasBar->count() > 1)
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresasBar as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       class="btn {{ $empresaActual === (int) $emp->id ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $emp->nombre }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif
