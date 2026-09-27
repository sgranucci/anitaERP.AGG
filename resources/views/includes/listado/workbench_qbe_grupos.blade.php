{{-- QBE multi-grupo: AND/OR dentro del grupo, NOT, Y/O entre grupos --}}
@php
    use App\Support\Listado\ListadoQbeSupport;
    $qbeUi = ListadoQbeSupport::paraUi($qbeEstructura ?? []);
    $entreGrupos = $qbeUi['entre_grupos'];
    $grupos = $qbeUi['grupos'];
    $opsTexto = $opsTexto ?? [];
    $opsEntero = $opsEntero ?? [];
    $opsBool = $opsBool ?? [];
    $opsFecha = $opsFecha ?? [];
    $opsDecimal = $opsDecimal ?? \App\Support\Listado\ListadoQbeSupport::OPERADORES_DECIMAL;
    $camposQbe = $camposQbe ?? [];
    $etiquetasColumnas = $etiquetasColumnas ?? [];
    $maxGrupos = ListadoQbeSupport::MAX_GRUPOS;
@endphp
<input type="hidden" name="qbe[entre_grupos]" id="lw-qbe-entre-grupos" value="{{ $entreGrupos }}">
<div id="lw-qbe-grupos" data-max-grupos="{{ $maxGrupos }}">
    @foreach ($grupos as $gi => $grupo)
        @if ($gi > 0)
            <div class="lw-qbe-entre text-center my-2">
                <div class="btn-group btn-group-sm lw-qbe-entre-toggle" role="group" data-entre-for="{{ $gi }}">
                    <button type="button" class="btn btn-outline-secondary lw-entre-and {{ $entreGrupos === 'and' ? 'active' : '' }}" data-logic="and">Y</button>
                    <button type="button" class="btn btn-outline-secondary lw-entre-or {{ $entreGrupos === 'or' ? 'active' : '' }}" data-logic="or">O</button>
                </div>
            </div>
        @endif
        <div class="lw-qbe-grupo card card-outline card-info mb-2" data-grupo-idx="{{ $gi }}">
            <div class="card-header py-1 px-2 d-flex flex-wrap align-items-center justify-content-between" style="gap:.35rem;">
                <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                    <strong class="small mb-0">Grupo {{ $gi + 1 }}</strong>
                    <select name="qbe[grupos][{{ $gi }}][logic]" class="form-control form-control-sm lw-qbe-grupo-logic" style="width:auto;min-width:7rem;">
                        <option value="and" @if (($grupo['logic'] ?? 'and') === 'and') selected @endif>Todos (Y)</option>
                        <option value="or" @if (($grupo['logic'] ?? '') === 'or') selected @endif>Alguno (O)</option>
                    </select>
                    <div class="custom-control custom-checkbox mb-0">
                        <input type="hidden" name="qbe[grupos][{{ $gi }}][not]" value="0">
                        <input type="checkbox" class="custom-control-input lw-qbe-grupo-not" id="lw-qbe-not-{{ $gi }}"
                               name="qbe[grupos][{{ $gi }}][not]" value="1"
                               @if (! empty($grupo['not'])) checked @endif>
                        <label class="custom-control-label small" for="lw-qbe-not-{{ $gi }}">NOT</label>
                    </div>
                </div>
                <div class="d-flex" style="gap:.25rem;">
                    <button type="button" class="btn btn-xs btn-outline-primary lw-qbe-add-criterio-grupo" title="Agregar criterio">
                        <i class="fa fa-plus"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger lw-qbe-remove-grupo" title="Quitar grupo">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-2 px-2 lw-qbe-criterios-grupo">
                @foreach (($grupo['criterios'] ?? []) as $ci => $c)
                    @php
                        $campoAct = $c['campo'] ?? 'nombre';
                        $formulaAct = trim((string) ($c['formula'] ?? ''));
                        if ($formulaAct !== '' || $campoAct === \App\Support\Listado\ListadoQbeFormulaSupport::CAMPO_KEY) {
                            $tipo = \App\Support\Listado\ListadoQbeFormulaSupport::inferirTipo($formulaAct !== '' ? $formulaAct : 'UPPER({nombre})');
                        } else {
                            $tipo = $camposQbe[$campoAct]['type'] ?? 'texto';
                        }
                        $ops = match ($tipo) {
                            'entero' => $opsEntero,
                            'booleano' => $opsBool,
                            'fecha' => $opsFecha,
                            'decimal' => $opsDecimal,
                            default => $opsTexto,
                        };
                        $opAct = $c['op'] ?? 'contiene';
                    @endphp
                    <div class="lw-qbe-row form-row align-items-end mb-2" data-idx="{{ $ci }}">
                        <div class="form-group col-md-3 mb-1">
                            <label class="small mb-0">Campo</label>
                            <select name="qbe[grupos][{{ $gi }}][criterios][{{ $ci }}][campo]" class="form-control form-control-sm lw-qbe-campo">
                                @foreach ($camposQbe as $key => $meta)
                                    <option value="{{ $key }}" data-type="{{ $meta['type'] }}"
                                        @if ($key === $campoAct && $formulaAct === '') selected @endif>
                                        {{ $etiquetasColumnas[$key] ?? $meta['label'] }}
                                    </option>
                                @endforeach
                                <option value="{{ \App\Support\Listado\ListadoQbeFormulaSupport::CAMPO_KEY }}" data-type="formula"
                                    @if ($formulaAct !== '' || $campoAct === \App\Support\Listado\ListadoQbeFormulaSupport::CAMPO_KEY) selected @endif>
                                    Fórmula…
                                </option>
                            </select>
                        </div>
                        <div class="form-group col-md-12 mb-1 lw-qbe-formula-wrap {{ $formulaAct !== '' || $campoAct === \App\Support\Listado\ListadoQbeFormulaSupport::CAMPO_KEY ? '' : 'd-none' }}">
                            <label class="small mb-0">Fórmula</label>
                            <input type="text" name="qbe[grupos][{{ $gi }}][criterios][{{ $ci }}][formula]"
                                   class="form-control form-control-sm lw-qbe-formula font-monospace"
                                   value="{{ $formulaAct }}" autocomplete="off"
                                   placeholder="LENGTH({nombre}) · UPPER({codigo}) · TRIM({domicilio}) · CONCAT({codigo},'-',{nombre})"
                                   title="Funciones: LENGTH UPPER LOWER TRIM CONCAT · Campos: {clave}">
                        </div>
                        <div class="form-group col-md-2 mb-1">
                            <label class="small mb-0">Operador</label>
                            <select name="qbe[grupos][{{ $gi }}][criterios][{{ $ci }}][op]" class="form-control form-control-sm lw-qbe-op">
                                @foreach ($ops as $opKey => $opLabel)
                                    <option value="{{ $opKey }}" @if ($opAct === $opKey) selected @endif>{{ $opLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3 mb-1">
                            <label class="small mb-0 lw-qbe-valor-label">{{ $tipo === 'fecha' && $opAct === 'entre' ? 'Desde' : 'Valor' }}</label>
                            <input type="{{ $tipo === 'fecha' ? 'date' : 'text' }}" name="qbe[grupos][{{ $gi }}][criterios][{{ $ci }}][valor]"
                                   class="form-control form-control-sm lw-qbe-valor"
                                   value="{{ $c['valor'] ?? '' }}" autocomplete="off"
                                   @if ($opAct === 'vacio') disabled @endif>
                        </div>
                        <div class="form-group col-md-2 mb-1 lw-qbe-hasta-wrap {{ $opAct === 'entre' ? '' : 'd-none' }}">
                            <label class="small mb-0">Hasta</label>
                            <input type="{{ $tipo === 'fecha' ? 'date' : 'text' }}" name="qbe[grupos][{{ $gi }}][criterios][{{ $ci }}][valor_hasta]"
                                   class="form-control form-control-sm lw-qbe-valor-hasta"
                                   value="{{ $c['valor_hasta'] ?? '' }}" autocomplete="off">
                        </div>
                        <div class="form-group col-auto mb-1">
                            <label class="small mb-0 d-block">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-outline-danger lw-qbe-remove"
                                    title="Quita este criterio. Si es el único del grupo, borra el valor."
                                    aria-label="Quitar criterio">
                                <i class="fa fa-times"></i> Quitar criterio
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
<div class="mb-2">
    <button type="button" class="btn btn-sm btn-outline-info" id="btn-lw-add-grupo">
        <i class="fa fa-object-group"></i> Grupo
    </button>
</div>

<template id="lw-qbe-grupo-template">
    <div class="lw-qbe-entre text-center my-2">
        <div class="btn-group btn-group-sm lw-qbe-entre-toggle" role="group">
            <button type="button" class="btn btn-outline-secondary lw-entre-and active" data-logic="and">Y</button>
            <button type="button" class="btn btn-outline-secondary lw-entre-or" data-logic="or">O</button>
        </div>
    </div>
    <div class="lw-qbe-grupo card card-outline card-info mb-2" data-grupo-idx="__g__">
        <div class="card-header py-1 px-2 d-flex flex-wrap align-items-center justify-content-between" style="gap:.35rem;">
            <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                <strong class="small mb-0 lw-qbe-grupo-titulo">Grupo</strong>
                <select name="qbe[grupos][__g__][logic]" class="form-control form-control-sm lw-qbe-grupo-logic" style="width:auto;min-width:7rem;">
                    <option value="and" selected>Todos (Y)</option>
                    <option value="or">Alguno (O)</option>
                </select>
                <div class="custom-control custom-checkbox mb-0">
                    <input type="hidden" name="qbe[grupos][__g__][not]" value="0">
                    <input type="checkbox" class="custom-control-input lw-qbe-grupo-not" id="lw-qbe-not-__g__"
                           name="qbe[grupos][__g__][not]" value="1">
                    <label class="custom-control-label small" for="lw-qbe-not-__g__">NOT</label>
                </div>
            </div>
            <div class="d-flex" style="gap:.25rem;">
                <button type="button" class="btn btn-xs btn-outline-primary lw-qbe-add-criterio-grupo" title="Agregar criterio">
                    <i class="fa fa-plus"></i>
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger lw-qbe-remove-grupo" title="Quitar grupo">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
        <div class="card-body py-2 px-2 lw-qbe-criterios-grupo"></div>
    </div>
</template>

<template id="lw-qbe-row-template">
    <div class="lw-qbe-row form-row align-items-end mb-2">
        <div class="form-group col-md-3 mb-1">
            <label class="small mb-0">Campo</label>
            <select name="qbe[grupos][__g__][criterios][__i__][campo]" class="form-control form-control-sm lw-qbe-campo"></select>
        </div>
        <div class="form-group col-md-12 mb-1 lw-qbe-formula-wrap d-none">
            <label class="small mb-0">Fórmula</label>
            <input type="text" name="qbe[grupos][__g__][criterios][__i__][formula]" class="form-control form-control-sm lw-qbe-formula font-monospace" autocomplete="off"
                   placeholder="LENGTH({nombre}) · UPPER({codigo}) · CONCAT({codigo},'-',{nombre})">
        </div>
        <div class="form-group col-md-2 mb-1">
            <label class="small mb-0">Operador</label>
            <select name="qbe[grupos][__g__][criterios][__i__][op]" class="form-control form-control-sm lw-qbe-op"></select>
        </div>
        <div class="form-group col-md-3 mb-1">
            <label class="small mb-0 lw-qbe-valor-label">Valor</label>
            <input type="text" name="qbe[grupos][__g__][criterios][__i__][valor]" class="form-control form-control-sm lw-qbe-valor" autocomplete="off">
        </div>
        <div class="form-group col-md-2 mb-1 lw-qbe-hasta-wrap d-none">
            <label class="small mb-0">Hasta</label>
            <input type="text" name="qbe[grupos][__g__][criterios][__i__][valor_hasta]" class="form-control form-control-sm lw-qbe-valor-hasta" autocomplete="off">
        </div>
        <div class="form-group col-auto mb-1">
            <label class="small mb-0 d-block">&nbsp;</label>
            <button type="button" class="btn btn-sm btn-outline-danger lw-qbe-remove"
                    title="Quita este criterio. Si es el único del grupo, borra el valor."
                    aria-label="Quitar criterio">
                <i class="fa fa-times"></i> Quitar criterio
            </button>
        </div>
    </div>
</template>
