@php
    $centrocostosIniciales = $centrocostos_iniciales ?? [];
    $codigosInicialesCc = collect($centrocostosIniciales)->pluck('codigo')->filter()->implode(',');
    $tieneFiltroCc = $codigosInicialesCc !== ''
        || trim((string) ($filtros['cc_desde'] ?? '')) !== ''
        || trim((string) ($filtros['cc_hasta'] ?? '')) !== '';
    $incluirSinCc = $filtros['incluir_sin_cc'] ?? ! $tieneFiltroCc;
@endphp
<div class="form-group row mb-2" id="mpc-centrocostos-filtro">
    <label class="col-lg-2 control-label text-right">Centros de costo</label>
    <div class="col-lg-9">
        <input type="hidden" name="centrocostos_codigo" id="mpc_centrocostos_codigo" value="{{ $codigosInicialesCc }}">

        <p class="text-muted small mb-2 font-weight-bold">Centros particulares</p>
        <div class="tm-centrocosto-campo mpc-cc-campo mpc-cc-puntual mb-2" data-campo="puntual">
            <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                <input type="hidden" class="centrocosto_id">
                <button type="button" title="Consultar centros de costo (F1)" class="btn btn-outline-secondary btn-sm consultacentrocosto">
                    <i class="fa fa-search"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigocentrocosto"
                    value="" placeholder="C&oacute;d." autocomplete="off" style="max-width: 100px;">
                <input type="text" class="form-control form-control-sm descripcioncentrocosto flex-grow-1"
                    value="" placeholder="Nombre del centro de costo" readonly>
                <button type="button" class="btn btn-outline-primary btn-sm" id="mpc-btn-agregar-cc">
                    <i class="fa fa-plus"></i> Agregar
                </button>
            </div>
        </div>

        <div class="table-responsive mb-3">
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

        <div class="border rounded bg-light px-3 py-2">
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
            <div class="form-check">
                <input type="hidden" name="agrupar_por_cc" value="0">
                <input class="form-check-input" type="checkbox" name="agrupar_por_cc" id="agrupar_por_cc" value="1"
                    @checked(! empty($filtros['agrupar_por_cc']))>
                <label class="form-check-label" for="agrupar_por_cc">Clasificar cada cuenta por centro de costo</label>
            </div>
            <div class="form-check">
                <input type="hidden" name="incluir_sin_cc" value="0">
                <input class="form-check-input" type="checkbox" name="incluir_sin_cc" id="incluir_sin_cc" value="1"
                    @checked($incluirSinCc)>
                <label class="form-check-label" for="incluir_sin_cc">Incluir movimientos sin centro de costo</label>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Lista y rango se unen. Sin filtro se incluyen todos los movimientos; con filtro, los movimientos sin CC se excluyen salvo que marque la opci&oacute;n.
            </p>
        </div>
    </div>
</div>
