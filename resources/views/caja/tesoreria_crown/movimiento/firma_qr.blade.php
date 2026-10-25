<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $etiqueta }}</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f4f7fb; color: #17202a; }
        main { max-width: 28rem; margin: 0 auto; padding: 1rem; }
        h1 { font-size: 1.15rem; margin: 0 0 .25rem; }
        .meta, .aviso, .error { font-size: .9rem; }
        .error { background: #f8d7da; color: #721c24; padding: .6rem .75rem; border-radius: .35rem; }
        .aviso { background: #d4edda; color: #155724; padding: .6rem .75rem; border-radius: .35rem; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin: .5rem 0 1rem; }
        th, td { border: 1px solid #d5e1ea; padding: .35rem .5rem; font-size: .9rem; }
        th { background: #85c1e9; text-align: left; }
        td.num, th.num { text-align: right; }
        label { display: block; font-size: .85rem; margin-bottom: .2rem; }
        input { width: 100%; box-sizing: border-box; padding: .55rem .6rem; margin-bottom: .6rem; font-size: 1rem; }
        button { width: 100%; padding: .7rem; font-size: 1rem; background: #1e7e34; color: #fff; border: 0; border-radius: .35rem; }
        .caja { background: #fff; border: 1px solid #d5e1ea; border-radius: .4rem; padding: .75rem; margin-bottom: .75rem; }
    </style>
</head>
<body>
<main>
    <h1>{{ $etiqueta }}</h1>
    <p class="meta">
        {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }}
        {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($comprobante->numero) }}
        · {{ $comprobante->jornada->fecha->format('d/m/Y') }}
        · Caja {{ $comprobante->asignacion->deposito->codigo ?? '' }}
        · {{ $comprobante->asignacion->cajero->nombre ?? '' }}
    </p>
    @if ($aviso)
        <p class="aviso">{{ $aviso }}</p>
    @endif
    @if ($error)
        <p class="error">{{ $error }}</p>
    @endif
    @foreach ($grupos as $grupo)
        @php
            $filas = $grupo['filas']->filter(fn ($valor) => (float) ($cantidades[$valor->id] ?? 0) > 0);
            $total = 0;
        @endphp
        @if ($filas->isNotEmpty())
            <div class="caja">
                <strong>{{ $grupo['titulo'] }}</strong>
                <table>
                    <thead>
                        <tr><th>Denominación</th><th class="num">Cantidad</th><th class="num">Importe</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $valor)
                            @php
                                $cantidad = (float) $cantidades[$valor->id];
                                $importe = $cantidad * (float) $valor->denominacion;
                                $total += $importe;
                            @endphp
                            <tr>
                                <td>{{ rtrim(rtrim(number_format((float) $valor->denominacion, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($cantidad, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="num">{{ number_format($importe, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2">Total</th><th class="num">{{ number_format($total, 2, ',', '.') }}</th></tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endforeach
    @if ($bloqueado)
        <p class="meta">{{ $bloqueado }}</p>
    @else
        <form method="POST" action="{{ route('firmar_qr_tesoreria_crown_movimiento', $comprobante->qr_token) }}" autocomplete="off">
            @csrf
            <label for="firma_usuario">Tu usuario</label>
            <input type="text" name="firma_usuario" id="firma_usuario" autocomplete="username" required>
            <label for="firma_password">Tu clave</label>
            <input type="password" name="firma_password" id="firma_password" autocomplete="current-password" required>
            <button type="submit">Firmar</button>
        </form>
        <p class="meta">Hace falta tu usuario del sistema, el que está vinculado a este cajero. Abrir el código no firma.</p>
    @endif
</main>
</body>
</html>
