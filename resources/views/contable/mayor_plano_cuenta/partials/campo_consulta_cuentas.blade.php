@php
    $cuentasIniciales = $cuentas_iniciales ?? [];
    $codigosIniciales = collect($cuentasIniciales)->pluck('codigo')->filter()->implode(',');
@endphp
<div class="form-group row mb-2" id="mpc-cuentas-filtro">
    <label class="col-lg-2 control-label text-right">Cuentas</label>
    <div class="col-lg-9">
        <input type="hidden" name="cuentas" id="mpc_cuentas" value="{{ old('cuentas', $codigosIniciales) }}">

        <p class="text-muted small mb-2 font-weight-bold">Cuentas particulares</p>
        <div class="mpc-cuenta-campo mpc-cuenta-puntual mb-2" data-campo="puntual">
            <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                <button type="button" title="Consultar cuentas (F1)" class="btn btn-outline-secondary btn-sm consultacuentacontable">
                    <i class="fa fa-search"></i>
                </button>
                <input type="text"
                    class="form-control form-control-sm codigocuentacontable"
                    id="mpc_cuenta_puntual_codigo"
                    value=""
                    placeholder="111010-001"
                    title="C&oacute;digo de cuenta. F1 = consulta"
                    autocomplete="off"
                    style="max-width: 130px;">
                <input type="text"
                    class="form-control form-control-sm nombrecuentacontable flex-grow-1"
                    id="mpc_cuenta_puntual_nombre"
                    value=""
                    placeholder="Nombre de la cuenta"
                    readonly>
                <button type="button" class="btn btn-outline-primary btn-sm" id="mpc-btn-agregar-cuenta" title="Agregar cuenta a la lista">
                    <i class="fa fa-plus"></i> Agregar
                </button>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0" id="mpc-tabla-cuentas-seleccionadas">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 130px;">C&oacute;digo</th>
                        <th>Cuenta contable</th>
                        <th style="width: 70px;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="mpc-tbody-cuentas-seleccionadas">
                    @foreach ($cuentasIniciales as $cta)
                        <tr data-codigo="{{ $cta['codigo'] ?? '' }}">
                            <td>{{ $cta['codigo_fmt'] ?? ($cta['codigo'] ?? '') }}</td>
                            <td>{{ $cta['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs mpc-btn-quitar-cuenta" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-3" id="mpc-aviso-sin-cuentas-puntuales"
            @if (($cuentasIniciales ?? []) !== []) style="display: none;" @endif>
            Sin cuentas particulares. Puede cargar una o m&aacute;s con la lupa / Agregar, y/o un rango abajo.
        </p>

        <div class="border rounded bg-light px-3 py-2 mb-1">
            <p class="text-muted small mb-2 mb-md-1 font-weight-bold">Rango por c&oacute;digo de cuenta</p>
            <div class="row">
                <div class="col-md-6 mb-2 mb-md-0">
                    <label class="small text-muted mb-1" for="cuenta_desde_codigo">Desde</label>
                    <div class="mpc-cuenta-campo mpc-cuenta-inline" data-campo="desde">
                        <div class="input-group input-group-sm">
                            <input type="text" name="cuenta_desde" id="cuenta_desde_codigo"
                                class="form-control codigocuentacontable mpc-cuenta-codigo-input"
                                placeholder="111010-001" autocomplete="off"
                                title="C&oacute;digo desde. F1 = consulta"
                                value="{{ $cuenta_desde_meta['codigo'] ?? '' }}">
                            <input type="text" class="form-control nombrecuentacontable mpc-cuenta-nombre-input" readonly
                                placeholder="Nombre cuenta" value="{{ $cuenta_desde_meta['nombre'] ?? '' }}">
                            <div class="input-group-append">
                                <button type="button" title="Consulta cuentas (F1)" class="btn btn-outline-secondary consultacuentacontable tooltipsC">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="small text-muted mb-1" for="cuenta_hasta_codigo">Hasta</label>
                    <div class="mpc-cuenta-campo mpc-cuenta-inline" data-campo="hasta">
                        <div class="input-group input-group-sm">
                            <input type="text" name="cuenta_hasta" id="cuenta_hasta_codigo"
                                class="form-control codigocuentacontable mpc-cuenta-codigo-input"
                                placeholder="111010-999 (vac&iacute;o = solo desde)" autocomplete="off"
                                title="C&oacute;digo hasta. F1 = consulta"
                                value="{{ $cuenta_hasta_meta['codigo'] ?? '' }}">
                            <input type="text" class="form-control nombrecuentacontable mpc-cuenta-nombre-input" readonly
                                placeholder="Nombre cuenta" value="{{ $cuenta_hasta_meta['nombre'] ?? '' }}">
                            <div class="input-group-append">
                                <button type="button" title="Consulta cuentas (F1)" class="btn btn-outline-secondary consultacuentacontable tooltipsC">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Vac&iacute;o en rango y en particulares = todas las cuentas con movimiento.
                Si hay particulares y rango, se unen (puntuales + intervalo).
                La consulta de nombres usa la primera empresa seleccionada.
            </p>
        </div>
    </div>
</div>
