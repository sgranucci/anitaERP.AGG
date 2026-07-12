<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturas apócrifas ARCA — suspensiones</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0; color:#dc3545;">Facturas apócrifas ARCA (WSAPOC)</h2>
<p style="margin:0 0 16px 0;">
    Novedades consultadas:
    <strong>{{ $informe['desde'] ?? '—' }}</strong>
    →
    <strong>{{ $informe['hasta'] ?? '—' }}</strong>
    @if (($informe['modo'] ?? '') !== '')
        · Modo {{ $informe['modo'] }}
    @endif
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;">
        <th align="left">Concepto</th>
        <th align="right">Cantidad</th>
    </tr>
    <tr>
        <td>Publicaciones en novedades WSAPOC</td>
        <td align="right">{{ (int) ($informe['publicaciones_ws'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>CUITs en novedades</td>
        <td align="right">{{ (int) ($informe['cuits_novedad'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Proveedores coincidentes en ERP</td>
        <td align="right">{{ (int) ($informe['proveedores_coincidentes'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Clientes coincidentes en ERP</td>
        <td align="right">{{ (int) ($informe['clientes_coincidentes'] ?? 0) }}</td>
    </tr>
    <tr>
        <td><strong>Proveedores suspendidos</strong></td>
        <td align="right"><strong>{{ count($informe['proveedores_suspendidos'] ?? []) }}</strong></td>
    </tr>
    <tr>
        <td><strong>Clientes suspendidos</strong></td>
        <td align="right"><strong>{{ count($informe['clientes_suspendidos'] ?? []) }}</strong></td>
    </tr>
</table>

@if (! empty($informe['proveedores_suspendidos']))
    <h3 style="margin:18px 0 6px 0; color:#dc3545;">Proveedores suspendidos automáticamente</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%;">
        <tr style="background:#f0f0f0;">
            <th align="left">ID</th>
            <th align="left">Cód.</th>
            <th align="left">Nombre</th>
            <th align="left">CUIT</th>
            <th align="left">Publicación ARCA</th>
            <th align="left">ABM</th>
        </tr>
        @foreach ($informe['proveedores_suspendidos'] as $fila)
            <tr>
                <td>{{ (int) ($fila['id'] ?? 0) }}</td>
                <td>{{ $fila['codigo'] ?? '—' }}</td>
                <td>{{ $fila['nombre'] ?? '—' }}</td>
                <td>{{ $fila['cuit'] ?? '—' }}</td>
                <td>
                    {{ $fila['fecha_publicacion'] ?? '—' }}
                    @if (! empty($fila['descripcion_arca']))
                        <br><small>{{ $fila['descripcion_arca'] }}</small>
                    @endif
                </td>
                <td>
                    @if (! empty($fila['url_editar']))
                        <a href="{{ $fila['url_editar'] }}">Editar proveedor</a>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['clientes_suspendidos']))
    <h3 style="margin:18px 0 6px 0; color:#dc3545;">Clientes suspendidos automáticamente</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%;">
        <tr style="background:#f0f0f0;">
            <th align="left">ID</th>
            <th align="left">Cód.</th>
            <th align="left">Nombre</th>
            <th align="left">CUIT</th>
            <th align="left">Publicación ARCA</th>
            <th align="left">ABM</th>
        </tr>
        @foreach ($informe['clientes_suspendidos'] as $fila)
            <tr>
                <td>{{ (int) ($fila['id'] ?? 0) }}</td>
                <td>{{ $fila['codigo'] ?? '—' }}</td>
                <td>{{ $fila['nombre'] ?? '—' }}</td>
                <td>{{ $fila['cuit'] ?? '—' }}</td>
                <td>
                    {{ $fila['fecha_publicacion'] ?? '—' }}
                    @if (! empty($fila['descripcion_arca']))
                        <br><small>{{ $fila['descripcion_arca'] }}</small>
                    @endif
                </td>
                <td>
                    @if (! empty($fila['url_editar']))
                        <a href="{{ $fila['url_editar'] }}">Editar cliente</a>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

<p style="margin-top:24px; font-size:12px; color:#666;">
    Motivo suspensión proveedor: {{ config('arca_wsapoc.tiposuspension_nombre', 'Facturas apócrifas (ARCA APOC)') }}.<br>
    Motivo suspensión cliente: {{ config('arca_wsapoc.tiposuspension_cliente_nombre', 'Facturas apócrifas (ARCA APOC)') }}.<br>
    Comando manual:
    <code>php artisan arca:auditar-proveedores-facturas-apocrifas --desde={{ $informe['desde'] ?? '' }} --hasta={{ $informe['hasta'] ?? '' }}</code>
</p>
</body>
</html>
