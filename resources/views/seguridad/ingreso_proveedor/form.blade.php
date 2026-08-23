@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $data = $data ?? new \App\Models\Seguridad\IngresoProveedor();
    $personasOld = old('persona_nombres');
    $personas = [];
    if (is_array($personasOld)) {
        foreach ($personasOld as $i => $nombre) {
            $personas[] = [
                'nombre' => $nombre,
                'documento' => old('persona_documentos.'.$i, ''),
            ];
        }
    } elseif ($data && $data->personas) {
        foreach ($data->personas as $p) {
            $personas[] = ['nombre' => $p->nombre, 'documento' => $p->documento];
        }
    }
    if ($personas === []) {
        $personas[] = ['nombre' => '', 'documento' => ''];
    }
    $estado = old('estado', $data->estado ?? IngresoProveedorEstados::PENDIENTE);
    $prefill = $prefill ?? [];
    $proveedor = $data?->proveedores ?? ($prefill['proveedor'] ?? null);
    if (! $proveedor && (int) ($data?->proveedor_id ?? $prefill['proveedor_id'] ?? 0) > 0) {
        $proveedor = \App\Models\Compras\Proveedor::withTrashed()->find((int) ($data?->proveedor_id ?? $prefill['proveedor_id']));
    }
@endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#tab-datos-principales" role="tab">Datos principales</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab">Archivos asociados</a>
    </li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-datos-principales" role="tabpanel">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? $prefill['empresa_id'] ?? ''),
            'col_label' => 'col-lg-2',
            'col_input' => 'col-lg-4',
        ])

        <div class="row">
            <div class="col-md-6">
                <div class="form-group row">
                    <label for="fecha" class="col-lg-4 control-label text-right pr-2 requerido">Fecha de carga</label>
                    <div class="col-lg-8">
                        <input type="date" id="fecha" class="form-control" readonly
                               value="{{ optional($data->fecha ?? null)->format('Y-m-d') ?: date('Y-m-d') }}">
                        <small class="text-muted">Se graba al crear el ticket. No se edita.</small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2 requerido">Persona(s) que visita(n)</label>
                    <div class="col-lg-8" id="ingreso-personas">
                        @foreach ($personas as $persona)
                            <div class="ingreso-persona-item mb-2">
                                <input type="text" name="persona_nombres[]" class="form-control mb-1" placeholder="Nombre y apellido" value="{{ $persona['nombre'] }}">
                                <input type="text" name="persona_documentos[]" class="form-control" placeholder="Documento (DNI/CUIL)" value="{{ $persona['documento'] }}">
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-outline-primary btn-sm btn-block" id="ingreso-agregar-persona" style="border-style: dashed;">
                            + agregar persona
                        </button>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="punto_id" class="col-lg-4 control-label text-right pr-2 requerido">Sala / Punto de ingreso</label>
                    <div class="col-lg-8">
                        <select name="punto_id" id="punto_id" class="form-control" required>
                            <option value="">-- Seleccionar --</option>
                            @foreach ($puntos as $item)
                                <option value="{{ $item->id }}" {{ (string) old('punto_id', $data->punto_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="area_id" class="col-lg-4 control-label text-right pr-2 requerido">&Aacute;rea de destino</label>
                    <div class="col-lg-8">
                        <select name="area_id" id="area_id" class="form-control" required>
                            <option value="">-- Seleccionar --</option>
                            @foreach ($areas as $item)
                                <option value="{{ $item->id }}" {{ (string) old('area_id', $data->area_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                @php
                    $esVisitanteOld = old('es_visitante');
                    $esVisitante = $esVisitanteOld !== null
                        ? (string) $esVisitanteOld === '1'
                        : \App\Support\Seguridad\IngresoProveedorVisitanteSupport::esVisitante($data);
                @endphp
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2">Tipo de visita</label>
                    <div class="col-lg-8">
                        <div class="form-check mt-1">
                            <input type="hidden" name="es_visitante" value="0">
                            <input type="checkbox" name="es_visitante" id="es_visitante" value="1" class="form-check-input"
                                   {{ $esVisitante ? 'checked' : '' }}>
                            <label class="form-check-label" for="es_visitante">
                                No es proveedor (viene a pasar un presupuesto u otra visita puntual)
                            </label>
                        </div>
                    </div>
                </div>

                @php
                    $ordencompraIdForm = old('ordencompra_id', $data->ordencompra_id ?? $prefill['ordencompra_id'] ?? '');
                    $ordencompraLocked = (int) ($prefill['ordencompra_id'] ?? 0) > 0 && ! $data;
                    $ocForm = $data?->ordencompras ?? ($prefill['ordencompra'] ?? null);
                @endphp
                <input type="hidden" name="ordencompra_id" id="ordencompra_id" value="{{ $ordencompraIdForm }}"
                       @if ($ordencompraLocked) data-locked="1" @endif>>

                <div class="form-group row ingreso-campo-proveedor" @if($esVisitante) style="display:none" @endif>
                    <label for="codigoproveedor" class="col-lg-4 control-label text-right pr-2 requerido">Proveedor / Empresa</label>
                    <div class="col-lg-8">
                        <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ old('proveedor_id', $data->proveedor_id ?? $prefill['proveedor_id'] ?? '') }}">
                        <div class="d-flex flex-wrap align-items-center">
                            <input type="text" class="form-control codigoproveedor mr-2" id="codigoproveedor" name="codigoproveedor"
                                   value="{{ old('codigoproveedor', $proveedor->codigo ?? '') }}" style="width: 6rem;" placeholder="C&oacute;d.">
                            <input type="text" class="form-control mr-2" id="nombreproveedor" name="nombreproveedor"
                                   value="{{ old('nombreproveedor', $proveedor->nombre ?? '') }}" readonly placeholder="Buscar proveedor..." style="min-width: 8rem; flex: 1;">
                            <button type="button" title="Consulta proveedores (F1)" class="btn-accion-tabla consultaproveedor tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ml-2" id="ingreso-alta-rapida-proveedor"
                                    title="Buscar por nombre o CUIT; si no est&aacute;, alta en maestro o visitante">
                                Alta r&aacute;pida
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group row ingreso-campo-contrato" @if($esVisitante) style="display:none" @endif>
                    <label for="numero_contrato" class="col-lg-4 control-label text-right pr-2">Contrato / Abono</label>
                    <div class="col-lg-8">
                        <div class="d-flex flex-wrap align-items-center">
                            <input type="text" class="form-control mr-2" id="numero_contrato" name="numero_contrato"
                                   value="{{ old('numero_contrato', $ocForm->numeroordencompra ?? '') }}"
                                   style="width: 8rem;" placeholder="Nro."
                                   @if ($ordencompraLocked) readonly @endif
                                   title="Contratos activos del proveedor (F1)">
                            <input type="text" class="form-control mr-2" id="descripcion_contrato" readonly
                                   value="{{ $ocForm ? trim(($ocForm->estadoordencompra ?? '').((!empty($ocForm->es_contrato)) ? ' · Contrato' : '')) : '' }}"
                                   placeholder="Solo contratos activos" style="min-width: 8rem; flex: 1;">
                            @unless ($ordencompraLocked)
                                <button type="button" title="Consulta contratos activos (F1)" class="btn-accion-tabla consultacontrato tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                            @endunless
                        </div>
                        <small class="text-muted">Opcional. Solo OC contrato aprobada/cumplida y vigente.</small>
                    </div>
                </div>

                @include('seguridad.ingreso_proveedor.partials.datos_ordencompra', [
                    'ordencompra' => $ocForm,
                    'col_label' => 'col-lg-4',
                    'col_input' => 'col-lg-8',
                ])

                <div class="form-group row ingreso-campo-visitante" @unless($esVisitante) style="display:none" @endunless>
                    <label for="visitante_nombre" class="col-lg-4 control-label text-right pr-2 requerido">Qui&eacute;n visita</label>
                    <div class="col-lg-8">
                        <input type="text" name="visitante_nombre" id="visitante_nombre" class="form-control" maxlength="180"
                               value="{{ old('visitante_nombre', $data->visitante_nombre ?? '') }}"
                               placeholder="Empresa, estudio o nombre de quien viene">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="motivo_id" class="col-lg-4 control-label text-right pr-2 requerido">Motivo de visita</label>
                    <div class="col-lg-8">
                        <select name="motivo_id" id="motivo_id" class="form-control" required>
                            <option value="">-- Seleccionar motivo --</option>
                            @foreach ($motivos as $item)
                                <option value="{{ $item->id }}" data-codigo="{{ $item->codigo }}"
                                    {{ (string) old('motivo_id', $data->motivo_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row ingreso-campo-motivo-otro" style="display:none">
                    <label for="motivo_otro" class="col-lg-4 control-label text-right pr-2 requerido">Otro motivo</label>
                    <div class="col-lg-8">
                        <input type="text" name="motivo_otro" id="motivo_otro" class="form-control" maxlength="180"
                               value="{{ old('motivo_otro', $data->motivo_otro ?? '') }}" placeholder="Describa el motivo">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="patente" class="col-lg-4 control-label text-right pr-2">Patente de veh&iacute;culo</label>
                    <div class="col-lg-8">
                        <input type="text" name="patente" id="patente" class="form-control" maxlength="20"
                               value="{{ old('patente', $data->patente ?? '') }}" placeholder="Opcional">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="sector_id" class="col-lg-4 control-label text-right pr-2 requerido">Sector</label>
                    <div class="col-lg-8">
                        <select name="sector_id" id="sector_id" class="form-control" required>
                            <option value="">-- Seleccionar sector --</option>
                            @foreach ($sectores as $item)
                                <option value="{{ $item->id }}" {{ (string) old('sector_id', $data->sector_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2">Estado del ticket</label>
                    <div class="col-lg-8">
                        <input type="text" class="form-control" readonly value="{{ IngresoProveedorEstados::etiqueta((string) $estado) }}">
                    </div>
                </div>
            </div>
        </div>

        @include('seguridad.ingreso_proveedor.partials.movimiento_planta', ['data' => $data])

        <div class="form-group row mt-3">
            <label for="fecha_prevista" class="col-lg-2 control-label text-right pr-2">Fecha prevista de visita</label>
            <div class="col-lg-3">
                <input type="date" name="fecha_prevista" id="fecha_prevista" class="form-control"
                       value="{{ old('fecha_prevista', optional($data->fecha_prevista ?? null)->format('Y-m-d')) }}">
            </div>
        </div>
        <div class="form-group row">
            <label for="titulo" class="col-lg-2 control-label text-right pr-2 requerido">T&iacute;tulo</label>
            <div class="col-lg-10">
                <input type="text" name="titulo" id="titulo" class="form-control" maxlength="180" required
                       value="{{ old('titulo', $data->titulo ?? '') }}" placeholder="Resumen breve del motivo de la visita">
            </div>
        </div>
        <div class="form-group row">
            <label for="comentario" class="col-lg-2 control-label text-right pr-2">Comentario</label>
            <div class="col-lg-10">
                <textarea name="comentario" id="comentario" class="form-control" rows="3">{{ old('comentario', $data->comentario ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
        @include('seguridad.ingreso_proveedor.partials.solapa_archivos')
    </div>
</div>

<template id="ingreso-template-persona">
    <div class="ingreso-persona-item mb-2">
        <input type="text" name="persona_nombres[]" class="form-control mb-1" placeholder="Nombre y apellido" value="">
        <input type="text" name="persona_documentos[]" class="form-control" placeholder="Documento (DNI/CUIL)" value="">
    </div>
</template>
