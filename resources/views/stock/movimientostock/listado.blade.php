@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        collect($datas)->map(fn ($f) => (object) ['nombreempresa' => $f->nombreEmpresa ?? ''])
    );
    $subtituloFiltros = [];
    if (($filtros['empresa_id'] ?? 0) > 0) {
        $subtituloFiltros[] = 'Empresa ID '.$filtros['empresa_id'];
    }
    if (($filtros['deposito_id'] ?? 0) > 0) {
        $subtituloFiltros[] = 'Depósito ID '.$filtros['deposito_id'];
    }
    if (trim((string) ($filtros['valor'] ?? '')) !== '') {
        $subtituloFiltros[] = 'Búsqueda: '.$filtros['valor'];
    }
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
        .num { text-align: right; }
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
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Movimientos de stock</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (count($subtituloFiltros) > 0)
                    <div class="meta">Filtros: {{ implode(' · ', $subtituloFiltros) }}</div>
                @endif
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;">ID</th>
                <th style="width: 7%;">Fecha</th>
                <th style="width: 5%;">Nat.</th>
                <th style="width: 12%;">Tipo</th>
                <th style="width: 8%;">N&uacute;mero</th>
                <th style="width: 10%;">Origen</th>
                <th style="width: 10%;">Destino</th>
                <th style="width: 6%;">Lote</th>
                <th style="width: 10%;">Empresa</th>
                <th style="width: 6%;" class="num">Cant.</th>
                <th style="width: 4%;" class="num">&Iacute;t.</th>
                <th style="width: 8%;">Estado</th>
                <th style="width: 5%;" class="num">M.S</th>
                <th style="width: 5%;" class="num">M.E</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $fila)
                @php
                    $estadoLabel = $fila->esTransferencia()
                        ? ($fila->etiquetaEstadoTransferencia() ?? '')
                        : ($estado_enum[$fila->estadoMovimiento ?? ''] ?? ($fila->estadoMovimiento ?? ''));
                @endphp
                <tr>
                    <td>{{ $fila->pkId }}</td>
                    <td>{{ $fila->fecha ? date('d/m/Y', strtotime($fila->fecha)) : '' }}</td>
                    <td>{{ $fila->esTransferencia() ? 'TRF' : 'MOV' }}</td>
                    <td>{{ $fila->tipoNombre }}</td>
                    <td>{{ $fila->codigoListado }}</td>
                    <td>{{ $fila->esTransferencia() ? $fila->etiquetaOrigen() : '—' }}</td>
                    <td>{{ $fila->esTransferencia() ? $fila->etiquetaDestino() : ($fila->depositoNombre ?? '—') }}</td>
                    <td>{{ $fila->loteListado }}</td>
                    <td>{{ $fila->nombreEmpresa }}</td>
                    <td class="num">{{ number_format($fila->totalCantidad, 2, ',', '.') }}</td>
                    <td class="num">{{ $fila->itemsCount > 0 ? $fila->itemsCount : '' }}</td>
                    <td>{{ $estadoLabel }}</td>
                    <td class="num">{{ $fila->movSalidaId ?? '' }}</td>
                    <td class="num">{{ $fila->movEntradaId ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
