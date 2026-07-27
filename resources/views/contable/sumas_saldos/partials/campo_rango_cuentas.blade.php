@php
    $cuentaDesdeMeta = $cuenta_desde_meta ?? ['codigo' => '', 'nombre' => ''];
    $cuentaHastaMeta = $cuenta_hasta_meta ?? ['codigo' => '', 'nombre' => ''];
@endphp
<div class="form-group row mb-2">
    <label class="col-lg-2 control-label text-right">Rango de cuentas</label>
    <div class="col-lg-9">
        <div class="row">
            <div class="col-md-6 mb-2 mb-md-0">
                <label class="small text-muted mb-1" for="cuenta_desde_codigo">Desde</label>
                <div class="sys-cuenta-campo tm-cuentacontable-campo" data-campo="desde">
                    <div class="input-group input-group-sm">
                        <input type="text" name="cuenta_desde" id="cuenta_desde_codigo"
                            class="form-control codigocuentacontable"
                            placeholder="111010-001" autocomplete="off"
                            title="F1 o lupa: consultar cuentas. Enter: resolver por código."
                            value="{{ $cuentaDesdeMeta['codigo'] ?? '' }}">
                        <input type="text" class="form-control nombrecuentacontable" readonly
                            placeholder="Nombre cuenta" value="{{ $cuentaDesdeMeta['nombre'] ?? '' }}">
                        <div class="input-group-append">
                            <button type="button" title="Consultar cuentas (F1)" class="btn btn-outline-secondary consultacuentacontable tooltipsC">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" class="cuentacontable_id" value="">
                </div>
            </div>
            <div class="col-md-6">
                <label class="small text-muted mb-1" for="cuenta_hasta_codigo">Hasta</label>
                <div class="sys-cuenta-campo tm-cuentacontable-campo" data-campo="hasta">
                    <div class="input-group input-group-sm">
                        <input type="text" name="cuenta_hasta" id="cuenta_hasta_codigo"
                            class="form-control codigocuentacontable"
                            placeholder="599999-999" autocomplete="off"
                            title="F1 o lupa: consultar cuentas. Enter: resolver por código."
                            value="{{ $cuentaHastaMeta['codigo'] ?? '' }}">
                        <input type="text" class="form-control nombrecuentacontable" readonly
                            placeholder="Nombre cuenta" value="{{ $cuentaHastaMeta['nombre'] ?? '' }}">
                        <div class="input-group-append">
                            <button type="button" title="Consultar cuentas (F1)" class="btn btn-outline-secondary consultacuentacontable tooltipsC">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" class="cuentacontable_id" value="">
                </div>
            </div>
        </div>
    </div>
</div>
