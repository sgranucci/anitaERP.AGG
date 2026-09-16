<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Emisión OT preimpreso</title>
    <style type="text/css">
        @page { margin: 0; size: a4 portrait; }
        html, body {
            margin: 0;
            padding: 0;
            width: 210mm;
            height: 297mm;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #000000;
        }
        .pagina {
            position: relative;
            width: 210mm;
            height: 297mm;
            page-break-after: always;
            overflow: hidden;
        }
        .pagina-ultima { page-break-after: auto; }
        .campo {
            position: absolute;
            white-space: nowrap;
            overflow: hidden;
            line-height: 1.05;
            font-weight: bold;
        }
        .campo-titulo { font-size: 10pt; }
        .campo-ot { font-size: 11pt; }
        .campo-pares { font-size: 10pt; }
        .qr {
            position: absolute;
        }
    </style>
</head>
<body>
@foreach ($documentos as $docIndex => $doc)
    @php
        $paginas = $doc['paginas_preimpreso'] ?? [];
        $totalPaginasDoc = count($paginas);
    @endphp
    @foreach ($paginas as $paginaIndex => $pagina)
        @php
            $esUltima = ($docIndex === count($documentos) - 1) && ($paginaIndex === $totalPaginasDoc - 1);
        @endphp
        <div class="pagina{{ $esUltima ? ' pagina-ultima' : '' }}">
            @foreach (($pagina['campos'] ?? []) as $campo)
                @php
                    $valor = trim((string) ($campo['v'] ?? ''));
                    $k = (string) ($campo['k'] ?? '');
                    $clase = 'campo';
                    if (strpos($k, 'titulo_') === 0) {
                        $clase .= ' campo-titulo';
                    } elseif ($k === 'codigo') {
                        $clase .= ' campo-ot';
                    } elseif ($k === 'tot_pares') {
                        $clase .= ' campo-pares';
                    }
                @endphp
                @if ($valor !== '')
                    <div class="{{ $clase }}" style="top: {{ $campo['y'] }}mm; left: {{ $campo['x'] }}mm; max-width: {{ $campo['max_w'] ?? 120 }}mm;">
                        {{ $valor }}
                    </div>
                @endif
            @endforeach
            @foreach (($pagina['qrs'] ?? []) as $qr)
                @if (($qr['uri'] ?? '') !== '')
                    <img class="qr" src="{{ $qr['uri'] }}" alt="QR"
                         style="top: {{ $qr['y'] }}mm; left: {{ $qr['x'] }}mm; width: {{ $qr['s'] }}mm; height: {{ $qr['s'] }}mm;">
                @endif
            @endforeach
        </div>
    @endforeach
@endforeach
</body>
</html>
