<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['titulo'] }} — {{ $meta['subtitulo'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/manual-stock.css') }}">
</head>
<body class="manual-stock">
<div id="mc-progress" class="mc-progress"></div>

<div class="mc-shell">
    <aside class="mc-sidebar">
        <div class="mc-sidebar-brand">
            <h1>Anita ERP</h1>
            <p>Manual · Recepción y movimientos de stock</p>
        </div>
        <nav class="mc-nav">
            @foreach ($meta['secciones'] as $i => $sec)
                <a href="#cap-{{ $i }}">{{ $sec['titulo'] }}</a>
            @endforeach
        </nav>
    </aside>

    <div class="mc-main">
        <header class="mc-topbar">
            <div class="mc-search-wrap">
                <input type="search" id="mc-search" class="mc-search" placeholder="Buscar en el manual…" autocomplete="off">
            </div>
            <a href="{{ route('ayuda') }}" class="mc-btn" title="Resúmenes por módulo"><span aria-hidden="true">☰</span> Índice de manuales</a>
            <a href="{{ route('recepcion_proveedor') }}" class="mc-btn">Recepción</a>
            <a href="{{ route('movimientostock') }}" class="mc-btn">Movimientos</a>
            <button type="button" id="mc-theme-toggle" class="mc-btn" title="Cambiar tema">Tema</button>
            <a href="{{ route('manual_recepcion_movstock_pdf') }}" class="mc-btn" target="_blank" rel="noopener">PDF</a>
            <a href="{{ route('manual_recepcion_movstock_word') }}" class="mc-btn" target="_blank" rel="noopener">Word</a>
        </header>

        <main class="mc-content">
            <section class="mc-hero">
                <span class="mc-badge">v{{ $meta['version'] }}</span>
                <h2>{{ $meta['titulo'] }}</h2>
                <p style="font-size:1.1rem;color:var(--mc-muted);margin:0 0 1rem">{{ $meta['subtitulo'] }}</p>
                <div class="mc-hero-meta">
                    <span><strong>Empresa:</strong> {{ $meta['empresa'] }}</span>
                    <span><strong>Actualizado:</strong> {{ $meta['fecha'] }}</span>
                    <span><strong>Acceso:</strong>
                        <a href="{{ $meta['url_login'] }}" style="color:var(--mc-accent)">{{ $meta['url_login'] }}</a>
                    </span>
                </div>
            </section>

            @foreach ($meta['secciones'] as $i => $sec)
                <article id="cap-{{ $i }}" class="mc-chapter">
                    <h2>{{ $sec['titulo'] }}</h2>

                    @include('manual.partials.capturas-seccion', [
                        'sec' => $sec,
                        'configKey' => 'manual_recepcion_movstock',
                        'imgPublicPrefix' => 'docs/manual-recepcion-movstock/img',
                    ])

                    @foreach ($sec['parrafos'] ?? [] as $p)
                        <p>{{ $p }}</p>
                    @endforeach

                    @if (!empty($sec['items']))
                        <ul>
                            @foreach ($sec['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @include('stock.manual.partials.herramientas-seccion', ['sec' => $sec])

                    @if (!empty($sec['tabla']))
                        @if (!empty($sec['tabla']['caption']))
                            <p class="table-caption">{{ $sec['tabla']['caption'] }}</p>
                        @endif
                        <div class="mc-table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        @foreach ($sec['tabla']['headers'] as $h)
                                            <th>{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sec['tabla']['rows'] as $row)
                                        <tr>
                                            @foreach ($row as $cell)
                                                <td>{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            @endforeach

        </main>
    </div>
</div>

<script src="{{ asset('assets/js/manual-stock.js') }}"></script>
</body>
</html>
