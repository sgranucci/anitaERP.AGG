<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobante cierre turno bingo</title>
    @include('caja.bingo.partials.estilos_comprobante_rendicion_pdf')
</head>
<body>
    <h1>Comprobante cierre de turno bingo</h1>
    <div class="subtitulo">
        {{ $d['empresa'] }} &middot; {{ $d['turno'] }} &middot; Jornada {{ $d['fecha_jornada'] }} &middot; PC {{ $d['identificador_pc'] }}
    </div>
    <div class="muted" style="margin-bottom:10px;">
        Habilitaci&oacute;n {{ $d['habilitacion_en'] }} ({{ $d['usuario_habilitado'] }})
        &rarr; Cierre {{ $d['cierre_en'] }} ({{ $d['usuario_cierre'] }})
        &middot; N&ordm; cierre {{ $d['numero_cierre'] }}
    </div>

    <h2>Datos del cierre</h2>
    <table>
        <tr>
            <td class="lbl">Habilitaci&oacute;n</td>
            <td class="num">${{ number_format((float) $d['monto_habilitacion'], 2, ',', '.') }}</td>
            <td class="lbl">Rendici&oacute;n turno</td>
            <td class="num">${{ number_format((float) $d['monto_rendicion_turno'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Rendici&oacute;n d&iacute;a</td>
            <td class="num" colspan="3">${{ number_format((float) $d['monto_rendicion_dia'], 2, ',', '.') }}</td>
        </tr>
    </table>

    @include('caja.bingo.partials.comprobante_rendicion_cuenta', [
        'total_cartones' => $d['total_cartones'] ?? 0,
        'cant_cartones' => $d['cant_cartones'] ?? 0,
        'cartones' => $d['cartones'] ?? [],
        'conceptos' => $d['conceptos'] ?? [],
        'saldo_final' => $d['saldo_final'] ?? ($d['monto_rendicion_turno'] ?? 0),
        'redondeo' => $d['redondeo'] ?? 0,
        'sobrante_faltante' => $d['sobrante_faltante'] ?? 0,
        'vales' => $d['vales'] ?? 0,
        'deposito' => $d['deposito'] ?? 0,
    ])

    @if (($d['observacion'] ?? '') !== '')
        <h2>Observaci&oacute;n</h2>
        <p class="bloque-obs">{{ $d['observacion'] }}</p>
    @endif

    <p class="muted" style="margin-top:12px;">Generado {{ $d['generado'] }}</p>
</body>
</html>
