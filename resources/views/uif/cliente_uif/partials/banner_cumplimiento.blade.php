@php
    $cump = $cumplimientoUif ?? null;
    $cumpItems = $cump['items'] ?? [];
    $cumpTieneItems = count($cumpItems) > 0;
    $cumpTitulo = $cump['titulo'] ?? 'Faltan documentos o firmas UIF';
    $cumpSubtitulo = $cump['subtitulo'] ?? '';
    $cumpClase = $cump['claseBanner'] ?? 'is-danger';
    $cumpUrlsTab = $cump['urlsTab'] ?? [];
    $cumpEsExterno = ! empty($cumpEsExterno);
@endphp
<div id="uif-alertas-cumplimiento"
     class="uif-banner-cumplimiento{{ $cumpTieneItems ? ' '.$cumpClase : ' d-none' }}"
     role="alert"
     aria-live="polite"
     @if ($cumpEsExterno) data-uif-cumplimiento-externo="1" @endif>
    <div class="d-flex align-items-start">
        <div class="uif-banner-icono mr-3" aria-hidden="true">
            <i class="fa fa-exclamation-triangle"></i>
        </div>
        <div class="flex-grow-1" style="min-width: 0;">
            <h5 class="uif-banner-titulo mb-1">
                <span id="uif-alertas-cumplimiento-titulo">{{ $cumpTitulo }}</span>
                <span id="uif-alertas-cumplimiento-contador" class="badge badge-light ml-2">{{ $cumpTieneItems ? count($cumpItems) : '' }}</span>
            </h5>
            <p id="uif-alertas-cumplimiento-subtitulo" class="uif-banner-subtitulo mb-2{{ $cumpSubtitulo === '' ? ' d-none' : '' }}">{{ $cumpSubtitulo }}</p>
            <ul id="uif-alertas-cumplimiento-lista" class="uif-banner-lista mb-2 pl-3">
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
            <div id="uif-alertas-cumplimiento-acciones" class="d-flex flex-wrap uif-banner-acciones">
                @if ($cumpTieneItems)
                    @php
                        $tabsUsados = [];
                        foreach ($cumpItems as $item) {
                            if (is_array($item) && ! empty($item['tab'])) {
                                $tabsUsados[(string) $item['tab']] = true;
                            }
                        }
                        $botonesTab = [
                            '1' => 'Datos principales',
                            '2' => 'Datos UIF',
                            '5' => 'Archivos asociados',
                        ];
                    @endphp
                    @foreach ($botonesTab as $tabBtn => $labelBtn)
                        @if (! empty($tabsUsados[$tabBtn]))
                            @if (isset($cumpUrlsTab[$tabBtn]))
                                <a href="{{ $cumpUrlsTab[$tabBtn] }}" class="btn btn-sm btn-outline-dark">Ir a {{ $labelBtn }}</a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-dark" data-uif-ir-tab="{{ $tabBtn }}">Ir a {{ $labelBtn }}</button>
                            @endif
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
