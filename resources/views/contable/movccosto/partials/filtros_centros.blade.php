@php
    $ccIniciales = $centrocostos_iniciales ?? [];
    $grIniciales = $grupos_iniciales ?? [];
    $codigosCc = collect($ccIniciales)->pluck('codigo')->filter()->implode(',');
    $codigosGr = collect($grIniciales)->pluck('codigo')->filter()->implode(',');
    $hayFiltroCentros = $codigosCc !== '' || $codigosGr !== ''
        || trim((string) ($filtros['cc_desde'] ?? '')) !== ''
        || trim((string) ($filtros['cc_hasta'] ?? '')) !== '';
@endphp
<div class="form-group row mb-2">
    <label class="col-lg-2 control-label text-right">Acotar centros</label>
    <div class="col-lg-9">
        <a class="btn btn-outline-info btn-sm {{ $hayFiltroCentros ? '' : 'collapsed' }}" data-toggle="collapse" href="#mcc-panel-centros"
            role="button" aria-expanded="{{ $hayFiltroCentros ? 'true' : 'false' }}" aria-controls="mcc-panel-centros">
            <i class="fa fa-sitemap"></i> Centros de costo y grupos
            @if ($hayFiltroCentros)
                <span class="badge badge-warning">filtrado</span>
            @endif
        </a>
        <small class="text-muted ml-2">Vac&iacute;o = todos los centros que tengan movimientos.</small>

        <div class="collapse mt-2 {{ $hayFiltroCentros ? 'show' : '' }}" id="mcc-panel-centros">
            <div class="row">
                <div class="col-lg-6 mb-2">
                    <div class="card card-outline card-info h-100 mb-0">
                        <div class="card-header py-2">
                            <h3 class="card-title font-weight-bold"><i class="fa fa-sitemap mr-1"></i> Centros de costo</h3>
                        </div>
                        <div class="card-body p-2">
                            <input type="hidden" name="centrocostos_codigo" id="mcc_centrocostos_codigo" value="{{ $codigosCc }}">
                            <div class="tm-centrocosto-campo mcc-cc-puntual mb-2" data-campo="puntual">
                                <div class="input-group input-group-sm">
                                    <input type="hidden" class="centrocosto_id">
                                    <input type="text" class="form-control codigocentrocosto" value="" placeholder="C&oacute;d."
                                        autocomplete="off" style="max-width: 100px;" title="F1 o lupa: consultar. Enter: agregar a la lista.">
                                    <input type="text" class="form-control descripcioncentrocosto" value=""
                                        placeholder="Nombre del centro de costo" readonly>
                                    <div class="input-group-append">
                                        <button type="button" title="Consultar centros de costo (F1)" class="btn btn-outline-secondary consultacentrocosto">
                                            <i class="fa fa-search"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="mcc-btn-agregar-cc">
                                            <i class="fa fa-plus"></i> Agregar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive mb-2" style="max-height:120px; overflow-y:auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width: 100px;">C&oacute;digo</th>
                                            <th>Centro de costo</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="mcc-tbody-cc">
                                        @foreach ($ccIniciales as $cc)
                                            <tr data-codigo="{{ $cc['codigo'] }}">
                                                <td>{{ $cc['codigo'] }}</td>
                                                <td>{{ $cc['nombre'] }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-xs mcc-btn-quitar-cc" title="Quitar">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="border rounded bg-light px-2 py-2">
                                <p class="text-muted small mb-1 font-weight-bold">Rango por c&oacute;digo (se une a la lista)</p>
                                <div class="row">
                                    @foreach (['desde' => $cc_desde_meta ?? [], 'hasta' => $cc_hasta_meta ?? []] as $lado => $metaCc)
                                        <div class="col-md-6 mb-1">
                                            <div class="tm-centrocosto-campo mcc-cc-rango" data-campo="{{ $lado }}">
                                                <div class="input-group input-group-sm">
                                                    <input type="hidden" class="centrocosto_id">
                                                    <input type="text" name="cc_{{ $lado }}" id="cc_{{ $lado }}"
                                                        class="form-control codigocentrocosto"
                                                        value="{{ $metaCc['codigo'] ?? '' }}" placeholder="CC {{ $lado }}" autocomplete="off">
                                                    <input type="text" class="form-control descripcioncentrocosto"
                                                        value="{{ $metaCc['nombre'] ?? '' }}" placeholder="Nombre" readonly>
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-secondary consultacentrocosto" title="Consultar CC (F1)">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-2">
                    <div class="card card-outline card-info h-100 mb-0">
                        <div class="card-header py-2">
                            <h3 class="card-title font-weight-bold"><i class="fa fa-layer-group mr-1"></i> Grupos de centros de costo</h3>
                        </div>
                        <div class="card-body p-2">
                            <input type="hidden" name="grupos_codigo" id="mcc_grupos_codigo" value="{{ $codigosGr }}">
                            <div class="mcc-grupo-campo mb-2">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control codigogrupocc" value="" placeholder="Grupo"
                                        autocomplete="off" style="max-width: 100px;" title="F1 o lupa: consultar. Enter: agregar a la lista.">
                                    <input type="text" class="form-control descripciongrupocc" value=""
                                        placeholder="Nombre del grupo" readonly>
                                    <div class="input-group-append">
                                        <button type="button" title="Consultar grupos (F1)" class="btn btn-outline-secondary consultagrupocc">
                                            <i class="fa fa-search"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="mcc-btn-agregar-grupo">
                                            <i class="fa fa-plus"></i> Agregar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height:120px; overflow-y:auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width: 100px;">Grupo</th>
                                            <th>Nombre</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="mcc-tbody-grupos">
                                        @foreach ($grIniciales as $g)
                                            <tr data-codigo="{{ $g['codigo'] }}">
                                                <td>{{ $g['codigo'] }}</td>
                                                <td>{{ $g['nombre'] }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-xs mcc-btn-quitar-grupo" title="Quitar">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted small mb-0 mt-2">
                                Grupo 0 = centros de costo sin grupo asignado.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
