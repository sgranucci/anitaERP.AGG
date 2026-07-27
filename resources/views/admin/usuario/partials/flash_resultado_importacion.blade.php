@if (session('usuario_import_resultado'))
    @php
        $r = session('usuario_import_resultado');
        $logins = $r['logins_creados'] ?? [];
        $loginsMuestra = array_slice($logins, 0, 20);
        $loginsRestantes = max(0, count($logins) - count($loginsMuestra));
        $errores = $r['errores'] ?? [];
        $erroresMuestra = array_slice($errores, 0, 15);
        $erroresRestantes = max(0, count($errores) - count($erroresMuestra));
    @endphp
    <div id="banner-resultado-import-usuario" class="alert alert-warning alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4 class="alert-heading mb-2"><i class="fa fa-check-circle"></i> Resultado de la importación</h4>
        <p class="mb-2">
            Se crearon <strong>{{ (int) ($r['usuarios_creados'] ?? 0) }}</strong> usuarios.
        </p>
        <ul class="mb-2 small">
            <li>Filas del Excel con datos: {{ (int) ($r['filas_leidas'] ?? 0) }}</li>
            <li>Omitidas: {{ (int) ($r['filas_omitidas'] ?? 0) }}</li>
            @if (! empty($r['logins_generados']) || ! empty($r['emails_generados']))
                <li>Generados automáticamente: {{ (int) ($r['logins_generados'] ?? 0) }} login(s), {{ (int) ($r['emails_generados'] ?? 0) }} email(s)</li>
            @endif
            @if (! empty($r['dominio_email']))
                <li>Dominio email: {{ $r['dominio_email'] }}</li>
            @endif
            <li>Encabezado detectado en fila {{ (int) ($r['fila_encabezado'] ?? 1) }}</li>
            @if (! empty($r['hoja_nombre']))
                <li>Hoja importada: {{ (int) ($r['hoja_indice'] ?? 1) }} — {{ $r['hoja_nombre'] }}</li>
            @endif
        </ul>
        @if ($loginsMuestra !== [])
            <p class="mb-1 small"><strong>Logins creados</strong> (muestra):</p>
            <p class="mb-2 small"><code>{{ implode(', ', $loginsMuestra) }}</code>
                @if ($loginsRestantes > 0)
                    … y {{ $loginsRestantes }} más.
                @endif
            </p>
        @endif
        @if ($erroresMuestra !== [])
            <p class="mb-1 small"><strong>Omitidas / errores:</strong></p>
            <ul class="mb-0 small pl-3">
                @foreach ($erroresMuestra as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            @if ($erroresRestantes > 0)
                <p class="mb-0 small text-muted">… y {{ $erroresRestantes }} más.</p>
            @endif
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
