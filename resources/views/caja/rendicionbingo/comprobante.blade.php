@php
    $cartones = is_array($rendicion->cartones_json) ? $rendicion->cartones_json : [];
    $conceptos = is_array($rendicion->conceptos_json) ? $rendicion->conceptos_json : [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rendici&oacute;n bingo {{ $rendicion->codigo }}</title>
    @include('caja.bingo.partials.estilos_comprobante_rendicion_pdf')
</head>
<body>
    <h1>Rendici&oacute;n bingo &mdash; presentaci&oacute;n en caja</h1>
    <div class="subtitulo">
        C&oacute;digo operaci&oacute;n: <strong>{{ $rendicion->codigo }}</strong>
        &middot; Empresa: {{ $rendicion->empresa?->nombre }}
        &middot; Jornada: {{ optional($rendicion->fecha_jornada)->format('d/m/Y') }}
        &middot; Turno: {{ $rendicion->turnoOperativo?->turno?->nombre }}
        &middot; Terminal: {{ $rendicion->turnoOperativo?->identificador_pc }}
        &middot; Operador: {{ $rendicion->turnoOperativo?->usuarioHabilitado?->nombre }}
    </div>
    <div class="muted" style="margin-bottom:10px;">
        Registro en caja: {{ optional($rendicion->fecharendicion)->format('d/m/Y H:i') }}
        &middot; Generado: {{ now()->format('d/m/Y H:i') }}
        @if ($rendicion->creousuario?->nombre)
            &middot; Registr&oacute;: {{ $rendicion->creousuario->nombre }}
        @endif
    </div>

    @include('caja.bingo.partials.comprobante_rendicion_cuenta', [
        'total_cartones' => $rendicion->total_cartones,
        'cant_cartones' => $rendicion->cant_cartones,
        'cartones' => $cartones,
        'conceptos' => $conceptos,
        'saldo_final' => $rendicion->saldo_final,
        'redondeo' => $rendicion->redondeo,
        'sobrante_faltante' => $rendicion->sobrante_faltante,
        'vales' => $rendicion->vales,
        'deposito' => $rendicion->deposito,
    ])

    @if ($rendicion->observacion)
        <h2>Observaci&oacute;n</h2>
        <p class="bloque-obs">{{ $rendicion->observacion }}</p>
    @endif
</body>
</html>
