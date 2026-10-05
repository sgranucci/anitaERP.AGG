<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rendici&oacute;n m&aacute;quinas {{ $rendicion->codigo }}</title>
    @include('caja.rendiciongastronomia.partials.estilos_comprobante_pdf')
    <style>
        .tabla-cuenta thead th { background: #85C1E9; color: #17202A; }
        .fila-total td { background: #e8f4fc; font-weight: bold; }
        .totales-box { margin-top: 4px; }
        .totales-box td.lbl { width: 45%; background: #f0f0f0; font-weight: bold; }
        .dos-cols { width: 100%; border: none !important; margin-bottom: 8px; }
        .dos-cols > tbody > tr > td { border: none !important; vertical-align: top; width: 50%; padding: 0 4px 0 0; }
        .dos-cols > tbody > tr > td + td { padding: 0 0 0 4px; }
    </style>
</head>
<body>
@php
    use App\Support\Caja\RendicionMaquina\RendicionMaquinaComprobanteDatos;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $datos = RendicionMaquinaComprobanteDatos::armar($rendicion);
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($rendicion->empresa?->nombre);
@endphp

<table class="cabecera-doc">
    <tr>
        <td style="width: 35%;">
            @if (! empty($logo['uri']))
                <img src="{{ $logo['uri'] }}" alt="Logo" class="logo">
            @endif
        </td>
        <td style="width: 65%; text-align: right;">
            <h1>Rendici&oacute;n de m&aacute;quinas</h1>
            <div class="subtitulo">
                C&oacute;digo: <strong>{{ $rendicion->codigo }}</strong>
                @if ($rendicion->nro_oper_anita)
                    @if (config('rendicion_maquina_anita.sincronizar'))
                        &middot; Nro. Anita: {{ $rendicion->nro_oper_anita }}
                    @else
                        &middot; Nro.: {{ $rendicion->nro_oper_anita }}
                    @endif
                @endif
            </div>
            <div class="muted">PDF generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table>
    <tr>
        <td class="lbl">Empresa</td>
        <td>{{ $rendicion->empresa?->nombre }}</td>
        <td class="lbl">Fecha</td>
        <td>{{ optional($rendicion->fecha)->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="lbl">Turno</td>
        <td>{{ $rendicion->turno_label }} ({{ $rendicion->turno }})</td>
        <td class="lbl">Estado</td>
        <td>{{ $rendicion->estado_label }}</td>
    </tr>
    <tr>
        <td class="lbl">Supervisor</td>
        <td>{{ $rendicion->supervisorUsuario?->nombre ?: '—' }}</td>
        <td class="lbl">Cajero</td>
        <td>{{ $rendicion->cajeroUsuario?->nombre ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Auxiliar</td>
        <td>{{ $rendicion->auxiliarUsuario?->nombre ?: '—' }}</td>
        <td class="lbl">Registr&oacute;</td>
        <td>{{ $rendicion->creoUsuario?->nombre ?: '—' }}</td>
    </tr>
</table>

<table class="dos-cols">
    <tr>
        <td>
            <h2>Totales de cierre</h2>
            <table class="totales-box">
                @foreach ($datos['totales'] as $total)
                    <tr class="{{ ! empty($total['destacar']) ? 'fila-total' : '' }}">
                        <td class="lbl">{{ $total['etiqueta'] }}</td>
                        <td class="num">{{ $fmt($total['valor']) }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
        <td>
            <h2>Datos principales</h2>
            <table class="tabla-cuenta">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($datos['principales'] as $fila)
                        <tr>
                            <td>{{ $fila['etiqueta'] }}</td>
                            <td class="num">{{ $fmt($fila['valor']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

<h2>Valores (cuentas de caja)</h2>
<table class="tabla-cuenta">
    <thead>
        <tr>
            <th style="width:12%">C&oacute;digo</th>
            <th>Cuenta</th>
            <th class="num" style="width:20%">Monto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($datos['valores'] as $valor)
            <tr>
                <td>{{ $valor['codigo'] }}</td>
                <td>{{ $valor['cuenta'] }}</td>
                <td class="num">{{ $fmt($valor['monto']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin valores cargados.</td></tr>
        @endforelse
        <tr class="fila-total">
            <td colspan="2">Total valores</td>
            <td class="num">{{ $fmt($datos['total_valores']) }}</td>
        </tr>
    </tbody>
</table>

<h2>Apertura de gastos</h2>
<table class="tabla-cuenta">
    <thead>
        <tr>
            <th style="width:12%">C&oacute;digo</th>
            <th>Concepto</th>
            <th class="num" style="width:20%">Monto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($datos['gastos'] as $gasto)
            <tr>
                <td>{{ $gasto['codigo'] }}</td>
                <td>{{ $gasto['concepto'] }}</td>
                <td class="num">{{ $fmt($gasto['monto']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin gastos.</td></tr>
        @endforelse
        <tr class="fila-total">
            <td colspan="2">Total gastos</td>
            <td class="num">{{ $fmt($datos['total_gastos']) }}</td>
        </tr>
    </tbody>
</table>

@if ($datos['observacion'] !== '')
    <h2>Observaci&oacute;n</h2>
    <p class="bloque-obs">{{ $datos['observacion'] }}</p>
@endif
</body>
</html>
