@php
    $cuentasIniciales = $cuentas_iniciales ?? [];
    $codigosIniciales = collect($cuentasIniciales)->pluck('codigo')->filter()->implode(',');
@endphp
<div class="card card-outline card-info h-100 mb-0" id="mpc-cuentas-filtro">
    <div class="card-header py-2">
        <h3 class="card-title font-weight-bold">
            <i class="fa fa-book mr-1"></i> Cuentas contables
        </h3>
        <small class="float-right text-muted">Puntuales y/o rango</small>
    </div>
    <div class="card-body p-3 d-flex flex-column">
        <input type="hidden" name="cuentas" id="mpc_cuentas" value="{{ old('cuentas', $codigosIniciales) }}">

        <p class="text-muted small mb-2 font-weight-bold">Selecci&oacute;n puntual</p>
        <div class="mpc-cuenta-campo mpc-cuenta-puntual mb-2" data-campo="puntual">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control codigocuentacontable"
                    id="mpc_cuenta_puntual_codigo" value="" placeholder="111010-001"
                    title="C&oacute;digo de cuenta. F1 = consulta" autocomplete="off"
                    style="max-width: 125px;">
                <input type="text" class="form-control nombrecuentacontable"
                    id="mpc_cuenta_puntual_nombre" value="" placeholder="Nombre de la cuenta" readonly>
                <div class="input-group-append">
                    <button type="button" title="Consultar cuentas (F1)" class="btn btn-outline-secondary consultacuentacontable">
                        <i class="fa fa-search"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="mpc-btn-agregar-cuenta" title="Agregar cuenta a la lista">
                        <i class="fa fa-plus"></i> Agregar
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive mb-2 mpc-seleccion-scroll">
            <table class="table table-sm table-bordered mb-0" id="mpc-tabla-cuentas-seleccionadas">
                <thead style="background:#85C1E9;color:#17202A;">
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
        <p class="text-muted small mb-2" id="mpc-aviso-sin-cuentas-puntuales"
            @if (($cuentasIniciales ?? []) !== []) style="display: none;" @endif>
            Sin cuentas puntuales. Puede agregar una o usar solamente el rango.
        </p>

        <div class="border rounded bg-light px-2 py-2 mt-auto">
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
                Sin selecci&oacute;n se incluyen todas las cuentas con movimiento. Las cuentas puntuales y el rango se suman.
            </p>
        </div>
    </div>
</div>
