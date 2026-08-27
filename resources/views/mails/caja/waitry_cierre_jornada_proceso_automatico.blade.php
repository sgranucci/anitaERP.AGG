<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre automático Waitry</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $resumen = $informe['resumen'] ?? [];
    $hayError = (int) ($resumen['errores'] ?? 0) > 0;
@endphp

<h2 style="margin:0 0 8px 0;">Cierre automático proceso Waitry</h2>
<p style="margin:0 0 16px 0;">
    Ejecutado: <strong>{{ $informe['ejecutado_en'] ?? '—' }}</strong>
</p>

<p style="margin:0 0 12px 0;">
    Procesadas: <strong>{{ (int) ($resumen['procesadas'] ?? 0) }}</strong>
    · Omitidas / sin pendiente: <strong>{{ (int) ($resumen['omitidas'] ?? 0) }}</strong>
    · Errores: <strong style="color:{{ $hayError ? '#dc3545' : '#28a745' }};">{{ (int) ($resumen['errores'] ?? 0) }}</strong>
</p>

@foreach ($informe['empresas'] ?? [] as $empresa)
    @php
        $estado = (string) ($empresa['estado'] ?? '');
        $color = match ($estado) {
            'completado', 'reanudado' => '#28a745',
            'omitido', 'sin_pendiente' => '#6c757d',
            default => '#dc3545',
        };
        $pasos = $empresa['pasos'] ?? [];
        $resultado = $empresa['resultado'] ?? [];
    @endphp
    <div style="border:1px solid #ddd; border-radius:4px; padding:12px; margin-bottom:16px;">
        <h3 style="margin:0 0 8px 0;">
            {{ $empresa['empresa_nombre'] ?? ('Empresa '.($empresa['empresa_id'] ?? '')) }}
            <span style="color:{{ $color }}; font-size:13px;">({{ strtoupper($estado) }})</span>
        </h3>

        @if (! empty($empresa['fecha_jornada']))
            <p style="margin:0 0 8px 0;">
                Jornada: <strong>{{ $empresa['fecha_jornada'] }}</strong>
                @if (isset($empresa['porcentaje']))
                    · % aplicado: <strong>{{ $empresa['porcentaje'] }}%</strong>
                @endif
                @if (! empty($empresa['puntoventa']['codigo']))
                    · PV: <strong>{{ $empresa['puntoventa']['codigo'] }}</strong>
                    {{ $empresa['puntoventa']['nombre'] ?? '' }}
                @endif
            </p>
        @endif

        @if (! empty($empresa['mensaje']))
            <p style="margin:0 0 8px 0;">{{ $empresa['mensaje'] }}</p>
        @endif

        @if (! empty($empresa['error']))
            <p style="color:#dc3545; margin:0 0 8px 0;"><strong>Error:</strong> {{ $empresa['error'] }}</p>
        @endif

        @if (! empty($pasos['recalcular']))
            <p style="margin:4px 0;">
                <strong>Recálculo:</strong>
                {{ $pasos['recalcular']['porcentaje'] ?? 0 }}%
                (objetivo $ {{ $fmt($pasos['recalcular']['objetivo_importe'] ?? 0) }})
            </p>
        @endif

        @if (! empty($pasos['emitir_factura']))
            @php $ef = $pasos['emitir_factura']; @endphp
            <p style="margin:4px 0;">
                <strong>Facturas:</strong>
                @if (! empty($ef['omitida']))
                    sin comandas Waitry (emisión omitida)
                @else
                    {{ (int) ($ef['cantidad_facturas'] ?? count($ef['facturas'] ?? [])) }} lote(s)
                    · total $ {{ $fmt(\App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport::totalFacturasDesdeEmision($ef)) }}
                @endif
            </p>
            @if (! empty($ef['facturas']))
                <ul style="margin:4px 0 8px 20px; padding:0;">
                    @foreach ($ef['facturas'] as $fac)
                        <li>{{ $fac['factura'] ?? '—' }} — $ {{ $fmt($fac['total'] ?? 0) }}</li>
                    @endforeach
                </ul>
            @endif
        @endif

        @if (! empty($pasos['grabar_asientos']))
            @php $ga = $pasos['grabar_asientos']; @endphp
            <p style="margin:4px 0;">
                <strong>Asientos:</strong> {{ (int) ($ga['cantidad_asientos'] ?? 0) }}
                @if (! empty($ga['rendicion_anita']['nro_oper']))
                    · Rendición Anita nro. {{ $ga['rendicion_anita']['nro_oper'] }}
                @endif
            </p>
            @if (! empty($ga['asientos']))
                <ul style="margin:4px 0 8px 20px; padding:0;">
                    @foreach ($ga['asientos'] as $asi)
                        <li>{{ $asi['codigo'] ?? '' }} — {{ $asi['titulo'] ?? '' }} ({{ $asi['numeroasiento'] ?? '' }})</li>
                    @endforeach
                </ul>
            @endif
        @endif

        @if (! empty($resultado['facturas']) && empty($pasos['emitir_factura']))
            <p style="margin:4px 0;"><strong>Facturas previas:</strong></p>
            <ul style="margin:4px 0 8px 20px; padding:0;">
                @foreach ($resultado['facturas'] as $fac)
                    <li>{{ $fac['factura'] ?? '—' }} — $ {{ $fmt($fac['total'] ?? 0) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endforeach

<p style="color:#666; font-size:12px; margin-top:24px;">
    Mensaje automático de {{ config('app.name', 'anitaERP') }} — proceso cierre jornada Waitry.
</p>
</body>
</html>
