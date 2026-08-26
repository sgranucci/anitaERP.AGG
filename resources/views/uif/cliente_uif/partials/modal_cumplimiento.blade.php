@php
    $cump = $cumplimientoUif ?? null;
    $cumpItems = $cump['items'] ?? [];
    $cumpTieneItems = count($cumpItems) > 0;
    $cumpTitulo = $cump['titulo'] ?? 'Faltan documentos o firmas UIF';
    $cumpSubtitulo = $cump['subtitulo'] ?? '';
    $cumpClase = $cump['claseBanner'] ?? 'is-danger';
    $cumpUrlsTab = $cump['urlsTab'] ?? [];
    $cumpHrefContinuar = $cumpHrefContinuar ?? '#';
    $cumpHrefFicha = $cumpHrefFicha ?? ($cumpUrlsTab['2'] ?? ($cumpUrlsTab['1'] ?? '#'));
    $headerClase = $cumpClase === 'is-warning' ? 'bg-warning' : 'bg-danger';
    $headerTexto = $cumpClase === 'is-warning' ? 'text-dark' : 'text-white';
@endphp
<div class="modal fade" id="uif-modal-cumplimiento" tabindex="-1" role="dialog" aria-labelledby="uif-modal-cumplimiento-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header {{ $headerClase }} {{ $headerTexto }}" id="uif-modal-cumplimiento-header">
                <h5 class="modal-title mb-0">
                    <i class="fa fa-exclamation-triangle mr-1" aria-hidden="true"></i>
                    <span id="uif-modal-cumplimiento-titulo">{{ $cumpTitulo }}</span>
                    <span id="uif-modal-cumplimiento-contador" class="badge badge-light ml-2">{{ $cumpTieneItems ? count($cumpItems) : '' }}</span>
                </h5>
                <button type="button" class="close {{ $headerTexto }}" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="uif-modal-cumplimiento-subtitulo" class="font-weight-bold{{ $cumpSubtitulo === '' ? ' d-none' : '' }}">{{ $cumpSubtitulo }}</p>
                <ul id="uif-modal-cumplimiento-lista" class="uif-banner-lista pl-3 mb-0">
                    @foreach ($cumpItems as $item)
                        @php
                            $textoItem = is_array($item) ? ($item['texto'] ?? '') : (string) $item;
                            $tabItem = is_array($item) ? (string) ($item['tab'] ?? '') : '';
                            $selectorItem = is_array($item) ? (string) ($item['selector'] ?? '') : '';
                            $hrefItem = ($tabItem !== '' && isset($cumpUrlsTab[$tabItem])) ? $cumpUrlsTab[$tabItem] : '';
                        @endphp
                        <li>
                            @if ($hrefItem !== '')
                                <a href="{{ $hrefItem }}" class="uif-banner-item-link">{{ $textoItem }}</a>
                            @elseif ($tabItem !== '' || $selectorItem !== '')
                                <a href="#" class="uif-banner-item-link" data-uif-ir-tab="{{ $tabItem }}" data-uif-selector="{{ $selectorItem }}">{{ $textoItem }}</a>
                            @else
                                {{ $textoItem }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="modal-footer">
                <a id="uif-modal-cumplimiento-ficha"
                   href="{{ $cumpHrefFicha }}"
                   class="btn btn-outline-dark{{ $cumpHrefFicha === '#' ? ' d-none' : '' }}">
                    Completar documentación
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <a id="uif-modal-cumplimiento-continuar" href="{{ $cumpHrefContinuar }}" class="btn btn-warning{{ $cumpHrefContinuar === '#' ? ' d-none' : '' }}">
                    Continuar con el premio
                </a>
            </div>
        </div>
    </div>
</div>
