@if (session('asiento_import_resultado'))
    @php
        $r = session('asiento_import_resultado');
    @endphp
    <div id="banner-resultado-import-asiento" class="alert alert-success alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4 class="alert-heading mb-2"><i class="fa fa-check-circle"></i> Resultado de la importación</h4>
        <p class="mb-2">
            Asiento
            @if (! empty($r['asiento_id']) && can('editar-asiento', false))
                <a class="text-primary font-weight-bold" href="{{ route('editar_asiento', ['id' => $r['asiento_id']]) }}" target="_blank" rel="noopener">
                    N° {{ $r['numeroasiento'] ?? '' }}
                </a>
            @else
                <strong>N° {{ $r['numeroasiento'] ?? '' }}</strong>
            @endif
            creado con <strong>{{ (int) ($r['movimientos'] ?? 0) }}</strong> movimiento(s).
        </p>
        <ul class="mb-2 small">
            @if (! empty($r['empresa']))
                <li>Empresa: {{ $r['empresa'] }}</li>
            @endif
            @if (! empty($r['tipoasiento']))
                <li>Tipo: {{ $r['tipoasiento'] }}</li>
            @endif
            <li>Total Debe: {{ $r['total_debe_texto'] ?? '' }} · Total Haber: {{ $r['total_haber_texto'] ?? '' }}</li>
            <li>Filas omitidas: {{ (int) ($r['filas_omitidas'] ?? 0) }}</li>
            <li>Encabezado detectado en fila {{ (int) ($r['fila_encabezado'] ?? 1) }}</li>
            @if (! empty($r['hoja_nombre']))
                <li>Hoja importada: {{ (int) ($r['hoja_indice'] ?? 1) }} — {{ $r['hoja_nombre'] }}</li>
            @endif
            @if (! empty($r['pendiente_aprobacion']))
                <li class="text-warning"><strong>Estado:</strong> pendiente de aprobación</li>
            @endif
        </ul>
        @if (! empty($r['errores_muestra']) && is_array($r['errores_muestra']))
            <p class="mb-1 small"><strong>Filas omitidas (muestra):</strong></p>
            <ul class="mb-0 small">
                @foreach ($r['errores_muestra'] as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if (session('mensaje-error') || session('mensaje_error') || session('error'))
    <div class="alert alert-danger alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4 class="alert-heading mb-2"><i class="fa fa-times"></i> Error en la importación</h4>
        <p class="mb-0">{{ session('mensaje-error') ?? session('mensaje_error') ?? session('error') }}</p>
    </div>
@endif
