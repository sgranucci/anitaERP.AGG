@php
    $esRequisiciones = (old('tipoarbol', $data->tipoarbol ?? '') === 'Requisiciones');
    $esRequisicionesSala = (old('tipoarbol', $data->tipoarbol ?? '') === 'Requisiciones de sala');
@endphp

<div class="anita-arbol-panel">
    <div class="anita-arbol-panel-head">
        <div>
            <h2 class="anita-arbol-panel-title">Cabecera</h2>
            <p class="anita-arbol-panel-hint">Nombre, tipo de documento, empresa y recordatorios del circuito.</p>
        </div>
        <span class="anita-arbol-chip anita-arbol-chip-navy">General</span>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group row">
                <label for="nombre" class="col-lg-4 col-form-label requerido">Nombre</label>
                <div class="col-lg-8">
                    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
                </div>
            </div>
            <div class="form-group row">
                <label for="tipoarbol" class="col-lg-4 col-form-label requerido">Tipo de árbol</label>
                <div class="col-lg-8">
                    <select id="tipoarbol" name="tipoarbol" class="form-control" required>
                        <option value="">-- Elija tipo de árbol --</option>
                        @foreach($tipoarbol_enum as $tipoarbol)
                            <option value="{{ $tipoarbol['nombre'] }}" {{ $tipoarbol['nombre'] == old('tipoarbol',$data->tipoarbol??'') ? 'selected' : '' }}>
                                {{ $tipoarbol['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $data->empresa_id ?? session('empresa_id'),
                'col_label' => 'col-lg-4',
                'col_input' => 'col-lg-8',
            ])
            <div class="form-group row">
                <label for="estado" class="col-lg-4 col-form-label requerido">Estado</label>
                <div class="col-lg-8">
                    <select id="estado" name="estado" class="form-control" required>
                        <option value="">-- Elija estado --</option>
                        @foreach($estado_enum as $estado)
                            <option value="{{ $estado['nombre'] }}" {{ $estado['nombre'] == old('estado',$data->estado??'') ? 'selected' : '' }}>
                                {{ $estado['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group row">
                <label for="recordatorio" class="col-lg-4 col-form-label requerido">Recordatorio</label>
                <div class="col-lg-8">
                    <select id="recordatorio" name="recordatorio" class="form-control" required>
                        <option value="">-- Elija recordatorio --</option>
                        @foreach($recordatorio_enum as $recordatorio)
                            <option value="{{ $recordatorio['valor'] }}" {{ $recordatorio['valor'] == old('recordatorio',$data->recordatorio??'') ? 'selected' : '' }}>
                                {{ $recordatorio['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group row div-diasinrespuesta" style="display: none">
                <label for="diasinrespuesta" class="col-lg-4 col-form-label requerido">Días sin respuesta</label>
                <div class="col-lg-4">
                    <input type="number" name="diasinrespuesta" id="diasinrespuesta" class="form-control" value="{{old('diasinrespuesta', $data->diasinrespuesta ?? '0')}}"/>
                </div>
            </div>
            <div class="form-group row div-diavencimientorecordatorio" style="display: none">
                <label for="diavencimientorecordatorio" class="col-lg-4 col-form-label requerido">Días vto. recordatorio</label>
                <div class="col-lg-4">
                    <input type="number" name="diavencimientorecordatorio" id="diavencimientorecordatorio" class="form-control" value="{{old('diavencimientorecordatorio', $data->diavencimientorecordatorio ?? '0')}}"/>
                </div>
            </div>
            <div class="form-group row">
                <label for="filtro_centrocosto" class="col-lg-4 col-form-label">Filtrar CC en grilla</label>
                <div class="col-lg-8">
                    <select id="filtro_centrocosto_id" class="form-control" data-fouc>
                        <option value="">Todos los centros de costo</option>
                        @foreach($centrocosto_query as $value)
                            <option value="{{ $value->id }}">{{ $value->codigo }} - {{ $value->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

@include('configuracion.arbolaprobacion.partials.oc_triggers', ['data' => $data ?? null])
@include('configuracion.arbolaprobacion.partials.re_circuito_cuentas', ['data' => $data ?? null])

<div class="anita-arbol-panel">
    <div class="anita-arbol-panel-head">
        <div>
            <h2 class="anita-arbol-panel-title">Niveles</h2>
            <p class="anita-arbol-panel-hint">
                Usuario vacío = auto. En RE, usá Rama A/B cuando el CC tiene dual-rama.
            </p>
        </div>
        <span class="anita-arbol-chip anita-arbol-chip-teal">Circuito</span>
    </div>

    <div class="anita-arbol-callout">
        <strong>Usuario opcional.</strong>
        Sin usuario, el nivel se aprueba automáticamente.
        @if($esRequisiciones)
            En requisiciones se aplica el <strong>Estado req.</strong> (default APROBADA).
            <strong>Rama A</strong> = allowlist/auto · <strong>Rama B</strong> = autorización (tipicamente N1 EN COMPRAS → N2 firmantes por monto).
            Las ramas las define el bloque <strong>Circuito RE por cuentas</strong> (allowlist + triggers).
            <strong>Doble apr.</strong> por CC: con S, Desde monto actúa como piso; con N, bandas exclusivas Desde–Hasta.
        @elseif($esRequisicionesSala)
            En requisiciones de sala se aplica el Estado req. si está definido.
        @endif
    </div>

    <div class="anita-arbol-table-wrap">
        <table class="table table-sm mb-0" id="arbolaprobacion-nivel-table">
            <thead>
                <tr>
                    <th style="width: 3%;"></th>
                    <th style="width: 5%;">Nivel</th>
                    <th style="width: 6%;" class="col-rama-re" title="Solo RE dual-rama. Vacío = circuito único.">Rama</th>
                    <th style="width: 14%;">Centro costo</th>
                    <th style="width: 22%;" title="Opcional. Sin usuario = auto.">Usuario</th>
                    <th style="width: 9%;">Desde</th>
                    <th style="width: 9%;">Hasta</th>
                    <th style="width: 6%;">Moneda</th>
                    <th style="width: 12%;">Estado doc.</th>
                    <th style="width: 7%;" class="col-doble-aprobacion" title="Doble aprobación por CC">Doble</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-arbolaprobacion-nivel-table">
            @if ($data->arbolaprobacion_niveles ?? '')
                @foreach (old('arbolaprobacion_nivel', $data->arbolaprobacion_niveles->count() ? $data->arbolaprobacion_niveles : ['']) as $arbolaprobacion_niveles)
                    @php
                        $idxNivel = $loop->index;
                        $dobleSel = strtoupper((string) old('doble_aprobacions.'.$idxNivel, $arbolaprobacion_niveles->doble_aprobacion ?? 'N'));
                        $dobleSel = $dobleSel === 'S' ? 'S' : 'N';
                        $ramaSel = strtoupper((string) old('ramas.'.$idxNivel, $arbolaprobacion_niveles->rama ?? ''));
                        if (! in_array($ramaSel, ['A', 'B'], true)) {
                            $ramaSel = '';
                        }
                    @endphp
                    <tr class="item-arbolaprobacion-nivel">
                        <td>
                            <input type="hidden" class="id form-control" name="ids[]" value="{{$arbolaprobacion_niveles->id ?? ''}}">
                            <input type="text" name="arbolaprobacion_nivel[]" class="form-control form-control-sm iiarbolaprobacion_nivel" readonly value="{{ $loop->index+1 }}" />
                        </td>
                        <td>
                            <input type="number" class="nivel form-control form-control-sm" name="niveles[]" min="1" value="{{$arbolaprobacion_niveles->nivel ?? ''}}" required>
                        </td>
                        <td class="col-rama-re">
                            <select name="ramas[]" class="form-control form-control-sm rama-re" title="Vacío = circuito único">
                                <option value="" {{ $ramaSel === '' ? 'selected' : '' }}>—</option>
                                <option value="A" {{ $ramaSel === 'A' ? 'selected' : '' }}>A</option>
                                <option value="B" {{ $ramaSel === 'B' ? 'selected' : '' }}>B</option>
                            </select>
                        </td>
                        <td>
                            <select name="centrocosto_ids[]" class="centrocosto form-control form-control-sm required" required data-fouc>
                                <option value="">-- CC --</option>
                                @foreach($centrocosto_query as $value)
                                    <option value="{{ $value->id }}" {{ (int) $value->id == (int) old('centrocosto_ids[]', $arbolaprobacion_niveles->centrocosto_id ?? '') ? 'selected' : '' }}>
                                        {{ $value->codigo }} - {{ $value->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                <input type="hidden" class="usuario_id_arbol" name="usuario_ids[]" value="{{$arbolaprobacion_niveles->usuario_id ?? ''}}" >
                                <input type="hidden" class="usuario_id_previa" name="usuario_id_previa[]" value="{{$arbolaprobacion_niveles->usuario_id ?? ''}}" >
                                <input type="text" style="flex: 0 0 96px; width: 96px;" class="usuario_codigo_arbol form-control form-control-sm" value="{{ $arbolaprobacion_niveles->usuarios->usuario ?? '' }}" placeholder="Código" title="Login o ID; Tab para cargar nombre" autocomplete="off">
                                <button type="button" title="Consulta usuarios" class="btn-accion-tabla consultausuario tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="flex: 1 1 auto; min-width: 0;" class="nombreusuario form-control form-control-sm" name="nombreusuarios[]" value="{{$arbolaprobacion_niveles->usuarios->nombre ?? ''}}" placeholder="(opcional)">
                            </div>
                        </td>
                        <td>
                            <input type="number" class="desdemonto form-control form-control-sm" name="desdemontos[]" value="{{$arbolaprobacion_niveles->desdemonto ?? ''}}">
                        </td>
                        <td>
                            <input type="number" class="hastamonto form-control form-control-sm" name="hastamontos[]" value="{{$arbolaprobacion_niveles->hastamonto ?? ''}}">
                        </td>
                        <td>
                            <select name="moneda_ids[]" class="moneda form-control form-control-sm required" required data-fouc>
                                @foreach($moneda_query as $value)
                                    <option value="{{ $value->id }}" {{ (int) $value->id == (int) old('moneda_ids[]', $arbolaprobacion_niveles->moneda_id ?? '') ? 'selected' : '' }}>
                                        {{ $value->abreviatura }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @php
                                $nombreTipoOc = \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[array_search('OC', array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
                                $nombreTipoRs = \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[array_search('RS', array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
                                $tipoArbolSel = old('tipoarbol', isset($data) ? ($data->tipoarbol ?? '') : '');
                                $docEstadosOpciones = ($tipoArbolSel === $nombreTipoOc)
                                    ? ($ordencompra_estados_arbol_enum ?? [])
                                    : (($tipoArbolSel === $nombreTipoRs)
                                        ? ($requisicion_sala_estados_arbol_enum ?? [])
                                        : ($requisicion_estados_arbol_enum ?? []));
                                $selDoc = old('documento_estado_al_aprobar.'.$idxNivel, $arbolaprobacion_niveles->documento_estado_al_aprobar ?? '');
                                if ($selDoc === '' && $esRequisiciones) {
                                    $selDoc = 'APROBADA';
                                }
                            @endphp
                            <select name="documento_estado_al_aprobar[]" class="form-control form-control-sm" title="Estado del documento al aplicar este nivel">
                                <option value="">—</option>
                                @foreach($docEstadosOpciones as $estDoc)
                                    <option value="{{ $estDoc['nombre'] }}" {{ $selDoc == $estDoc['nombre'] ? 'selected' : '' }}>{{ str_replace('_', ' ', $estDoc['nombre']) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-center col-doble-aprobacion">
                            <input type="hidden" name="doble_aprobacions[]" class="doble_aprobacion_valor" value="{{ $dobleSel }}">
                            <input type="checkbox" class="doble_aprobacion_check" value="S" title="Doble aprobación para este CC"
                                {{ $dobleSel === 'S' ? 'checked' : '' }}>
                        </td>
                        <td>
                            <button type="button" title="Eliminar línea" class="btn-accion-tabla eliminar_arbolaprobacion_nivel tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
    </div>
    @include('configuracion.arbolaprobacion.template')
    <div class="anita-arbol-toolbar">
        <span class="text-muted small">Filtrá por CC arriba si la grilla es larga.</span>
        <button type="button" id="agrega_renglon_arbolaprobacion_nivel" class="btn btn-sm anita-arbol-btn-teal">
            <i class="fa fa-plus"></i> Agregar nivel
        </button>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
