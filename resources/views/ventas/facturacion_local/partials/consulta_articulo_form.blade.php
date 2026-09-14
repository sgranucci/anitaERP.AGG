@php
    $formId = $formId ?? 'fl-consulta-form';
    $puedeConsultarArticulo = \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar();
    $localesMeta = ($locales ?? collect())->map(static function ($loc) {
        $lista = $loc->listaprecio;

        return [
            'id' => (int) $loc->id,
            'codigo' => (string) ($loc->codigo ?? ''),
            'nombre' => (string) ($loc->nombre ?? ''),
            'listaprecio_id' => (int) ($loc->listaprecio_id ?? 0),
            'lista_codigo' => $lista ? (string) ($lista->codigo ?? '') : '',
            'lista_nombre' => $lista ? trim((string) (($lista->codigo ?? '').' '.($lista->nombre ?? ''))) : '',
            'anita_servidor' => (string) ($loc->anita_servidor ?? ''),
        ];
    })->values()->all();
@endphp
<form id="{{ $formId }}" class="fl-consulta-form" autocomplete="off" onsubmit="return false;">
    <div class="fl-consulta-bar">
        <div class="fl-consulta-bar-local">
            <label class="fl-consulta-label" for="{{ $formId }}-local">Local</label>
            <select name="local_id" id="{{ $formId }}-local" class="form-control form-control-lg fl-consulta-local" required>
                @foreach ($locales as $loc)
                    <option value="{{ $loc->id }}"
                        data-listaprecio-id="{{ (int) ($loc->listaprecio_id ?? 0) }}"
                        data-lista-nombre="{{ $loc->listaprecio ? trim(($loc->listaprecio->codigo ?? '').' '.($loc->listaprecio->nombre ?? '')) : '' }}"
                        @if ((int) $localId === (int) $loc->id) selected @endif>
                        {{ $loc->codigo }} — {{ $loc->nombre }}
                    </option>
                @endforeach
            </select>
            <div class="fl-consulta-lista-chip" id="{{ $formId }}-lista-chip" aria-live="polite">
                <i class="fa fa-tags"></i>
                <span class="fl-consulta-lista-chip-txt">Lista del local</span>
            </div>
        </div>

        <div class="fl-consulta-bar-articulo tm-articulo-campo" id="{{ $formId }}-articulo-campo">
            <label class="fl-consulta-label">Art&iacute;culo</label>
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 6px;">
                <input type="hidden" name="articulo_id" id="{{ $formId }}-articulo_id" class="articulo_id" value="">
                <button type="button"
                        title="Consulta art&iacute;culos (F1)"
                        class="btn btn-outline-primary consultaarticulo tooltipsC flex-shrink-0 fl-consulta-lupa"
                        data-solo-facturable="1"
                        id="{{ $formId }}-lupa">
                    <i class="fa fa-search"></i>
                </button>
                @if ($puedeConsultarArticulo)
                    <a href="#"
                       target="_blank"
                       rel="noopener"
                       class="btn-accion-tabla btn-link-articulo tooltipsC flex-shrink-0 d-none"
                       title="Consultar art&iacute;culo en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text"
                       class="form-control form-control-lg codigoarticulo flex-shrink-0"
                       id="{{ $formId }}-codigo"
                       name="codigo"
                       value=""
                       placeholder="SKU"
                       autocomplete="off"
                       autofocus
                       style="width: 8rem;">
                <input type="text"
                       class="form-control form-control-lg descripcionarticulo"
                       id="{{ $formId }}-descripcion"
                       value=""
                       placeholder="Descripci&oacute;n"
                       readonly
                       tabindex="-1"
                       style="min-width: 0; flex: 1 1 auto;">
                <button type="button"
                        class="btn btn-primary btn-lg flex-shrink-0"
                        id="{{ $formId }}-btn"
                        title="Consultar (Enter)">
                    <i class="fa fa-bolt"></i> Consultar
                </button>
            </div>
            <p class="fl-consulta-hint mb-0 mt-1">
                <kbd>F1</kbd> o lupa abre el modal &middot; <kbd>Enter</kbd> resuelve el SKU y consulta &middot; precio seg&uacute;n lista del local
            </p>
        </div>
    </div>
    <div class="fl-consulta-origen-row">
        <div class="custom-control custom-checkbox">
            <input type="hidden" name="origen_anita" value="0" id="{{ $formId }}-origen-hidden">
            <input type="checkbox" class="custom-control-input" id="{{ $formId }}-origen-anita"
                name="origen_anita" value="1" checked>
            <label class="custom-control-label" for="{{ $formId }}-origen-anita">
                Traer datos de Anita (Informix)
            </label>
        </div>
        <small class="text-muted ml-md-3">
            Con tilde = bridge Anita Local (prueba actual). Sin tilde = solo ERP (<code>articulo_movimiento</code> del dep&oacute;sito del local).
        </small>
    </div>
</form>
<script>
window.flLocalesMeta = @json($localesMeta);
</script>
