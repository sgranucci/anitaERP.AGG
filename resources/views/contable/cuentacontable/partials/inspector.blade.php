@php
    use App\Support\Contable\CuentacontableArbolSupport;
    $tipos = $tiposCuenta ?? CuentacontableArbolSupport::etiquetasTipo();
    $puedeEditar = $puedeEditarArbol ?? false;
@endphp
<aside class="pc-inspector" id="pc-inspector">
    <div class="pc-inspector__head">
        <div>
            <div class="text-muted small text-uppercase">Cuenta / bloque</div>
            <h4 class="pc-inspector__titulo mb-0" id="pc-insp-titulo">Elegí una cuenta</h4>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="pc-insp-cerrar" title="Cerrar panel" hidden>×</button>
    </div>

    <div class="pc-inspector__vacio" id="pc-insp-vacio">
        Clic en una cuenta del árbol. Acá se asigna el nivel de suma y se ve al instante cómo queda el bloque
        (ancestros + esta cuenta + hijas), sin ir al formulario clásico.
    </div>

    <form id="pc-insp-form" class="pc-inspector__form" hidden>
        @csrf
        <input type="hidden" name="id" id="pc-insp-id" value="">

        <p class="pc-inspector__hint mb-2">
            El código no se toca (Anita). “Colgar de” solo cambia la vista ERP. Nivel y nombre sí van al plan y a Anita.
        </p>

        <div class="form-group mb-2">
            <label class="small mb-1" for="pc-insp-nombre">Nombre</label>
            <input type="text" class="form-control form-control-sm" id="pc-insp-nombre" name="nombre" maxlength="100" @unless($puedeEditar) readonly @endunless>
        </div>

        <div class="form-group mb-2">
            <label class="small mb-1">Nivel de suma</label>
            <div class="pc-niveles" id="pc-insp-niveles" role="group" aria-label="Nivel">
                @for ($n = 1; $n <= 5; $n++)
                    <button type="button" class="pc-nivel" data-nivel="{{ $n }}" @unless($puedeEditar) disabled @endunless>
                        <span class="pc-nivel__n">N{{ $n }}</span>
                        <span class="pc-nivel__bar"></span>
                    </button>
                @endfor
            </div>
            <input type="hidden" name="nivel" id="pc-insp-nivel" value="1">
        </div>

        <div class="form-group mb-2">
            <label class="small mb-1" for="pc-insp-padre">Colgar de (grupo)</label>
            <select class="form-control form-control-sm" id="pc-insp-padre" name="parent_id" @unless($puedeEditar) disabled @endunless>
                <option value="">Automático por código</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group col-6 mb-2">
                <label class="small mb-1" for="pc-insp-tipo">Tipo</label>
                <select class="form-control form-control-sm" id="pc-insp-tipo" name="tipocuenta" @unless($puedeEditar) disabled @endunless>
                    @foreach ($tipos as $valor => $label)
                        <option value="{{ $valor }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-6 mb-2">
                <label class="small mb-1" for="pc-insp-rubro">Naturaleza</label>
                <select class="form-control form-control-sm" id="pc-insp-rubro" name="rubrocontable_id" @unless($puedeEditar) disabled @endunless>
                    @foreach ($rubrocontable_query ?? [] as $rubro)
                        <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="pc-preview" id="pc-preview">
            <div class="pc-preview__label">Así queda el bloque</div>
            <ul class="pc-preview__arbol mb-0" id="pc-preview-arbol"></ul>
        </div>

        <div class="d-flex flex-wrap align-items-center mt-3">
            @if ($puedeEditar)
                <button type="submit" class="btn btn-primary btn-sm mr-2" id="pc-insp-guardar">
                    Aplicar al plan
                </button>
            @endif
            <a href="#" class="btn btn-outline-secondary btn-sm" id="pc-insp-ficha">Ficha completa</a>
            <span class="text-muted small ml-2" id="pc-insp-estado"></span>
        </div>
    </form>
</aside>
