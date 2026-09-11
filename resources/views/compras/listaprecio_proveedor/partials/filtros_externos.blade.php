@php
    use App\Support\Compras\ListaprecioProveedorListadoFiltros;

    $estadoActivo = ListaprecioProveedorListadoFiltros::normalizarEstadoExterno($filtros['estado'] ?? '');
    $baseQuery = $filtrosQuery ?? [];
    $rutaIndex = $rutaIndex ?? 'consultar_listaprecio_proveedor';

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
        'ACTIVA' => ['label' => 'Activas', 'icono' => 'fa-check-circle'],
        'INACTIVA' => ['label' => 'Inactivas', 'icono' => 'fa-pause'],
    ];
@endphp
<div class="lp-toolbar">
    <div class="lp-seg" role="group" aria-label="Filtro por estado">
        @foreach ($segmentos as $clave => $meta)
            <a href="{{ $urlEstado($clave) }}"
               class="{{ $estadoActivo === $clave ? 'lp-activo' : '' }}"
               title="{{ $clave === '' ? 'Ver todas las listas' : 'Filtrar por '.$meta['label'] }}">
                <i class="fa {{ $meta['icono'] }}"></i>
                {{ $meta['label'] }}
            </a>
        @endforeach
    </div>
    <div class="ml-md-auto">
        @include('includes.exportar-tabla-queryparams', [
            'ruta' => 'listar_listaprecio_proveedor',
            'queryparams' => $filtrosQuery ?? [],
            'variant' => 'compact',
        ])
    </div>
</div>
