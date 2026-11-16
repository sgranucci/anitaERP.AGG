@extends("theme.$theme.layout")

@section('titulo')
    Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero d-flex flex-wrap justify-content-between align-items-start" style="gap:1rem;">
        <div>
            <div class="ci-hero-kicker">Dirección · Modelo semántico</div>
            <h1>Consulta Inteligente</h1>
            <p>
                Explorá entidades del ERP con el mismo QBE y workbench de los índices,
                sin armar un informe a medida. Elegí una fuente, aplicá criterios y abrí el comprobante.
            </p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('tablero_consulta_inteligente') }}" class="btn btn-light btn-sm">
                <i class="fa fa-th-large"></i> Tablero
            </a>
            <a href="{{ route('cruzar_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-columns"></i> Cruce
            </a>
            <a href="{{ route('unir_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-link"></i> Join
            </a>
            <div class="dropdown d-inline-block">
                <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="ci-mas-acciones"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Más
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="ci-mas-acciones">
                    <a class="dropdown-item" href="{{ route('snapshots_consulta_inteligente') }}">
                        <i class="fa fa-camera fa-fw"></i> Snapshots
                    </a>
                    <a class="dropdown-item" href="{{ route('auditoria_consulta_inteligente') }}">
                        <i class="fa fa-history fa-fw"></i> Auditoría
                    </a>
                    @if (! empty($puedeConfigurar))
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('diccionario_consulta_inteligente') }}">
                            <i class="fa fa-book fa-fw"></i> Diccionario NL
                        </a>
                        <a class="dropdown-item" href="{{ route('configuracion_consulta_inteligente') }}">
                            <i class="fa fa-cog fa-fw"></i> Alertas
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('includes.mensaje')

    <form method="post" action="{{ route('preguntar_consulta_inteligente') }}" class="ci-busqueda" id="ci-form-nl">
        @csrf
        <label class="mb-1 small text-muted" for="ci-pregunta">Pregunta en lenguaje natural</label>
        <div class="form-row align-items-end">
            <div class="form-group col-md-9 mb-md-0 position-relative">
                <input type="text" id="ci-pregunta" name="pregunta" class="form-control form-control-lg"
                       value="{{ $pregunta ?? '' }}"
                       placeholder="Ej. pedidos pendientes este mes · facturas de venta sin remito · cheques mayor a 100000"
                       autocomplete="off" autofocus
                       role="combobox" aria-autocomplete="list" aria-controls="ci-sugerencias-lista" aria-expanded="false">
                <div id="ci-sugerencias-lista" class="ci-sugerencias-lista" hidden role="listbox"></div>
            </div>
            <div class="form-group col-md-3 mb-0">
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fa fa-magic"></i> Armar QBE
                </button>
            </div>
        </div>
        @if (! empty($nlSugerencias))
            <div class="ci-sugerencias-chips mt-2" aria-label="Sugerencias">
                @foreach ($nlSugerencias as $sug)
                    <button type="button" class="ci-sug-chip" data-ci-pregunta="{{ $sug['texto'] }}">{{ $sug['texto'] }}</button>
                @endforeach
            </div>
        @endif
        <div class="d-flex flex-wrap align-items-center justify-content-between mt-2" style="gap:.5rem;">
            <div class="small text-muted mb-0">
                Arma el QBE (período, estado, importe, cliente/proveedor).
                @if (! empty($llmHabilitado))
                    Si no hay match local, usa LLM
                    @if (! empty($llmCadena) && is_array($llmCadena))
                        ({{ implode(' → ', $llmCadena) }}).
                    @else
                        externo.
                    @endif
                @else
                    Diccionario + patrones locales (LLM opcional vía <code>CI_LLM_*</code>).
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center" style="gap:.85rem;">
                @if (! empty($nlContexto))
                    <label class="small mb-0 text-muted" title="Combina con: {{ $nlContexto['pregunta'] }}">
                        <input type="checkbox" name="refinar" value="1" @checked(old('refinar', request('refinar')))>
                        Refinar última
                    </label>
                @endif
                <label class="small mb-0 text-muted">
                    <input type="checkbox" name="abrir_directo" value="1" @checked(old('abrir_directo', request('abrir_directo')))>
                    Abrir directo sin preview
                </label>
            </div>
        </div>
        @if (! empty($nlTurnos))
            <div class="ci-nl-contexto mt-2">
                <span class="small text-muted">
                    Cadena ({{ count($nlTurnos) }}/{{ \App\Support\ConsultaInteligente\ConsultaInteligenteNlContextoSupport::MAX_TURNOS }}):
                    <em>{{ $nlCadena }}</em>
                </span>
                <a href="{{ route('deshacer_contexto_consulta_inteligente') }}" class="btn btn-link btn-sm p-0 ml-2">Deshacer</a>
                <a href="{{ route('limpiar_contexto_consulta_inteligente') }}" class="btn btn-link btn-sm p-0 ml-1">Limpiar</a>
            </div>
        @endif
        @if (! empty($llmAvisoPrivacidad))
            <div class="ci-nl-privacidad mt-2 @if (! empty($llmHabilitado)) ci-nl-privacidad-activa @endif" role="note">
                <i class="fa fa-shield-alt"></i>
                {{ $llmAvisoPrivacidad }}
                @if (! empty($llmHabilitado))
                    <span class="d-block mt-1">Con LLM activo la frase (sanitizada) puede salir del servidor hacia el proveedor configurado.</span>
                @endif
            </div>
        @endif
    </form>

    @if (! empty($nlResultado) && empty($nlResultado['ok']))
        <div class="alert alert-warning py-2">{{ $nlResultado['mensaje'] }}</div>
    @endif
    @if (! empty($nlResultado['ok']) && ! empty($nlResultado['url']))
        <div class="ci-nl-preview mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start" style="gap:.75rem;">
                <div class="flex-grow-1">
                    @if (! empty($nlResultado['narrativa']['texto']))
                        <div class="ci-nl-preview-kicker">Respuesta</div>
                        <div class="ci-nl-narrativa">{{ $nlResultado['narrativa']['texto'] }}</div>
                        @if (! empty($nlResultado['narrativa']['aging']['buckets']))
                            <div class="ci-nl-aging mt-2">
                                @foreach ($nlResultado['narrativa']['aging']['buckets'] as $b)
                                    @if (($b['n'] ?? 0) > 0)
                                        <span class="ci-nl-aging-pill">{{ $b['label'] }} <strong>{{ $b['n'] }}</strong></span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="ci-nl-preview-kicker {{ ! empty($nlResultado['narrativa']) ? 'mt-3' : '' }}">QBE armado</div>
                    <strong>{{ $nlResultado['explicacion'] ?? 'Consulta lista' }}</strong>
                    @if (! empty($nlResultado['qbe_legible']) && is_array($nlResultado['qbe_legible']))
                        <ul class="ci-nl-preview-list mb-0 mt-2">
                            @foreach ($nlResultado['qbe_legible'] as $linea)
                                <li>{{ $linea }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div id="ci-enlace-resultado" class="ci-enlace-box mt-2" hidden>
                        <label class="small text-muted mb-1 d-block">Enlace compartible</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="ci-enlace-url" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="ci-enlace-copiar">Copiar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column" style="gap:.4rem;">
                    <a href="{{ $nlResultado['url'] }}" class="btn btn-info btn-sm">
                        <i class="fa fa-external-link-alt"></i> Abrir en explorador
                    </a>
                    <form method="post" action="{{ route('guardar_favorito_consulta_inteligente') }}">
                        @csrf
                        <input type="hidden" name="pregunta" value="{{ $pregunta }}">
                        <input type="hidden" name="titulo" value="{{ $nlResultado['explicacion'] ?? $pregunta }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm btn-block">
                            <i class="fa fa-star"></i> Guardar favorito
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ci-btn-compartir"
                            data-pregunta="{{ $pregunta }}"
                            data-titulo="{{ $nlResultado['explicacion'] ?? $pregunta }}"
                            data-entidad="{{ $nlResultado['entidad'] ?? '' }}"
                            data-query="{{ e(json_encode($nlResultado['query'] ?? [])) }}"
                            data-url="{{ route('crear_enlace_consulta_inteligente') }}"
                            data-csrf="{{ csrf_token() }}">
                        <i class="fa fa-link"></i> Compartir enlace
                    </button>
                    <form method="post" action="{{ route('pdf_pregunta_consulta_inteligente') }}">
                        @csrf
                        <input type="hidden" name="pregunta" value="{{ $pregunta }}">
                        <button type="submit" class="btn btn-outline-danger btn-sm btn-block">
                            <i class="fa fa-file-pdf"></i> PDF pregunta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($nlFavoritos) || ! empty($nlHistorial))
        <div class="ci-nl-atajos mb-3">
            @if (! empty($nlFavoritos))
                <div class="ci-nl-atajos-bloque">
                    <div class="ci-nl-atajos-label"><i class="fa fa-star"></i> Favoritos</div>
                    <div class="ci-atajos" aria-label="Favoritos NL">
                        @foreach ($nlFavoritos as $fav)
                            <span class="ci-atajo ci-atajo-fav">
                                <form method="post" action="{{ route('preguntar_consulta_inteligente') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="pregunta" value="{{ $fav['pregunta'] }}">
                                    <button type="submit" class="ci-atajo-btn" title="{{ $fav['pregunta'] }}">{{ $fav['titulo'] }}</button>
                                </form>
                                <form method="post" action="{{ route('eliminar_favorito_consulta_inteligente', ['id' => $fav['id']]) }}"
                                      class="d-inline" onsubmit="return confirm('¿Quitar favorito?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ci-atajo-x" title="Quitar">&times;</button>
                                </form>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
            @if (! empty($nlHistorial))
                <div class="ci-nl-atajos-bloque mt-2">
                    <div class="ci-nl-atajos-label"><i class="fa fa-history"></i> Recientes</div>
                    <div class="ci-atajos" aria-label="Historial NL">
                        @foreach ($nlHistorial as $h)
                            <form method="post" action="{{ route('preguntar_consulta_inteligente') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="pregunta" value="{{ $h['pregunta'] }}">
                                <button type="submit"
                                        class="ci-atajo ci-atajo-btn {{ empty($h['ok']) ? 'ci-atajo-warn' : '' }}"
                                        title="{{ $h['cuando'] }}{{ $h['entidad'] ? ' · '.$h['entidad'] : '' }}">
                                    {{ \Illuminate\Support\Str::limit($h['pregunta'], 42) }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if (! empty($alertas))
        <div class="ci-atajos" aria-label="Alertas operativas">
            @foreach ($alertas as $alerta)
                <a href="{{ $alerta['url'] }}" class="ci-atajo" title="{{ $alerta['detalle'] }}"
                   style="border-color: {{ $alerta['color'] }}; color: #17202A; background: #fff;">
                    <span class="ci-atajo-dot" style="background:{{ $alerta['color'] }}"></span>
                    {{ $alerta['titulo'] }}
                </a>
            @endforeach
        </div>
    @endif

    <form method="get" action="{{ route('consulta_inteligente') }}" class="ci-busqueda" id="ci-form-catalogo">
        <div class="form-row align-items-end">
            <div class="form-group col-md-7 mb-md-0">
                <label class="mb-1 small text-muted" for="ci-q">Filtrar catálogo</label>
                <input type="search" id="ci-q" name="q" class="form-control"
                       value="{{ $filtroTexto }}"
                       placeholder="Ej. cheques, facturas, pagos, proveedores…"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-3 mb-md-0">
                <label class="mb-1 small text-muted" for="ci-modulo">Módulo</label>
                <select id="ci-modulo" name="modulo" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($modulos as $mod)
                        <option value="{{ $mod }}" @selected($filtroModulo === $mod)>{{ $mod }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 mb-0">
                <button type="submit" class="btn btn-info btn-block">
                    <i class="fa fa-search"></i> Filtrar
                </button>
            </div>
        </div>
        @if ($modulos !== [])
            <div class="ci-chips">
                <a href="{{ route('consulta_inteligente', array_filter(['q' => $filtroTexto ?: null])) }}"
                   class="ci-chip {{ $filtroModulo === '' ? 'active' : '' }}">Todos</a>
                @foreach ($modulos as $mod)
                    <a href="{{ route('consulta_inteligente', array_filter(['modulo' => $mod, 'q' => $filtroTexto ?: null])) }}"
                       class="ci-chip {{ $filtroModulo === $mod ? 'active' : '' }}">{{ $mod }}</a>
                @endforeach
            </div>
        @endif
    </form>

    @if ($plantillas !== [])
        <div class="ci-atajos" aria-label="Consultas rápidas">
            @foreach ($plantillas as $atajo)
                <a href="{{ $atajo['url'] }}" class="ci-atajo" title="{{ $atajo['descripcion'] }}">
                    <span class="ci-atajo-dot" style="background:{{ $atajo['color'] }}"></span>
                    {{ $atajo['titulo'] }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($catalogo === [])
        <div class="ci-vacio">
            <p class="mb-1"><i class="fa fa-lock fa-lg mb-2 d-block"></i></p>
            <strong>No hay entidades disponibles</strong>
            <p class="mb-0 mt-1">
                @if ($filtroTexto !== '' || $filtroModulo !== '')
                    Probá limpiar el filtro o
                    <a href="{{ route('consulta_inteligente') }}">ver todas</a>.
                @else
                    Pedí al administrador el permiso de listar de Caja o Compras (cheques, facturas, pagos, proveedores).
                @endif
            </p>
        </div>
    @else
        <div class="ci-grid">
            @foreach ($catalogo as $item)
                <article class="ci-card" style="border-left-color: {{ $item['color'] }}">
                    <div class="ci-card-mod">{{ $item['modulo'] }}</div>
                    <div class="ci-card-head">
                        <div class="ci-card-ico" style="background: {{ $item['color'] }}">
                            <i class="fas {{ $item['icono'] }}"></i>
                        </div>
                        <h2>{{ $item['nombre'] }}</h2>
                    </div>
                    <p>{{ $item['descripcion'] }}</p>
                    @if ($item['plantillas'] !== [])
                        <div class="ci-card-plantillas">
                            @foreach ($item['plantillas'] as $p)
                                <a class="ci-mini" href="{{ $p['url'] }}">{{ $p['titulo'] }}</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="ci-card-actions">
                        <a href="{{ $item['url_explorar'] }}" class="btn btn-info btn-sm">
                            <i class="fa fa-search"></i> Explorar
                        </a>
                        <a href="{{ $item['url_nativa'] }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                            Módulo completo
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="ci-hint mb-0">
            <i class="fa fa-lightbulb"></i>
            Preguntá arriba, abrí una entidad o usá una alerta. Desde el explorador podés fijar consultas en el tablero, cruzar entidades y programar Excel por mail.
        </p>
    @endif
</div>
@endsection

@section('scripts')
<script>
window.CI_SUGERENCIAS_URL = @json($nlSugerenciasUrl ?? '');
(function () {
    var input = document.getElementById('ci-pregunta');
    var lista = document.getElementById('ci-sugerencias-lista');
    if (!input || !lista || !window.CI_SUGERENCIAS_URL) {
        return;
    }
    var timer = null;
    var items = [];
    var active = -1;

    function ocultar() {
        lista.hidden = true;
        lista.innerHTML = '';
        active = -1;
        input.setAttribute('aria-expanded', 'false');
    }

    function mostrar(rows) {
        items = rows || [];
        if (!items.length) {
            ocultar();
            return;
        }
        lista.innerHTML = items.map(function (it, i) {
            var g = it.grupo ? '<span class="ci-sug-grupo">' + it.grupo + '</span>' : '';
            return '<button type="button" class="ci-sug-item" role="option" data-i="' + i + '">' +
                '<span>' + (it.texto || '') + '</span>' + g + '</button>';
        }).join('');
        lista.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        lista.querySelectorAll('.ci-sug-item').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                elegir(parseInt(btn.getAttribute('data-i'), 10));
            });
        });
    }

    function elegir(i) {
        if (!items[i]) return;
        input.value = items[i].texto;
        ocultar();
        input.focus();
    }

    function buscar() {
        var q = (input.value || '').trim();
        if (q.length < 2) {
            ocultar();
            return;
        }
        fetch(window.CI_SUGERENCIAS_URL + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            mostrar((data && data.items) || []);
        }).catch(function () { ocultar(); });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(buscar, 220);
    });
    input.addEventListener('keydown', function (e) {
        if (lista.hidden) return;
        var btns = lista.querySelectorAll('.ci-sug-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active = Math.min(active + 1, btns.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = Math.max(active - 1, 0);
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            elegir(active);
            return;
        } else if (e.key === 'Escape') {
            ocultar();
            return;
        } else {
            return;
        }
        btns.forEach(function (b, i) { b.classList.toggle('active', i === active); });
    });
    input.addEventListener('blur', function () { setTimeout(ocultar, 150); });

    document.querySelectorAll('[data-ci-pregunta]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            input.value = chip.getAttribute('data-ci-pregunta') || '';
            input.focus();
        });
    });

    var btnShare = document.getElementById('ci-btn-compartir');
    if (btnShare) {
        btnShare.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('_token', btnShare.getAttribute('data-csrf') || '');
            fd.append('pregunta', btnShare.getAttribute('data-pregunta') || '');
            fd.append('titulo', btnShare.getAttribute('data-titulo') || '');
            fd.append('entidad', btnShare.getAttribute('data-entidad') || '');
            fd.append('query_json', btnShare.getAttribute('data-query') || '{}');
            fetch(btnShare.getAttribute('data-url'), {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.ok || !data.url) {
                    alert((data && data.mensaje) || 'No se pudo crear el enlace.');
                    return;
                }
                var box = document.getElementById('ci-enlace-resultado');
                var urlInput = document.getElementById('ci-enlace-url');
                if (box && urlInput) {
                    urlInput.value = data.url;
                    box.hidden = false;
                    urlInput.select();
                }
            }).catch(function () { alert('No se pudo crear el enlace.'); });
        });
    }
    var btnCopy = document.getElementById('ci-enlace-copiar');
    if (btnCopy) {
        btnCopy.addEventListener('click', function () {
            var urlInput = document.getElementById('ci-enlace-url');
            if (!urlInput) return;
            urlInput.select();
            try {
                navigator.clipboard.writeText(urlInput.value);
            } catch (e) {
                document.execCommand('copy');
            }
        });
    }
})();
</script>
@endsection
