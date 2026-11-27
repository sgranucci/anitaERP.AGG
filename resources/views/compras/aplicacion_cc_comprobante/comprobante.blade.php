<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $comp->numeroFormateado() }}</title>
    <style>
        @page { margin: 14mm 12mm 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #0d3b66; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        th, td { border: 1px solid #b0b0b0; padding: 3px 4px; vertical-align: top; }
        th { background: #85C1E9; color: #17202A; font-weight: bold; text-align: left; font-size: 8px; }
        .no-border td { border: none !important; padding: 1px 3px; }
        .lbl { background: #f4f6f7; font-weight: bold; white-space: nowrap; width: 18%; }
        .muted { color: #555; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }
        .badge {
            display: inline-block; border: 1px solid #0d3b66; padding: 2px 8px;
            font-weight: bold; font-size: 11px; letter-spacing: 0.3px;
        }
        .total-box {
            margin-top: 6px; padding: 4px 8px; border: 1.5px solid #0d3b66;
            font-size: 11px; font-weight: bold; text-align: right;
        }
        .marca {
            position: fixed; top: 40%; left: 0; right: 0; text-align: center;
            font-size: 80px; color: rgba(192, 57, 43, 0.18); font-weight: bold; transform: rotate(-25deg);
        }
        .legal { font-size: 8px; margin-top: 12px; color: #333; line-height: 1.35; }
    </style>
</head>
<body>

@if ($anulado)
    <div class="marca">ANULADO</div>
@endif

<table class="no-border" style="margin-bottom:6px;">
    <tr>
        <td style="width:50%;">
            @if (! empty($logo['uri']))
                <img src="{{ $logo['uri'] }}" alt="" style="max-height:48px;max-width:150px;">
            @endif
            <div style="font-size:11px;font-weight:bold;margin-top:3px;">{{ $empresa->nombre ?? '' }}</div>
            @if ($direccionEmpresa !== '')
                <div class="muted">{{ $direccionEmpresa }}</div>
            @endif
            @if (! empty($empresa->nroinscripcion))
                <div class="muted">CUIT: {{ $empresa->nroinscripcion }}</div>
            @endif
        </td>
        <td style="width:50%;text-align:right;vertical-align:top;">
            <h1>Comprobante de aplicación</h1>
            <div class="badge">{{ $comp->numeroFormateado() }}</div>
            <div style="margin-top:6px;"><strong>Fecha:</strong> {{ optional($comp->fecha)->format('d/m/Y') }}</div>
            <div style="margin-top:3px;"><strong>Estado:</strong> {{ $anulado ? 'ANULADO' : 'Emitido' }}</div>
            @if ($anulado && $comp->anulado_at)
                <div class="muted">Anulado el {{ $comp->anulado_at->format('d/m/Y H:i') }}</div>
            @endif
        </td>
    </tr>
</table>

<h3 style="font-size:9px;margin:6px 0 3px;padding:2px 5px;background:#d6eaf8;color:#1B4F72;border:1px solid #85C1E9;">Proveedor</h3>
<table>
    <tr>
        <td class="lbl">Código</td>
        <td>{{ $proveedor->codigo ?? '' }}</td>
        <td class="lbl">Razón social</td>
        <td>{{ $proveedor->nombre ?? '' }}</td>
    </tr>
    <tr>
        <td class="lbl">CUIT</td>
        <td>{{ $proveedor->nroinscripcion ?? '' }}</td>
        <td class="lbl">Domicilio</td>
        <td>{{ $proveedor->domicilio ?? '' }}</td>
    </tr>
</table>

<h3 style="font-size:9px;margin:8px 0 3px;padding:2px 5px;background:#d6eaf8;color:#1B4F72;border:1px solid #85C1E9;">{{ $variosCreditos ? 'Créditos aplicados' : 'Crédito aplicado' }}</h3>
<table>
    <thead>
        <tr>
            <th>Comprobante</th>
            <th style="width:9%;">Fecha</th>
            <th class="num" style="width:15%;">Importe original</th>
            <th class="num" style="width:15%;">Aplicado antes</th>
            <th class="num" style="width:15%;">Aplicado en este comprobante</th>
            <th class="num" style="width:15%;">Saldo luego de aplicar</th>
            <th class="num" style="width:15%;">Saldo actual</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($creditos as $c)
            <tr>
                <td><strong>{{ $c->etiqueta }}</strong></td>
                <td>{{ $c->fecha }}</td>
                <td class="num">{{ $c->importe !== null ? $c->moneda.' '.number_format($c->importe, 2, ',', '.') : '—' }}</td>
                <td class="num">{{ $c->previo !== null ? $c->moneda.' '.number_format($c->previo, 2, ',', '.') : '—' }}</td>
                <td class="num"><strong>{{ $c->moneda }} {{ number_format($c->aplicado_aca, 2, ',', '.') }}</strong></td>
                <td class="num">{{ $c->saldo_luego !== null ? $c->moneda.' '.number_format($c->saldo_luego, 2, ',', '.') : '—' }}</td>
                <td class="num">{{ $c->saldo_actual !== null ? $c->moneda.' '.number_format($c->saldo_actual, 2, ',', '.') : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h3 style="font-size:9px;margin:8px 0 3px;padding:2px 5px;background:#d6eaf8;color:#1B4F72;border:1px solid #85C1E9;">Comprobantes aplicados ({{ $lineas->count() }})</h3>
<table>
    <thead>
        <tr>
            <th style="width:9%;">Fecha</th>
            <th style="width:15%;">Tipo</th>
            <th style="width:12%;">Número</th>
            <th>Descripción</th>
            @if ($variosCreditos)
                <th style="width:10%;">Crédito</th>
            @endif
            <th class="num" style="width:12%;">Importe del comprob.</th>
            <th class="num" style="width:12%;">Aplicado acá</th>
            <th class="num" style="width:12%;">Saldo luego de aplicar</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $l)
            <tr>
                <td>{{ $l->fecha }}</td>
                <td>
                    <strong>{{ $l->tipo }}</strong>
                    @if ($l->tipo_nombre !== '')
                        <div class="muted" style="white-space:nowrap;">{{ \Illuminate\Support\Str::limit($l->tipo_nombre, 20) }}</div>
                    @endif
                </td>
                <td>{{ $l->numero }}</td>
                <td>{{ $l->descripcion }}</td>
                @if ($variosCreditos)
                    <td>{{ $l->credito }}</td>
                @endif
                <td class="num">{{ $l->importe !== null ? $l->moneda.' '.number_format($l->importe, 2, ',', '.') : '—' }}</td>
                <td class="num"><strong>{{ $l->moneda }} {{ number_format($l->aplicado, 2, ',', '.') }}</strong></td>
                <td class="num">
                    @if ($l->saldo === null)
                        —
                    @else
                        {{ $l->moneda }} {{ number_format($l->saldo, 2, ',', '.') }}
                        <div class="muted" style="{{ $l->parcial ? 'color:#B9770E;font-weight:bold;' : '' }}">{{ $l->parcial ? 'APLICACIÓN PARCIAL' : 'Cancelado' }}</div>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="total-box">
    Total aplicado ($): {{ number_format($totalPesos, 2, ',', '.') }}
    @if ($totalMoneda !== null)
        &nbsp;|&nbsp; Total {{ $monedaTotal }}: {{ number_format($totalMoneda, 2, ',', '.') }}
    @endif
    @if (abs($diferenciaCambio) >= 0.01)
        &nbsp;|&nbsp; Dif. cambio ($): {{ number_format($diferenciaCambio, 2, ',', '.') }}
    @endif
</div>

@if (! empty($comp->observacion))
    <div style="margin-top:8px;"><strong>Observación:</strong> {{ $comp->observacion }}</div>
@endif

<div class="legal">
    Comprobante interno que documenta las aplicaciones entre comprobantes de la cuenta corriente del proveedor.
    «Saldo luego de aplicar» es lo que queda pendiente después de este comprobante; «Saldo actual» incluye aplicaciones posteriores.
    Los importes en moneda extranjera se expresan en su moneda original.
</div>

<div class="muted" style="margin-top:10px;">
    Generado {{ $generadoEn }}
    @if ($usuarioLogin !== '')
        &nbsp;|&nbsp; Usuario: {{ $usuarioLogin }}
    @endif
</div>

</body>
</html>
