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
            <p>Manual · {{ $etiquetaModulo }}</p>
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
            <a href="{{ route('ayuda') }}" class="mc-btn" title="Resúmenes por módulo">☰ Índice</a>
            @foreach ($atajos as $atajo)
                @if (Route::has($atajo['route']))
                    <a href="{{ route($atajo['route']) }}" class="mc-btn">{{ $atajo['label'] }}</a>
                @endif
            @endforeach
            <button type="button" id="mc-theme-toggle" class="mc-btn" title="Cambiar tema">Tema</button>
            <a href="{{ route($rutaPdf) }}" class="mc-btn" target="_blank" rel="noopener">PDF</a>
            <a href="{{ route($rutaWord) }}" class="mc-btn" target="_blank" rel="noopener">Word</a>
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
                        'configKey' => $configKey,
                        'imgPublicPrefix' => $imgPublicPrefix,
                    ])

                    @foreach ($sec['parrafos'] ?? [] as $p)
                        <p>{{ $p }}</p>
                    @endforeach

                    @foreach (['tabla', 'tabla2'] as $tablaKey)
                        @if (!empty($sec[$tablaKey]))
                            @if (!empty($sec[$tablaKey]['caption']))
                                <p class="table-caption">{{ $sec[$tablaKey]['caption'] }}</p>
                            @endif
                            <div class="mc-table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        @foreach ($sec[$tablaKey]['headers'] as $h)
                                            <th>{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($sec[$tablaKey]['rows'] as $row)
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
                    @endforeach

                    @if (!empty($sec['items']))
                        <ul>
                            @foreach ($sec['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach ($sec['parrafos2'] ?? [] as $p)
                        <p>{{ $p }}</p>
                    @endforeach

                    @php
                        $gruposHerramientas = $sec['herramientas_grupos'] ?? null;
                        if ($gruposHerramientas === null && !empty($sec['herramientas'])) {
                            $gruposHerramientas = [[
                                'titulo' => 'Herramientas de la pantalla',
                                'items' => $sec['herramientas'],
                            ]];
                        }
                    @endphp
                    @foreach ($gruposHerramientas ?? [] as $grupo)
                        @if (!empty($grupo['items']))
                            @include('manual.partials.herramientas-tabla', ['grupo' => $grupo])
                        @endif
                    @endforeach
                </article>
            @endforeach
        </main>
    </div>
</div>

<script src="{{ asset('assets/js/manual-stock.js') }}"></script>
</body>
</html>
