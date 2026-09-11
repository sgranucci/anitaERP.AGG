@php
    use App\Models\Compras\Requisicion_Estado;
    use App\Support\Compras\RequisicionListadoFiltros;

    $estadoActivo = RequisicionListadoFiltros::normalizarEstadoExterno($filtros['estado'] ?? '');
    $porEstado = $resumen['por_estado'] ?? [];
    $baseQuery = $filtrosQuery ?? [];
    $rutaIndex = $rutaIndex ?? 'consultar_requisicion';

    $urlEstado = function (string $estado) use ($baseQuery, $rutaIndex) {
        $q = $baseQuery;
        unset($q['estado'], $q['page']);
        if ($estado !== '') {
            $q['estado'] = $estado;
        }

        return route($rutaIndex, $q);
    };

    $nombreEstado = static function (string $codigo): string {
        $idx = array_search($codigo, array_column(Requisicion_Estado::$enumEstado, 'valor'), true);

        return $idx === false ? '' : (string) (Requisicion_Estado::$enumEstado[$idx]['nombre'] ?? '');
    };

    $segmentos = [
        '' => ['label' => 'Todas', 'icono' => 'fa-th-list'],
        $nombreEstado('V') => ['label' => 'Provisorio', 'icono' => 'fa-edit'],
        $nombreEstado('P') => ['label' => 'Pendientes', 'icono' => 'fa-hourglass-half'],
        $nombreEstado('K') => ['label' => 'En compras', 'icono' => 'fa-briefcase'],
        $nombreEstado('R') => ['label' => 'En árbol', 'icono' => 'fa-sitemap'],
        $nombreEstado('A') => ['label' => 'Aprobadas', 'icono' => 'fa-check'],
        $nombreEstado('O') => ['label' => 'Con OC', 'icono' => 'fa-shopping-cart'],
        $nombreEstado('C') => ['label' => 'Cumplidas', 'icono' => 'fa-check-circle'],
        $nombreEstado('S') => ['label' => 'Suspendidas', 'icono' => 'fa-pause'],
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
            <a href="{{ $urlEstado((string) $clave) }}"
               class="{{ $estadoActivo === $clave ? 'oc-activo' : '' }}"
               title="{{ $clave === '' ? 'Ver todas las requisiciones' : 'Filtrar por '.$meta['label'] }}">
                <i class="fa {{ $meta['icono'] }}"></i>
                {{ $meta['label'] }}
                @if ($conteo !== null)
                    <span class="oc-conteo">({{ number_format((int) $conteo, 0, ',', '.') }})</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
