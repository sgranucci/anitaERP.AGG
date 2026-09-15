<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Emisión OT</title>
    <style type="text/css">
        @page { margin: 6mm 6mm 8mm 6mm; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #17202A;
        }
        .ot-pagina { page-break-inside: avoid; }
        .salto-pagina { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; }
        .banda-titulo {
            background: #85C1E9;
            color: #17202A;
            padding: 4px 6px;
            margin-bottom: 4px;
        }
        .banda-titulo .copia {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .banda-titulo .ot {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
        }
        .meta-linea {
            font-size: 8px;
            margin-bottom: 3px;
            line-height: 1.3;
        }
        .dos-cols td {
            width: 50%;
            vertical-align: top;
            padding: 0 3px 0 0;
        }
        .bloque {
            border: 1px solid #bbbbbb;
            padding: 3px 4px;
            margin-bottom: 3px;
            min-height: 28px;
        }
        .bloque h4 {
            margin: 0 0 2px 0;
            font-size: 8px;
            font-weight: bold;
            color: #1B4F72;
            text-transform: uppercase;
            border-bottom: 1px solid #85C1E9;
            padding-bottom: 1px;
        }
        .campo { margin: 0 0 1px 0; line-height: 1.25; }
        .campo strong { color: #2C3E50; }
        table.medidas th,
        table.medidas td {
            border: 1px solid #999999;
            padding: 2px 2px;
            text-align: center;
            font-size: 8px;
        }
        table.medidas thead th {
            background: #85C1E9;
            color: #17202A;
            font-weight: bold;
        }
        .qr {
            width: 64px;
            height: 64px;
        }
        .pie {
            margin-top: 3px;
            font-size: 7px;
            color: #555555;
        }
        .destacar {
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
@foreach ($documentos as $docIndex => $doc)
    @php
        $copias = max(1, (int) ($doc['copias'] ?? 1));
        $titulos = $doc['titulos_copia'] ?? [];
        $mostrarCajas = ! empty($doc['cajas']);
    @endphp
    @for ($copia = 1; $copia <= $copias; $copia++)
        @php
            $esPrimera = ($docIndex === 0 && $copia === 1);
            $tituloCopia = trim((string) ($titulos[$copia - 1] ?? ''));
            if ($tituloCopia === '') {
                $tituloCopia = 'Copia '.$copia;
            }
            $esCajas = stripos($tituloCopia, 'CAJA') !== false;
            $esForradoBase = stripos($tituloCopia, 'FORRADO') !== false;
        @endphp
        <div class="ot-pagina{{ $esPrimera ? '' : ' salto-pagina' }}">
            <table class="banda-titulo">
                <tr>
                    <td style="width: 55%;" class="copia">{{ $tituloCopia }}</td>
                    <td style="width: 30%;" class="ot">OT {{ $doc['codigo'] }}</td>
                    <td style="width: 15%; text-align: right;">
                        @if (($doc['qr_data_uri'] ?? '') !== '')
                            <img class="qr" src="{{ $doc['qr_data_uri'] }}" alt="QR">
                        @endif
                    </td>
                </tr>
            </table>

            <div class="meta-linea">
                <span class="destacar">Art. {{ $doc['codigo_articulo'] }}</span>
                @if ($doc['codigo_articulo_reducido'] !== '')
                    ({{ $doc['codigo_articulo_reducido'] }})
                @endif
                · {{ $doc['fecha_fmt'] }}
                · Pares: <strong>{{ number_format((float) $doc['tot_pares'], 0, ',', '.') }}</strong>
                · Emisión {{ $doc['tipoemision'] }}
                · Numeración {{ $doc['numeracion'] }}
            </div>

            <table class="dos-cols">
                <tr>
                    <td>
                        <div class="bloque">
                            <h4>Clientes / pedidos</h4>
                            <div class="campo"><strong>Clientes:</strong> {{ implode(' / ', $doc['clientes']) }}</div>
                            <div class="campo"><strong>Localidad:</strong> {{ $doc['localidad'] }}</div>
                            <div class="campo"><strong>Vendedor:</strong> {{ $doc['vendedor'] }}</div>
                            <div class="campo"><strong>Pedidos:</strong> {{ $doc['pedidos'] }}</div>
                            @if (trim((string) $doc['leyenda']) !== '')
                                <div class="campo"><strong>Obs.:</strong> {{ $doc['leyenda'] }}</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="bloque">
                            <h4>Artículo / combinación</h4>
                            <div class="campo"><strong>Combinación:</strong> {{ $doc['combinacion'] }}</div>
                            <div class="campo"><strong>Fondo:</strong> {{ $doc['fondo'] }} / {{ $doc['color_fondo'] }}</div>
                            <div class="campo"><strong>Tipo corte:</strong> {{ $doc['tipo_corte'] }} ({{ $doc['abrev_tipo_corte'] }})</div>
                            <div class="campo"><strong>Corte forro:</strong> {{ $doc['tipo_corte_forro'] }}</div>
                            <div class="campo"><strong>Forro:</strong> {{ $doc['forro'] }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="bloque">
                <h4>Materiales / proceso</h4>
                <div class="campo"><strong>Capellada:</strong> {{ $doc['material_capellada'] }}</div>
                <div class="campo"><strong>Capellada c/consumo:</strong> {{ $doc['material_capellada_consumo'] }}</div>
                <div class="campo"><strong>Forrado fondo:</strong> {{ $doc['forrado_fondo_consumo'] }}</div>
                <div class="campo"><strong>Forrado base:</strong> {{ $doc['forrado_base_consumo'] }}</div>
                <div class="campo"><strong>Apliques:</strong> {{ $doc['aplique'] }}</div>
                <div class="campo"><strong>Empaque:</strong> {{ $doc['empaque'] }}</div>
                <div class="campo">
                    <strong>Plantilla:</strong> {{ $doc['plvista'] }}
                    · <strong>Serigrafía:</strong> {{ $doc['serigrafia'] }}
                    · <strong>Pl. armado:</strong> {{ $doc['plarmado'] }}
                </div>
                <div class="campo">
                    <strong>Puntera:</strong> {{ $doc['puntera'] }}
                    · <strong>Contrafuerte:</strong> {{ $doc['contrafuerte'] }}
                </div>
            </div>

            @if (! empty($doc['medidas']))
                <div class="bloque">
                    <h4>Medidas / cantidades</h4>
                    <table class="medidas">
                        <thead>
                            <tr>
                                @foreach ($doc['medidas'] as $medida)
                                    <th>{{ $medida['medida'] }}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                @foreach ($doc['medidas'] as $medida)
                                    <td>{{ number_format((float) $medida['cantidad'], 0, ',', '.') }}</td>
                                @endforeach
                                <td><strong>{{ number_format((float) $doc['tot_pares'], 0, ',', '.') }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($mostrarCajas && ($esCajas || $copia === $copias || $esForradoBase))
                <div class="bloque">
                    <h4>Cajas</h4>
                    <table class="medidas">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Cantidad</th>
                                <th>Código / descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doc['cajas'] as $caja)
                                <tr>
                                    <td>{{ $caja['cantidad'] }}</td>
                                    <td style="text-align: left;">{{ $caja['descripcion'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="pie">
                Generado {{ date('d/m/Y H:i') }} · anitaERP · copia {{ $copia }}/{{ $copias }}
                · intervalos pares {{ $doc['tot_pares1'] }}/{{ $doc['tot_pares2'] }}/{{ $doc['tot_pares3'] }}/{{ $doc['tot_pares4'] }}
            </div>
        </div>
    @endfor
@endforeach
</body>
</html>
