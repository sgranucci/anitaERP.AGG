@php
    use App\Support\Ventas\ClienteListadoFiltros;
    $camposQbe = $camposFiltro ?? ClienteListadoFiltros::camposQbeDisponibles();
    $qbeVals = (array) ($filtros['qbe'] ?? []);
    // Normalizar a lista de criterios {campo,op,valor}
    $criterios = [];
    if ($qbeVals !== [] && (array_is_list($qbeVals) || isset($qbeVals[0]))) {
        foreach ($qbeVals as $c) {
            if (is_array($c) && ! empty($c['campo'])) {
                $criterios[] = [
                    'campo' => $c['campo'],
                    'op' => $c['op'] ?? 'contiene',
                    'valor' => $c['valor'] ?? '',
                ];
            }
        }
    } else {
        foreach ($qbeVals as $k => $v) {
            if (is_string($k) && trim((string) $v) !== '') {
                $criterios[] = ['campo' => $k, 'op' => 'contiene', 'valor' => (string) $v];
            }
        }
    }
    if ($criterios === []) {
        $criterios[] = ['campo' => 'nombre', 'op' => 'contiene', 'valor' => ''];
    }
    $opsTexto = ClienteListadoFiltros::OPERADORES_TEXTO;
    $opsEntero = ClienteListadoFiltros::OPERADORES_ENTERO;
    $opsBool = ClienteListadoFiltros::OPERADORES_BOOLEANO;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $etiquetasColumnas[$k] ?? ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
@endphp
<div class="lw-qbe collapse show" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-bool='@json($opsBool)'
     data-campos='@json($camposQbeJson)'>
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Estilo Dynamics / NetSuite: Campo + Operador + Valor · varios criterios con Y</div>
        </div>
        <div class="d-flex" style="gap:.35rem;">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-add-criterio">
                <i class="fa fa-plus"></i> Criterio
            </button>
            <button type="submit" id="btn-lw-aplicar-qbe" class="btn btn-sm btn-primary">
                <i class="fa fa-search"></i> Buscar
            </button>
            <button type="button" class="btn btn-sm btn-link text-muted" data-toggle="collapse" data-target="#lw-qbe-panel">
                Ocultar
            </button>
        </div>
    </div>
    <div id="lw-qbe-criterios">
        @foreach ($criterios as $i => $c)
            @php
                $campoAct = $c['campo'];
                $tipo = $camposQbe[$campoAct]['type'] ?? 'texto';
                $ops = match ($tipo) {
                    'entero' => $opsEntero,
                    'booleano' => $opsBool,
                    default => $opsTexto,
                };
            @endphp
            <div class="lw-qbe-row form-row align-items-end mb-2" data-idx="{{ $i }}">
                <div class="form-group col-md-4 mb-1">
                    <label class="small mb-0">Campo</label>
                    <select name="qbe[{{ $i }}][campo]" class="form-control form-control-sm lw-qbe-campo">
                        @foreach ($camposQbe as $key => $meta)
                            <option value="{{ $key }}" data-type="{{ $meta['type'] }}"
                                @if ($key === $campoAct) selected @endif>
                                {{ $etiquetasColumnas[$key] ?? $meta['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 mb-1">
                    <label class="small mb-0">Operador</label>
                    <select name="qbe[{{ $i }}][op]" class="form-control form-control-sm lw-qbe-op">
                        @foreach ($ops as $opKey => $opLabel)
                            <option value="{{ $opKey }}" @if (($c['op'] ?? '') === $opKey) selected @endif>{{ $opLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4 mb-1">
                    <label class="small mb-0">Valor</label>
                    <input type="text" name="qbe[{{ $i }}][valor]" class="form-control form-control-sm lw-qbe-valor"
                           value="{{ $c['valor'] ?? '' }}" autocomplete="off"
                           @if (($c['op'] ?? '') === 'vacio') disabled @endif>
                </div>
                <div class="form-group col-md-1 mb-1">
                    <button type="button" class="btn btn-sm btn-outline-danger lw-qbe-remove" title="Quitar">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
<template id="lw-qbe-row-template">
    <div class="lw-qbe-row form-row align-items-end mb-2">
        <div class="form-group col-md-4 mb-1">
            <label class="small mb-0">Campo</label>
            <select name="qbe[__i__][campo]" class="form-control form-control-sm lw-qbe-campo"></select>
        </div>
        <div class="form-group col-md-3 mb-1">
            <label class="small mb-0">Operador</label>
            <select name="qbe[__i__][op]" class="form-control form-control-sm lw-qbe-op"></select>
        </div>
        <div class="form-group col-md-4 mb-1">
            <label class="small mb-0">Valor</label>
            <input type="text" name="qbe[__i__][valor]" class="form-control form-control-sm lw-qbe-valor" autocomplete="off">
        </div>
        <div class="form-group col-md-1 mb-1">
            <button type="button" class="btn btn-sm btn-outline-danger lw-qbe-remove" title="Quitar">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>
</template>
