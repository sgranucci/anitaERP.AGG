<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Auditoría propuesta #{{ $propuesta->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 8px; }
        h2 { font-size: 11px; margin: 12px 0 4px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data th { background: #85C1E9; color: #17202A; }
        tr:nth-child(even) td { background: #f5f5f5; }
        .muted { color: #666; }
    </style>
</head>
<body>
@php $r = $resumen; @endphp
<h1>Auditoría propuesta de pagos #{{ $propuesta->id }}</h1>
<p>
    Generado {{ date('d/m/Y H:i') }} —
    Empresa {{ $propuesta->empresas->nombre ?? '' }} —
    Estado {{ $propuesta->estado }} —
    Total {{ number_format((float)$r['monto_total'], 2, ',', '.') }} —
    Autorizado {{ number_format((float)$r['monto_autorizado'], 2, ',', '.') }}
</p>
<p class="muted">
    Incluidas {{ $r['lineas_incluidas'] }} · Ejecutadas {{ $r['lineas_ejecutadas'] }}
    · Pendientes {{ $r['lineas_pendientes'] }} · Excluidas {{ $r['lineas_excluidas'] }}
    · OP bloqueadas {{ $r['ops_bloqueadas'] }}
    @if ($r['lote_enviado']) · Lote ENVIADO @endif
</p>

<h2>Historia de estados</h2>
<table class="data">
    <thead><tr><th>Fecha</th><th>Estado</th><th>Usuario</th><th>Observación</th></tr></thead>
    <tbody>
        @foreach($estados as $est)
            <tr>
                <td>{{ optional($est->fecha)->format('d/m/Y H:i') }}</td>
                <td>{{ $est->estado }}</td>
                <td>{{ $est->usuarios->nombre ?? '' }}</td>
                <td>{{ $est->observacion }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Firmas árbol</h2>
<table class="data">
    <thead><tr><th>Nivel</th><th>Estado</th><th>Destinatario</th><th>Enviado por</th><th>Proceso</th><th>Obs.</th></tr></thead>
    <tbody>
        @forelse($firmas_arbol as $f)
            <tr>
                <td>{{ $f->nivel }}</td>
                <td>{{ $f->estado }}</td>
                <td>{{ $f->destinatario }}</td>
                <td>{{ $f->enviado_por }}</td>
                <td>{{ $f->fecha_proceso }}</td>
                <td>{{ $f->observacion }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin firmas</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Órdenes de pago</h2>
<table class="data">
    <thead><tr><th>OP</th><th>Proveedor</th><th>Estado</th><th>Monto</th><th>Bloqueo banco</th></tr></thead>
    <tbody>
        @foreach($ops as $op)
            <tr>
                <td>#{{ $op->id }}</td>
                <td>{{ $op->proveedores->nombre ?? '' }}</td>
                <td>{{ $op->estado }}</td>
                <td>{{ number_format((float)$op->monto, 2, ',', '.') }}</td>
                <td>{{ $op->bloqueada_banco ? 'Sí' : 'No' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Lotes bancarios</h2>
<table class="data">
    <thead><tr><th>ID</th><th>Estado</th><th>Líneas</th><th>Monto</th><th>Archivo</th><th>Enviado</th><th>Driver</th></tr></thead>
    <tbody>
        @foreach($lotes as $lote)
            <tr>
                <td>{{ $lote->id }}</td>
                <td>{{ $lote->estado }}</td>
                <td>{{ $lote->cantidad_lineas }}</td>
                <td>{{ number_format((float)$lote->monto_total, 2, ',', '.') }}</td>
                <td>{{ $lote->archivo_nombre }}</td>
                <td>{{ optional($lote->enviado_banco_at)->format('d/m/Y H:i') }}</td>
                <td>{{ $lote->convenio_driver }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
