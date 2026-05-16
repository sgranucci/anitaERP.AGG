<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['titulo'] }} — {{ $meta['subtitulo'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/manual-compras.css') }}">
</head>
<body class="manual-compras">
<div id="mc-progress" class="mc-progress"></div>

<div class="mc-shell">
    <aside class="mc-sidebar">
        <div class="mc-sidebar-brand">
            <h1>Anita ERP</h1>
            <p>Centro de ayuda · Compras</p>
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
            <a href="{{ route('ayuda') }}" class="mc-btn"><span aria-hidden="true">←</span> Centro de ayuda</a>
            <a href="{{ url('/') }}" class="mc-btn">ERP</a>
            <button type="button" id="mc-theme-toggle" class="mc-btn" title="Cambiar tema">Tema</button>
            <a href="{{ route('manual_compras_pdf') }}" class="mc-btn" target="_blank" rel="noopener">PDF</a>
            <a href="{{ route('manual_compras_word') }}" class="mc-btn" target="_blank" rel="noopener">Word</a>
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

            @php
                $capturas = config('manual_compras.capturas', []);
                $capturaPorSeccion = collect($capturas)->keyBy('seccion');
            @endphp

            @foreach ($meta['secciones'] as $i => $sec)
                <article id="cap-{{ $i }}" class="mc-chapter">
                    <h2>{{ $sec['titulo'] }}</h2>

                    @php $cap = $capturaPorSeccion->get($sec['titulo']); @endphp
                    @if ($cap)
                        @php
                            $baseName = preg_replace('/\.(svg|png)$/i', '', $cap['archivo']);
                            $imgDir = public_path('docs/manual-compras/img');
                            $imgFile = is_file($imgDir . '/' . $baseName . '.png')
                                ? $baseName . '.png'
                                : (is_file($imgDir . '/' . $baseName . '.svg') ? $baseName . '.svg' : null);
                        @endphp
                        @if ($imgFile)
                        <figure class="mc-figure">
                            <img src="{{ asset('docs/manual-compras/img/' . $imgFile) }}" alt="{{ $cap['titulo'] }}" loading="lazy">
                            <figcaption>{{ $cap['titulo'] }}</figcaption>
                        </figure>
                        @endif
                    @endif

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

<script src="{{ asset('assets/js/manual-compras.js') }}"></script>
</body>
</html>
