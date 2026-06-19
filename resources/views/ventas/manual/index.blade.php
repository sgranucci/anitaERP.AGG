<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['titulo'] }} — {{ $meta['subtitulo'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/manual-gastronomia.css') }}">
</head>
<body class="manual-gastronomia">
<div id="mc-progress" class="mc-progress"></div>

<div class="mc-shell">
    <aside class="mc-sidebar">
        <div class="mc-sidebar-brand">
            <h1>Anita ERP</h1>
            <p>Manual de usuario · Gastronomía</p>
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
            <a href="{{ url('/') }}" class="mc-btn">ERP</a>
            <button type="button" id="mc-theme-toggle" class="mc-btn" title="Cambiar tema">Tema</button>
            <a href="{{ route('manual_gastronomia_pdf') }}" class="mc-btn" target="_blank" rel="noopener">PDF</a>
            <a href="{{ route('manual_gastronomia_word') }}" class="mc-btn" target="_blank" rel="noopener">Word</a>
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
                <div class="mc-nota-agg">
                    <strong>Secciones específicas AGG:</strong> el capítulo
                    «Waitry y canjes Wigos» aplica a la operación con tótems/kioscos Waitry
                    e integración Wigos (premios y fidelidad). En otros despliegues del ERP
                    esas funciones pueden estar deshabilitadas.
                </div>
            </section>

            @foreach ($meta['secciones'] as $i => $sec)
                <article id="cap-{{ $i }}" class="mc-chapter">
                    <h2>{{ $sec['titulo'] }}</h2>

                    @include('manual.partials.capturas-seccion', [
                        'sec' => $sec,
                        'configKey' => 'manual_gastronomia',
                        'imgPublicPrefix' => 'docs/manual-gastronomia/img',
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

                    @include('ventas.manual.partials.herramientas-seccion', ['sec' => $sec])

                    @if (!empty($sec['tabla']))
                        @include('ventas.manual.partials.tabla-seccion', ['tabla' => $sec['tabla']])
                    @endif
                    @if (!empty($sec['tabla2']))
                        @include('ventas.manual.partials.tabla-seccion', ['tabla' => $sec['tabla2']])
                    @endif
                </article>
            @endforeach

        </main>
    </div>
</div>

<script src="{{ asset('assets/js/manual-gastronomia.js') }}"></script>
</body>
</html>
