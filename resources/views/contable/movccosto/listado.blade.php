@php
    $tituloReporte = $titulo ?? 'Totales por centro de costo';
    $ultimoBloque = count($bloques) - 1;
    $hayVariasColumnas = count($columnas) > 1;
    $dhPdf = ($formato ?? 'saldo') === 'debe_haber';
    $colspanCab = 2 + (max(1, count($bloques[0] ?? [])) + ($ultimoBloque === 0 && $hayVariasColumnas ? 1 : 0)) * ($dhPdf ? 2 : 1);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 6.5px; font-weight: bold; color: #17202A; text-align: center; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tbody tr.mcc-fila-total-nivel { background-color: #EAF2F8; font-weight: bold; }
        table.data tbody tr.mcc-fila-total-empresa { background-color: #D6EAF8; font-weight: bold; }
        table.data tbody tr.mcc-fila-total-general { background-color: #AED6F1; font-weight: bold; }
        table.data tbody tr.mcc-fila-header-empresa { background-color: #E5E7E9; font-weight: bold; }
        table.data tbody tr.mcc-fila-titulo td { font-weight: bold; }
        .text-right { text-align: right; white-space: nowrap; }
        .text-center { text-align: center; }
        .font-weight-bold { font-weight: bold; }
        table.data th.mcc-col-cta { width: 8%; }
        table.data th.mcc-col-desc { width: 22%; }
        .bloque-nuevo { page-break-before: always; }
    </style>
</head>
<body>
@foreach ($bloques as $nroBloque => $bloque)
    <div class="{{ $nroBloque > 0 ? 'bloque-nuevo' : '' }}">
        <table class="data">
            <thead>
                @include('includes.reportes.pdf_thead_cabecera', [
                    'titulo' => $tituloReporte,
                    'subtitulo' => trim(($subtitulo ?? '').(count($bloques) > 1 ? ' · Columnas parte '.($nroBloque + 1).' de '.count($bloques) : '')),
                    'colspan' => 2 + (count($bloque) + ($nroBloque === $ultimoBloque && $hayVariasColumnas ? 1 : 0)) * ($dhPdf ? 2 : 1),
                    'logosCabecera' => $logosCabecera,
                    'totalFilas' => $totalFilas,
                ])
                @include('contable.movccosto.partials.tabla_cabecera', [
                    'columnas' => $columnas,
                    'indices' => $bloque,
                    'formato' => $formato,
                    'mostrarTotal' => $nroBloque === $ultimoBloque && $hayVariasColumnas,
                    'fijas' => false,
                ])
            </thead>
            <tbody>
                @include('contable.movccosto.partials.tabla_cuerpo', [
                    'filas' => $filasPorBloque[$nroBloque] ?? $filas,
                    'columnas' => $columnas,
                    'indices' => $bloque,
                    'formato' => $formato,
                    'mostrarTotal' => $nroBloque === $ultimoBloque && $hayVariasColumnas,
                    'enlaces' => false,
                    'fijas' => false,
                ])
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
