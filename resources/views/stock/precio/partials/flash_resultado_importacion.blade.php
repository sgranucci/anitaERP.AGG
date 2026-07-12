@if (session('precio_import_resultado'))
    @php
        $r = session('precio_import_resultado');
        $skus = $r['skus_grabados'] ?? [];
        $skusMuestra = array_slice($skus, 0, 20);
        $skusRestantes = max(0, count($skus) - count($skusMuestra));
    @endphp
    <div id="banner-resultado-import-precio" class="alert alert-warning alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4 class="alert-heading mb-2"><i class="fa fa-check-circle"></i> Resultado de la importaci&oacute;n</h4>
        <p class="mb-2">
            Se grabaron <strong>{{ (int) ($r['precios_grabados'] ?? 0) }}</strong> precios
            (<strong>{{ (int) ($r['articulos_distintos'] ?? 0) }}</strong> art&iacute;culos distintos):
            <strong>{{ (int) ($r['precios_creados'] ?? 0) }}</strong> nuevos,
            <strong>{{ (int) ($r['precios_actualizados'] ?? 0) }}</strong> actualizados.
        </p>
        <ul class="mb-2 small">
            <li>Filas del Excel con datos: {{ (int) ($r['filas_leidas'] ?? 0) }}</li>
            <li>Omitidas: {{ (int) ($r['filas_omitidas'] ?? 0) }}</li>
            @if (! empty($r['filas_duplicadas']))
                <li>Filas repetidas en el archivo (mismo art&iacute;culo/lista): {{ (int) $r['filas_duplicadas'] }} — se aplic&oacute; el &uacute;ltimo valor</li>
            @endif
            <li>Encabezado detectado en fila {{ (int) ($r['fila_encabezado'] ?? 1) }}</li>
            @if (! empty($r['hoja_nombre']))
                <li>Hoja importada: {{ (int) ($r['hoja_indice'] ?? 1) }} — {{ $r['hoja_nombre'] }}</li>
            @endif
            @if (! empty($r['listaprecio_nombre']))
                <li>Lista destino: {{ $r['listaprecio_nombre'] }}</li>
            @endif
            @if (! empty($r['fechavigencia']))
                <li>Vigencia: {{ $r['fechavigencia'] }}</li>
            @endif
        </ul>
        @if ($skusMuestra !== [])
            <p class="mb-1 small"><strong>SKUs grabados</strong> (muestra):</p>
            <p class="mb-0 small"><code>{{ implode(', ', $skusMuestra) }}</code>@if ($skusRestantes > 0) &hellip; y {{ $skusRestantes }} m&aacute;s.@endif</p>
        @endif
    </div>
@endif

@if (session('mensaje-error') || session('mensaje_error') || session('error'))
    <div class="alert alert-danger alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4 class="alert-heading mb-2"><i class="fa fa-times"></i> Error en la importaci&oacute;n</h4>
        <p class="mb-0">{{ session('mensaje-error') ?? session('mensaje_error') ?? session('error') }}</p>
    </div>
@endif
