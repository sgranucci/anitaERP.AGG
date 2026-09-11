@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $letterhead = $letterhead ?? \App\Exports\Compras\Listaprecio_ProveedorDetalleExport::letterhead($lista);
    $lista->nombreempresa = $letterhead['nombre'] ?? '';
    $logoDat = EmpresaLogoArchivo::dataUriDesdeNombre($letterhead['nombre'] !== '' ? $letterhead['nombre'] : null);
    $lineas = $lista->listaprecio_proveedor_articulos ?? collect();
    $totalFilas = $lineas->count();
    $proveedor = $lista->proveedores;
    $codigoProv = trim((string) ($proveedor->codigo ?? ''));
    $nombreProv = trim((string) ($proveedor->nombre ?? ''));
    $proveedorTxt = trim($codigoProv.($codigoProv !== '' && $nombreProv !== '' ? ' — ' : '').$nombreProv);
    $moneda = trim((string) (optional($lista->monedas)->abreviatura ?: optional($lista->monedas)->nombre ?: ''));
    $fecha = $lista->fecha ? date('d/m/Y', strtotime((string) $lista->fecha)) : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lista de precios {{ $lista->id }}</title>
    <style>
        @page { margin: 12mm 14mm 12mm 14mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #111;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .pdf-cabecera { width: 100%; margin-bottom: 8px; }
        .pdf-cabecera td { border: none; vertical-align: top; }
        .logo-empresa { max-width: 180px; max-height: 52px; width: auto; height: auto; }
        .pdf-cabecera-marca { font-size: 12px; font-weight: bold; margin-top: 4px; }
        .pdf-cabecera-cuit { font-size: 9px; margin-top: 1px; color: #333; }
        .titulo-doc { font-size: 15px; font-weight: bold; margin: 0; }
        .fecha-doc { font-size: 10px; margin: 3px 0 0 0; }
        .muted { color: #555; font-size: 8px; }
        table.cabecera { margin-bottom: 8px; }
        table.cabecera td { border: 1px solid #333; padding: 4px 6px; vertical-align: middle; }
        table.cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 12%; }
        h2.seccion {
            font-size: 11px;
            margin: 8px 0 4px 0;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
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
            font-size: 8px;
            font-weight: bold;
            color: #17202A;
        }
        .text-right { text-align: right; white-space: nowrap; }
        .pie { margin-top: 8px; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    <table class="pdf-cabecera">
        <colgroup><col style="width:50%;"><col style="width:50%;"></colgroup>
        <tr>
            <td>
                @if (! empty($logoDat['uri']))
                    <img class="logo-empresa" src="{{ $logoDat['uri'] }}" alt="{{ $letterhead['nombre'] }}">
                @endif
                <div class="pdf-cabecera-marca">{{ $letterhead['nombre'] !== '' ? $letterhead['nombre'] : '—' }}</div>
                @if (($letterhead['cuit'] ?? '') !== '')
                    <div class="pdf-cabecera-cuit">CUIT {{ $letterhead['cuit'] }}</div>
                @endif
                @if (($letterhead['domicilio'] ?? '') !== '')
                    <div class="pdf-cabecera-cuit">{{ $letterhead['domicilio'] }}</div>
                @endif
            </td>
            <td style="text-align: right;">
                <p class="titulo-doc">LISTA DE PRECIOS NRO {{ $lista->id }}</p>
                <p class="fecha-doc">{{ $lista->nombre ?: 'Lista de precios de proveedor' }}</p>
                <p class="fecha-doc">Fecha lista: {{ $fecha !== '' ? $fecha : '—' }}</p>
                <p class="muted">Impresi&oacute;n {{ date('d/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <h2 class="seccion">Datos de la lista</h2>
    <table class="cabecera">
        <colgroup>
            <col style="width:12%;"><col style="width:38%;">
            <col style="width:12%;"><col style="width:38%;">
        </colgroup>
        <tr>
            <td class="lbl">Proveedor</td>
            <td>{{ $proveedorTxt !== '' ? $proveedorTxt : '—' }}</td>
            <td class="lbl">Moneda</td>
            <td>{{ $moneda !== '' ? $moneda : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Estado</td>
            <td>{{ $lista->estado ?: '—' }}</td>
            <td class="lbl">&Iacute;tems</td>
            <td>{{ $totalFilas }}</td>
        </tr>
        <tr>
            <td class="lbl">Cond. pago</td>
            <td>{{ optional($lista->condicionpagos)->nombre ?: '—' }}</td>
            <td class="lbl">Cond. entrega</td>
            <td>{{ optional($lista->condicionentregas)->nombre ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cond. compra</td>
            <td colspan="3">{{ optional($lista->condicioncompras)->nombre ?: '—' }}</td>
        </tr>
        @if (trim((string) ($lista->observaciones ?? '')) !== '')
            <tr>
                <td class="lbl">Observaciones</td>
                <td colspan="3">{{ $lista->observaciones }}</td>
            </tr>
        @endif
    </table>

    <h2 class="seccion">Precios por art&iacute;culo</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 14%;">SKU</th>
                <th style="width: 38%;">Descripci&oacute;n</th>
                <th style="width: 12%;">Precio</th>
                <th style="width: 8%;">% Desc.</th>
                <th style="width: 16%;">C&oacute;d. art. proveedor</th>
                <th style="width: 12%;">Fecha vigencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lineas as $linea)
                <tr>
                    <td>{{ optional($linea->articulos)->sku ?? '' }}</td>
                    <td>{{ optional($linea->articulos)->descripcion ?? '' }}</td>
                    <td class="text-right">{{ is_numeric($linea->precio) ? number_format((float) $linea->precio, 4, ',', '.') : $linea->precio }}</td>
                    <td class="text-right">{{ is_numeric($linea->descuento) ? number_format((float) $linea->descuento, 2, ',', '.') : $linea->descuento }}</td>
                    <td>{{ $linea->codigo_articulo_proveedor ?? '' }}</td>
                    <td>{{ $linea->fechavigencia ? date('d/m/Y', strtotime($linea->fechavigencia)) : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Sin renglones de precio.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="pie">{{ $totalFilas }} rengl&oacute;n(es) · Generado por anitaERP</div>
</body>
</html>
