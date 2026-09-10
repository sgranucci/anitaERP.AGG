@php
    use App\Support\Compras\OrdencompraEstados;
    use App\Support\Compras\OrdencompraListadoFiltros;

    $estadoActivo = OrdencompraListadoFiltros::normalizarEstadoExterno($filtros['estado'] ?? '');
    $porEstado = $resumen['por_estado'] ?? [];
    $baseQuery = $filtrosQuery ?? [];
    $rutaIndex = $rutaIndex ?? 'consultar_ordencompra';

    $urlEstado = function (string $estado) use ($baseQuery, $rutaIndex) {
        $q = $baseQuery;
        unset($q['estado'], $q['page']);
        if ($estado !== '') {
            $q['estado'] = $estado;
        }

        return route($rutaIndex, $q);
    };

    $segmentos = [
        '' => ['label' => 'Todas', 'icono' => 'fa-th-list'],
        OrdencompraEstados::PENDIENTE => ['label' => 'Pendientes', 'icono' => 'fa-hourglass-half'],
        OrdencompraEstados::APROBADA => ['label' => 'Aprobadas', 'icono' => 'fa-check'],
        OrdencompraEstados::CUMPLIDA => ['label' => 'Cumplidas', 'icono' => 'fa-check-circle'],
        OrdencompraEstados::SUSPENDIDA => ['label' => 'Suspendidas', 'icono' => 'fa-pause'],
        OrdencompraEstados::CERRADA => ['label' => 'Cerradas', 'icono' => 'fa-lock'],
    ];
@endphp
<div class="oc-toolbar">
    <div class="oc-seg" role="group" aria-label="Filtro por estado">
        @foreach ($segmentos as $clave => $meta)
            @php
                $conteo = $clave === ''
                    ? ($resumen['total'] ?? null)
                    : ($porEstado[$clave] ?? 0);
            @endphp
            <a href="{{ $urlEstado($clave) }}"
               class="{{ $estadoActivo === $clave ? 'oc-activo' : '' }}"
               title="{{ $clave === '' ? 'Ver todas las órdenes' : 'Filtrar por '.$meta['label'] }}">
                <i class="fa {{ $meta['icono'] }}"></i>
                {{ $meta['label'] }}
                @if ($conteo !== null)
                    <span class="oc-conteo">({{ number_format((int) $conteo, 0, ',', '.') }})</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
