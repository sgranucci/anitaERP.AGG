<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'a4 portrait',
            'pdf_margin' => '12mm 14mm',
        ])
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17202A; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 12px; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85C1E9; color: #17202A; padding: 3px 4px; border: 1px solid #ccc; text-align: left; }
        td { padding: 3px 4px; border: 1px solid #ccc; }
        .sin-borde td { border: none; padding: 1px 4px; }
        .right { text-align: right; }
        .logo { max-height: 64px; max-width: 200px; }
        .muted { color: #555; font-size: 9px; }
    </style>
</head>
<body>
@php
    use App\Support\Caja\TesoreriaCrownBovedaSupport;
    use App\Support\Caja\TesoreriaCrownNumeradorSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($movimiento->empresa->nombre ?? null);
    $numero = TesoreriaCrownNumeradorSupport::visible((int) $movimiento->numero);
    $hora = $movimiento->created_at?->format('H:i') ?? (string) $movimiento->hora;
@endphp
<table class="marco-pdf"><tr>
    <td class="marco-lat"></td>
    <td class="marco-centro">
        <table class="sin-borde">
            <tr>
                <td style="width:30%;">
                    @if (! empty($logo['uri']))
                        <img src="{{ $logo['uri'] }}" class="logo" alt="">
                    @endif
                </td>
                <td style="text-align:right;">
                    <h1>Movimiento de bóveda {{ $numero }}</h1>
                    <div class="muted">{{ $movimiento->empresa->nombre ?? '' }}</div>
                    <div class="muted">Generado {{ now()->format('d/m/Y H:i') }}</div>
                    @if (! empty($cambiosDia))
                        <div class="muted">
                            Cotización
                            @foreach ($cambiosDia as $cambio)
                                @if (! $loop->first)
                                    ·
                                @endif
                                {{ $cambio['codigo'] }}
                                @if ($cambio['tasa'] > 0)
                                    @php
                                        $decimalesCambio = abs($cambio['tasa'] - round($cambio['tasa'], 2)) < 0.0000001 ? 2 : 4;
                                    @endphp
                                    {{ number_format($cambio['tasa'], $decimalesCambio, ',', '.') }}
                                    @if (! $cambio['exacta'] && $cambio['fecha'])
                                        ({{ \Carbon\Carbon::parse($cambio['fecha'])->format('d/m') }})
                                    @endif
                                @else
                                    sin cambio
                                @endif
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
        </table>
        <table class="sin-borde" style="margin-top:8px;">
            <tr>
                <td>Sala: {{ $movimiento->sala->codigo ?? '' }} {{ $movimiento->sala->nombre ?? '' }}</td>
                <td>Fecha: {{ $movimiento->jornada->fecha->format('d/m/Y') }}</td>
                <td>Hora: {{ $hora }}</td>
            </tr>
            <tr>
                <td>Lugar: {{ $movimiento->deposito->codigo ?? '' }} {{ $movimiento->deposito->nombre ?? '' }}</td>
                <td>Transacción: {{ $movimiento->tipo->codigo ?? '' }} {{ $movimiento->tipo->abreviatura ?? '' }} — {{ $movimiento->tipo->nombre ?? '' }}</td>
                <td>Efecto: {{ TesoreriaCrownBovedaSupport::efectoTexto((string) ($movimiento->tipo->signo ?? '')) }}</td>
            </tr>
            <tr>
                <td>Estado: {{ TesoreriaCrownBovedaSupport::estado((string) $movimiento->estado) }}</td>
                <td colspan="2">
                    @if ($empleado)
                        Legajo: {{ $empleado->legajo }} — {{ $empleado->nombre }}
                    @elseif ($cliente)
                        Cliente: {{ $cliente->codigo }} — {{ $cliente->nombre }}
                    @endif
                </td>
            </tr>
            @if ($movimiento->revierte)
                <tr>
                    <td colspan="3">Reversa del movimiento {{ TesoreriaCrownNumeradorSupport::visible((int) $movimiento->revierte->numero) }}</td>
                </tr>
            @endif
            @if (trim((string) $movimiento->motivo) !== '')
                <tr>
                    <td colspan="3">Motivo: {{ $movimiento->motivo }}</td>
                </tr>
            @endif
            @if (trim((string) $movimiento->comentario) !== '')
                <tr>
                    <td colspan="3">Comentario: {{ $movimiento->comentario }}</td>
                </tr>
            @endif
        </table>
        @foreach ($grupos as $grupo)
            @php $total = 0.0; @endphp
            <h2>{{ $grupo['titulo'] }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Denominación</th>
                        <th>Tipo</th>
                        <th class="right">Cantidad</th>
                        <th class="right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo['filas'] as $linea)
                        @php
                            $cantidad = (float) $linea->cantidad;
                            $importe = $cantidad * (float) $linea->valor->denominacion;
                            $total += $importe;
                            $denominacion = (float) $linea->valor->denominacion;
                            $denominacionTexto = fmod($denominacion, 1.0) === 0.0
                                ? number_format($denominacion, 0, ',', '.')
                                : number_format($denominacion, 2, ',', '.');
                        @endphp
                        <tr>
                            <td class="right">{{ $denominacionTexto }}</td>
                            <td>{{ $linea->valor->descripcion }}</td>
                            <td class="right">{{ number_format($cantidad, 0, ',', '.') }}</td>
                            <td class="right">{{ number_format($importe, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th colspan="3">Total {{ $grupo['titulo'] }}</th>
                        <th class="right">{{ number_format($total, 2, ',', '.') }}</th>
                    </tr>
                </tbody>
            </table>
        @endforeach
        @if (! empty($efectivoPesos))
            <h2>Total en pesos</h2>
            <p class="muted">Pesos al nominal. Dólares y euros al cambio de venta de la jornada {{ $movimiento->jornada->fecha->format('d/m/Y') }}.</p>
            <table>
                <tbody>
                    @foreach ($efectivoPesos['monedas'] as $monedaLinea)
                        @php
                            $detalle = $monedaLinea['titulo'];
                            if ($monedaLinea['extranjera'] && ! $monedaLinea['falta']) {
                                $tasaLinea = (float) $monedaLinea['tasa'];
                                $decimalesLinea = abs($tasaLinea - round($tasaLinea, 2)) < 0.0000001 ? 2 : 4;
                                $detalle .= ' '.number_format($monedaLinea['origen'], 2, ',', '.').' × '.number_format($tasaLinea, $decimalesLinea, ',', '.');
                                if ($monedaLinea['fecha'] !== ($fechaJornada ?? null) && $monedaLinea['fecha']) {
                                    $detalle .= ' ('.\Carbon\Carbon::parse($monedaLinea['fecha'])->format('d/m/Y').')';
                                }
                            } elseif ($monedaLinea['falta']) {
                                $detalle .= ' — sin cotización de venta';
                            }
                        @endphp
                        <tr>
                            <td>{{ $detalle }}</td>
                            <td class="right">{{ $monedaLinea['falta'] ? '—' : number_format($monedaLinea['pesos'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>Total final $</th>
                        <th class="right">{{ number_format((float) $efectivoPesos['total'], 2, ',', '.') }}</th>
                    </tr>
                </tbody>
            </table>
            @if (! $efectivoPesos['completo'])
                <p class="muted">Falta la cotización de una moneda extranjera. Esa moneda no entra en el total.</p>
            @endif
        @endif
        @if ($movimiento->usuario)
            <p class="muted" style="margin-top:14px;">
                Confirmó {{ $movimiento->usuario->nombre ?? $movimiento->usuario->usuario }}
                @if ($movimiento->confirmado_at)
                    · {{ $movimiento->confirmado_at->format('d/m/Y H:i') }}
                @endif
            </p>
        @endif
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
