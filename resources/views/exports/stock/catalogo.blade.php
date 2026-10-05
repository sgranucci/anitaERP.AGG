<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cat&aacute;logo de productos Calzados Ferli S.A.</title>
    <style type="text/css">
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #212529; margin: 12px 16px; }
        h1 { font-size: 28px; margin: 0 0 18px 0; }
        .datos-linea { margin: 8px 0 10px 0; line-height: 1.35; }
        table.catalogo { width: 100%; border-collapse: collapse; margin: 0 0 12px 0; }
        table.catalogo th,
        table.catalogo td { border: 1px solid #dee2e6; padding: 6px 8px; vertical-align: middle; }
        table.catalogo th { text-align: center; font-weight: bold; }
        table.catalogo td.codigo,
        table.catalogo td.descripcion { text-align: center; }
        table.catalogo td.foto { text-align: center; width: 240px; }
        table.catalogo td.precios { text-align: left; white-space: nowrap; }
        table.catalogo img { width: 220px; height: 220px; }
    </style>
</head>
<body>
    <h1>L&iacute;nea: {{ $items[0]['linea'] ?? '' }}</h1>
    @php
        $linea_actual = '';
        $muestraPrecios = ($flPrecio ?? '') === 'S';
    @endphp
    @foreach ($items ?? [] as $data)
        @if ($data['linea_id'] != $linea_actual)
            @if ($linea_actual != '')
                </tbody>
                </table>
                <div style="page-break-after:always;"></div>
                <h1>L&iacute;nea: {{ $data['linea'] ?? '' }}</h1>
            @endif
            @php $linea_actual = $data['linea_id']; @endphp
            <div class="datos-linea">
                <strong>Numeraci&oacute;n: {{ $data['numeracion'] ?? '' }}</strong><br>
                <strong>Capellada: {{ $data['material'] ?? '' }}</strong><br>
                <strong>Forro: {{ $data['forro'] ?? '' }}</strong><br>
                <strong>Fondo: {{ $data['fondo'] ?? '' }}</strong>
            </div>
            <table class="catalogo">
                @foreach ($modulos ?? [] as $mod)
                    @php $modulo_nombre = ''; @endphp
                    <tr>
                        <th>M&oacute;dulo</th>
                        @foreach ($mod as $talles)
                            <th>{{ $talles->talle }}</th>
                            @php $modulo_nombre = $talles->modulo_nombre; @endphp
                        @endforeach
                    </tr>
                    <tr>
                        <th>{{ $modulo_nombre }}</th>
                        @foreach ($mod as $talles)
                            <th>{{ $talles->cantidad }}</th>
                        @endforeach
                    </tr>
                @endforeach
            </table>

            <table class="catalogo">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>C&oacute;digo</th>
                        <th>Descripci&oacute;n</th>
                        @if ($muestraPrecios)
                            <th>Precios</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
        @endif
        @php
            $rutaFoto = \App\Support\Stock\ArticuloCombinacionFotoSupport::rutaAbsoluta(
                $data['foto'] ?? '',
                $data['sku'] ?? '',
                $data['codigo'] ?? ''
            );
        @endphp
        <tr>
            <td class="foto">
                @if ($rutaFoto)
                    <img src="{{ $rutaFoto }}" width="220" height="220" alt="" />
                @endif
            </td>
            <td class="codigo">
                <small>{{ $data['sku'] ?? '' }}-{{ $data['codigo'] ?? '' }}</small>
            </td>
            <td class="descripcion">
                <small>{{ $data['nombre'] ?? '' }}</small>
            </td>
            @if ($muestraPrecios)
                <td class="precios">
                    <small>
                        @if (($data['precio1'] ?? 0) != 0)
                            {{ $data['nombrelista1'] }} {{ number_format($data['precio1'], 2) }}<br>
                        @endif
                        @if (($data['precio2'] ?? 0) != 0)
                            {{ $data['nombrelista2'] }} {{ number_format($data['precio2'], 2) }}<br>
                        @endif
                        @if (($data['precio3'] ?? 0) != 0)
                            {{ $data['nombrelista3'] }} {{ number_format($data['precio3'], 2) }}<br>
                        @endif
                        @if (($data['precio4'] ?? 0) != 0)
                            {{ $data['nombrelista4'] }} {{ number_format($data['precio4'], 2) }}
                        @endif
                    </small>
                </td>
            @endif
        </tr>
    @endforeach
    </tbody>
    </table>
</body>
</html>
