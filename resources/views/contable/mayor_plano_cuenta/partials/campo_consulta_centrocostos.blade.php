@php
    $centrocostosIniciales = $centrocostos_iniciales ?? [];
    $codigosInicialesCc = collect($centrocostosIniciales)->pluck('codigo')->filter()->implode(',');
    $tieneFiltroCc = $codigosInicialesCc !== ''
        || trim((string) ($filtros['cc_desde'] ?? '')) !== ''
        || trim((string) ($filtros['cc_hasta'] ?? '')) !== '';
    $incluirSinCcManual = ! empty($filtros['incluir_sin_cc_manual']);
    $incluirSinCc = $incluirSinCcManual
        ? ! empty($filtros['incluir_sin_cc'])
        : ! $tieneFiltroCc;
@endphp
<div class="card card-outline card-info h-100 mb-0" id="mpc-centrocostos-filtro">
    <div class="card-header py-2">
        <h3 class="card-title font-weight-bold">
            <i class="fa fa-sitemap mr-1"></i> Centros de costo
        </h3>
        <small class="float-right text-muted">Filtro independiente</small>
    </div>
    <div class="card-body p-3 d-flex flex-column">
        <input type="hidden" name="centrocostos_codigo" id="mpc_centrocostos_codigo" value="{{ $codigosInicialesCc }}">

        <div class="alert alert-light border py-1 px-2 small mb-2">
            <i class="fa fa-info-circle text-info"></i>
            Este filtro se aplica siempre, aunque no active la clasificaci&oacute;n por centro de costo.
        </div>
        <p class="text-muted small mb-2 font-weight-bold">Selecci&oacute;n puntual</p>
        <div class="tm-centrocosto-campo mpc-cc-campo mpc-cc-puntual mb-2" data-campo="puntual">
            <div class="input-group input-group-sm">
                <input type="hidden" class="centrocosto_id">
                <input type="text" class="form-control codigocentrocosto"
                    value="" placeholder="C&oacute;d." autocomplete="off" style="max-width: 100px;">
                <input type="text" class="form-control descripcioncentrocosto"
                    value="" placeholder="Nombre del centro de costo" readonly>
                <div class="input-group-append">
                    <button type="button" title="Consultar centros de costo (F1)" class="btn btn-outline-secondary consultacentrocosto">
                        <i class="fa fa-search"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="mpc-btn-agregar-cc">
                        <i class="fa fa-plus"></i> Agregar
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive mb-2 mpc-seleccion-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 110px;">C&oacute;digo</th>
                        <th>Centro de costo</th>
                        <th style="width: 70px;"></th>
                    </tr>
                </thead>
                <tbody id="mpc-tbody-cc-seleccionados">
                    @foreach ($centrocostosIniciales as $cc)
                        <tr data-codigo="{{ $cc['codigo'] ?? '' }}">
                            <td>{{ $cc['codigo'] ?? '' }}</td>
                            <td>{{ $cc['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs mpc-btn-quitar-cc" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border rounded bg-light px-2 py-2 mt-auto">
            <p class="text-muted small mb-2 font-weight-bold">Rango por c&oacute;digo</p>
            <div class="row">
                @foreach (['desde' => $cc_desde_meta ?? [], 'hasta' => $cc_hasta_meta ?? []] as $lado => $metaCc)
                    <div class="col-md-6 mb-2">
                        <label class="small text-muted mb-1" for="cc_{{ $lado }}">CC {{ ucfirst($lado) }}</label>
                        <div class="tm-centrocosto-campo mpc-cc-campo" data-campo="{{ $lado }}">
                            <div class="input-group input-group-sm">
                                <input type="hidden" class="centrocosto_id">
                                <input type="text" name="cc_{{ $lado }}" id="cc_{{ $lado }}"
                                    class="form-control codigocentrocosto"
                                    value="{{ $metaCc['codigo'] ?? '' }}" placeholder="C&oacute;digo" autocomplete="off">
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
            <p class="text-muted small mb-0 mt-2">
                Lista y rango se unen. Sin selecci&oacute;n se incluyen todos los centros de costo.
            </p>
        </div>

        <div class="border-top mt-2 pt-2">
            <div class="form-check">
                <input type="hidden" name="agrupar_por_cc" value="0">
                <input class="form-check-input" type="checkbox" name="agrupar_por_cc" id="agrupar_por_cc" value="1"
                    @checked(! empty($filtros['agrupar_por_cc']))>
                <label class="form-check-label font-weight-bold" for="agrupar_por_cc">
                    Clasificar el resultado por centro de costo
                </label>
            </div>
            <div class="form-check">
                <input type="hidden" name="incluir_sin_cc" value="0">
                <input type="hidden" name="incluir_sin_cc_manual" id="incluir_sin_cc_manual"
                    value="{{ $incluirSinCcManual ? 1 : 0 }}">
                <input class="form-check-input" type="checkbox" name="incluir_sin_cc" id="incluir_sin_cc" value="1"
                    @checked($incluirSinCc)>
                <label class="form-check-label" for="incluir_sin_cc">Incluir movimientos sin centro de costo</label>
                <small class="d-block text-muted">
                    Al elegir centros de costo se destilda solo; vuelva a tildarlo si tambi&eacute;n quiere los movimientos sin CC.
                </small>
            </div>
        </div>
    </div>
</div>
