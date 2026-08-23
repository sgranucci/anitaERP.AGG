@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresas->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobantes de proveedor</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 40%; text-align: center;">
                <strong style="font-size: 14px;">Comprobantes de proveedor</strong>
                <div class="meta">Generado {{ now()->format('d/m/Y H:i') }}</div>
                @if (filled($busqueda ?? null))
                    <div class="meta">Búsqueda: {{ $busqueda }}</div>
                @elseif (! empty($filtros['valor'] ?? null))
                    <div class="meta">Filtro: {{ $filtros['valor'] }}</div>
                @endif
                @if (! empty($filtros['empresa_id'] ?? null))
                    <div class="meta">Empresa id: {{ $filtros['empresa_id'] }}</div>
                @elseif (($filtros['empresa_scope'] ?? '') === 'todas')
                    <div class="meta">Todas las empresas asignadas</div>
                @endif
                @if (! empty($filtros['estado'] ?? null) && ($filtros['estado'] ?? '') !== \App\Support\Compras\ComprobanteProveedorEstados::FILTRO_TODOS)
                    <div class="meta">Estado: {{ \App\Support\Compras\ComprobanteProveedorEstados::etiqueta($filtros['estado']) }}</div>
                @endif
                <div class="meta">{{ $totalFilas }} registro(s)</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 11%;">Empresa</th>
                <th style="width: 14%;">Proveedor</th>
                <th style="width: 9%;">Tipo</th>
                <th style="width: 9%;">Número</th>
                <th style="width: 7%;">OC</th>
                <th style="width: 7%;">Fecha</th>
                <th style="width: 8%;">F. IVA / contabiliz.</th>
                <th style="width: 7%;">Total</th>
                <th style="width: 9%;">Estado</th>
                <th style="width: 10%;">Origen</th>
                <th style="width: 11%;">Modo carga</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->empresas->nombre ?? '' }}</td>
                <td>{{ $row->proveedores->nombre ?? '' }}</td>
                <td>{{ trim(($row->tipotransaccion_compras->abreviatura ?? '').' '.($row->tipotransaccion_compras->nombre ?? '')) }}</td>
                <td>{{ $row->letra }}{{ $row->sucursal }}-{{ $row->numerocomprobante }}</td>
                <td>{{ $row->ordencompras->numeroordencompra ?? '' }}</td>
                <td>{{ $row->fechacomprobante ? $row->fechacomprobante->format('d/m/Y') : '' }}</td>
                <td>{{ $row->fechaiva ? $row->fechaiva->format('d/m/Y') : '' }}</td>
                <td>{{ number_format((float) $row->total, 2, ',', '.') }}</td>
                <td>{{ $row->estado }}</td>
                <td>{{ \App\Support\Compras\ComprobanteProveedorOrigenEntrada::etiqueta($row->origen_entrada ?? '') }}</td>
                <td>{{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta($row->modo_carga ?? '') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
