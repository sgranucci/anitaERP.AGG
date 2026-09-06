@php
    use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;

    /**
     * Las tres búsquedas del informe viejo, más «sin PDF».
     *
     * Cada chip es un link que sólo cambia el parámetro `segmento`: así la
     * búsqueda queda en la URL y se puede compartir o guardar en favoritos,
     * que era lo que la gente hacía con el informe anterior.
     */
    $segmentoActivo = $filtros['segmento'] ?? TrackingFacturasListadoFiltros::SEGMENTO_TODOS;
    $baseQuery = $filtrosQuery ?? [];

    $urlSegmento = function (string $segmento) use ($baseQuery) {
        $q = $baseQuery;
        unset($q['segmento'], $q['page']);

        if ($segmento !== TrackingFacturasListadoFiltros::SEGMENTO_TODOS) {
            $q['segmento'] = $segmento;
        }

        // El chip de fechas de carga sólo tiene sentido sobre ese eje.
        if ($segmento === TrackingFacturasListadoFiltros::SEGMENTO_CARGADOS_ENTRE_FECHAS) {
            $q['eje_fecha'] = TrackingFacturasListadoFiltros::EJE_FECHA_CARGA;
        }

        return route('tracking_facturas', $q);
    };

    $conteos = [
        TrackingFacturasListadoFiltros::SEGMENTO_SIN_CONTABILIZAR => $resumen['sin_contabilizar'] ?? null,
        TrackingFacturasListadoFiltros::SEGMENTO_SIN_PAGAR => $resumen['con_deuda'] ?? null,
        TrackingFacturasListadoFiltros::SEGMENTO_SIN_PDF => $resumen['sin_pdf'] ?? null,
        TrackingFacturasListadoFiltros::SEGMENTO_DEUDA_ANTIGUA => $resumen['deuda_90_mas'] ?? null,
    ];
@endphp
<div class="tf-toolbar">
    <div class="tf-seg" role="group" aria-label="Búsquedas del tracking">
        @foreach ($segmentos as $clave => $meta)
            @php $conteo = $conteos[$clave] ?? null; @endphp
            <a href="{{ $urlSegmento($clave) }}"
               class="{{ $segmentoActivo === $clave ? 'tf-activo' : '' }}"
               title="{{ $meta['ayuda'] }}">
                <i class="fa {{ $meta['icono'] }}"></i>
                {{ $meta['label'] }}
                @if ($conteo !== null && $segmentoActivo === TrackingFacturasListadoFiltros::SEGMENTO_TODOS)
                    <span class="tf-conteo">({{ number_format((int) $conteo, 0, ',', '.') }})</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
