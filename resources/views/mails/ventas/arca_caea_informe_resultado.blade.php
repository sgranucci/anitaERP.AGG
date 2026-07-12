<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado presentación CAEA</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; line-height:1.5;">
    @php
        $ok = (bool) ($resultado['ok'] ?? false);
        $erroresLote = (int) ($detalle['errores_lote'] ?? 0);
        $freno = is_array($detalle['freno'] ?? null) ? $detalle['freno'] : null;
        $falloWorker = (bool) ($detalle['fallo_worker'] ?? false);
        $color = $ok && $erroresLote === 0 ? '#1e7e34' : ($ok ? '#856404' : '#721c24');
    @endphp

    <h2 style="margin:0 0 12px 0; color:{{ $color }};">
        @if ($ok && $erroresLote === 0)
            Presentación CAEA finalizada
        @elseif ($freno)
            Presentación CAEA frenada
        @else
            Resultado presentación CAEA
        @endif
    </h2>

    @if (! empty($resultado['usuario_nombre']))
        <p style="margin:0 0 8px 0;">Hola {{ $resultado['usuario_nombre'] }},</p>
    @endif

    <p style="margin:0 0 8px 0;">
        <strong>{{ $resultado['empresa'] ?? '' }}</strong><br>
        {{ $resultado['quincena'] ?? '' }}
    </p>

    @if ($freno)
        <div style="background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:12px 14px; border-radius:4px; margin:0 0 16px 0;">
            <p style="margin:0 0 6px 0; font-weight:bold;">
                Se detuvo en {{ $freno['etiqueta'] ?? 'error ARCA' }}
            </p>
            @if (! empty($freno['codigo_error']))
                <p style="margin:0 0 6px 0;">
                    Código ARCA: <code>[{{ $freno['codigo_error'] }}]</code>
                </p>
            @endif
            <p style="margin:0; white-space:pre-wrap;">{{ $freno['mensaje'] ?? '' }}</p>
        </div>
    @endif

    @if ($falloWorker)
        <div style="background:#fff3cd; border:1px solid #ffeeba; color:#856404; padding:12px 14px; border-radius:4px; margin:0 0 16px 0;">
            <p style="margin:0; white-space:pre-wrap;">{{ $resultado['mensaje'] ?? '' }}</p>
        </div>
    @elseif (! $freno)
        <p style="white-space: pre-wrap; margin:0 0 12px 0;">{{ $resultado['mensaje'] ?? '' }}</p>
    @else
        <p style="white-space: pre-wrap; margin:0 0 12px 0; color:#555;">{{ $resultado['mensaje'] ?? '' }}</p>
    @endif

    <p style="font-size:14px; color:#555; margin:0 0 8px 0;">
        Informados en este proceso: {{ (int) ($detalle['informados'] ?? 0) }}
        · Reconocidos en ARCA: {{ (int) ($detalle['sincronizados_arca'] ?? 0) }}
        · Errores en lote: {{ $erroresLote }}
        · Pendientes: {{ (int) ($detalle['pendientes_restantes'] ?? 0) }}
        · Errores acumulados: {{ (int) ($detalle['errores_total'] ?? 0) }}
        @if (($detalle['lotes'] ?? 0) > 0)
            · Lotes SOAP: {{ (int) $detalle['lotes'] }}
        @endif
        @if (($detalle['omitidos_hueco_numeracion'] ?? 0) > 0)
            · Omitidos por hueco: {{ (int) $detalle['omitidos_hueco_numeracion'] }}
        @endif
    </p>

    @if (! empty($detalle['ultimo_informado']))
        <p style="font-size:13px; color:#555; margin:0 0 8px 0;">
            <strong>Avance:</strong> {{ $detalle['ultimo_informado'] }}
        </p>
        @if ((int) ($detalle['con_observaciones'] ?? 0) > 0)
            <p style="font-size:12px; color:#856404; margin:0 0 8px 0;">
                {{ (int) $detalle['con_observaciones'] }} comprobante(s) quedaron con observación ARCA (aceptados con aviso; no son un freno del proceso).
            </p>
        @endif
    @endif

    @php
        $cierre = is_array($detalle['cierre_quincena'] ?? null) ? $detalle['cierre_quincena'] : null;
        $proximo = is_array($cierre['proximo'] ?? null) ? $cierre['proximo'] : null;
    @endphp
    @if ($cierre)
        <div style="background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:12px 14px; border-radius:4px; margin:0 0 16px 0;">
            <p style="margin:0 0 6px 0; font-weight:bold;">
                {{ $cierre['mensaje'] ?? 'No queda nada por presentar en esta quincena.' }}
            </p>
            @if ($proximo)
                <p style="margin:0;">
                    Próximo comprobante a informar (siguiente quincena / correlativo):
                    <strong>{{ $proximo['texto'] ?? '' }}</strong>
                </p>
            @else
                <p style="margin:0;">
                    No hay un comprobante posterior con CAEA cargado en ERP todavía.
                </p>
            @endif
        </div>
    @endif

    @if (! empty($detalle['errores_muestra']) && is_array($detalle['errores_muestra']))
        <p style="margin:0 0 6px 0;"><strong>Detalle del freno / errores:</strong></p>
        <ul style="margin:0 0 16px 18px; padding:0;">
            @foreach ($detalle['errores_muestra'] as $err)
                <li style="margin-bottom:6px;">
                    @if (! empty($err['pto_vta']))
                        PV {{ str_pad((string) $err['pto_vta'], 5, '0', STR_PAD_LEFT) }}
                    @endif
                    @if (! empty($err['numero']))
                        #{{ $err['numero'] }}:
                    @endif
                    @if (! empty($err['codigo']))
                        <code>[{{ $err['codigo'] }}]</code>
                    @endif
                    {{ $err['mensaje'] ?? '' }}
                </li>
            @endforeach
        </ul>
    @elseif (! empty($detalle['errores_agrupados']) && is_array($detalle['errores_agrupados']))
        <p style="margin:0 0 6px 0;"><strong>Errores ARCA (agrupados):</strong></p>
        <ul style="margin:0 0 16px 18px; padding:0;">
            @foreach ($detalle['errores_agrupados'] as $err)
                <li style="margin-bottom:4px;">
                    @if (! empty($err['codigo']))
                        [{{ $err['codigo'] }}]
                    @endif
                    {{ \Illuminate\Support\Str::limit($err['mensaje'] ?? '', 280) }}
                    ({{ (int) ($err['cantidad'] ?? 0) }} comp.)
                </li>
            @endforeach
        </ul>
    @endif

    @if (! empty($linkConsulta))
        <p style="margin: 20px 0;">
            <a href="{{ $linkConsulta }}" style="background:#007bff; color:#fff; padding:10px 18px; text-decoration:none; border-radius:4px;">
                Ver CAEA en Anita ERP
            </a>
        </p>
        <p style="font-size:12px; color:#666;">
            Si el botón no funciona, copiá este enlace:<br>
            <a href="{{ $linkConsulta }}">{{ $linkConsulta }}</a>
        </p>
    @endif

    <p style="color:#888; font-size:11px; margin-top:28px;">
        Un solo proceso en cola por quincena (no un job por factura). Correo de aviso al finalizar o al frenarse.
    </p>
</body>
</html>
