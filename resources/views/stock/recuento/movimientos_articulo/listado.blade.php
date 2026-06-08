@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Stock\RecuentoMovimientosArticuloSupport;

    $art = $contexto['articulo'] ?? [];
    $dep = $contexto['deposito'] ?? [];
    $modoTodos = (bool) ($contexto['modo_todos_depositos'] ?? false);
    $depEtiqueta = $modoTodos
        ? 'Todos los depósitos'
        : RecuentoMovimientosArticuloSupport::etiquetaDeposito($dep);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($movimientos);
    $totalFilas = is_countable($movimientos) ? count($movimientos) : 0;
    $tituloArticulo = trim(($art['sku'] ?? '').' '.($art['descripcion'] ?? ''));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Movimientos de stock</title>
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
        table.data .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; line-height: 1.35; }
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
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Movimientos de stock por artículo</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <div class="meta" style="margin-bottom: 8px;">
        <strong>Artículo:</strong> {{ $tituloArticulo }}<br>
        <strong>Depósito:</strong> {{ $depEtiqueta }}<br>
        <strong>{{ $modoTodos ? 'Saldo total' : 'Saldo actual' }}:</strong> {{ $contexto['saldo_fmt'] ?? '0' }}
    </div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 9%;">Fecha</th>
                @if ($modoTodos)
                <th style="width: 12%;">Depósito</th>
                @endif
                <th style="width: 7%;">Tipo</th>
                <th class="num" style="width: 8%;">Entrada</th>
                <th class="num" style="width: 8%;">Salida</th>
                <th style="width: 22%;">Concepto</th>
                <th style="width: 9%;">Mov. stock</th>
                <th style="width: {{ $modoTodos ? '25%' : '37%' }};">Leyenda mov.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movimientos as $m)
                <tr>
                    <td>{{ $m->fecha ? \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') : '' }}</td>
                    @if ($modoTodos)
                    <td>{{ $m->deposito_etiqueta ?? '' }}</td>
                    @endif
                    <td>{{ $m->tipo ?? '' }}</td>
                    <td class="num">{{ $m->entrada_fmt ?? '' }}</td>
                    <td class="num">{{ $m->salida_fmt ?? '' }}</td>
                    <td>{{ $m->concepto_display ?? $m->concepto ?? '' }}</td>
                    <td>{{ $m->movimiento_codigo ?: ($m->movimientostock_id ?? '') }}</td>
                    <td>{{ $m->movimiento_leyenda ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
