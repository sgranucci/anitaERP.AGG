@php
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaId = old('empresa_id', $empresa_default_id ?? '');
    $codigo = old('codigo', $codigo_propuesto ?? '');
@endphp

@if (($caja_id ?? 0) <= 0)
<div class="alert alert-danger">
    No tiene caja asignada para hoy. Ingrese desde <strong>Movimientos de caja</strong> antes de registrar la presentación.
</div>
@endif

<input type="hidden" name="caja_id" id="caja_id" value="{{ old('caja_id', $caja_id ?? '') }}">
<input type="hidden" name="turno_operativo_bingo_id" id="turno_operativo_bingo_id" value="{{ old('turno_operativo_bingo_id', '') }}">

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2"><strong>Datos de la presentación</strong></div>
    <div class="card-body py-2">
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="empresa_id" class="requerido">Empresa</label>
                @include('includes.form-empresa-asignada-control', [
                    'empresa_query' => $empresasDisponibles,
                    'empresa_id' => $empresaId,
                    'id' => 'empresa_id',
                    'name' => 'empresa_id',
                    'required' => true,
                    'opcion_vacia' => '— Seleccionar —',
                ])
            </div>
            <div class="form-group col-md-3">
                <label for="fecha_jornada_cabecera">Fecha jornada</label>
                <input type="text" id="fecha_jornada_cabecera" class="form-control" readonly value="—">
                <small class="text-muted">Del cierre seleccionado (no editable).</small>
            </div>
            <div class="form-group col-md-3">
                <label>Fecha/hora registro en caja</label>
                <input type="text" class="form-control" readonly value="Al guardar (hora del sistema)">
                <small class="text-muted">Momento real del registro. No editable.</small>
            </div>
            <div class="form-group col-md-3">
                <label for="codigo" class="requerido">Ticket / código caja</label>
                <input type="text" name="codigo" id="codigo" class="form-control" required maxlength="50" value="{{ $codigo }}"
                       placeholder="Se propone al elegir el cierre">
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-info mb-3" id="card-seleccion-cierre-turno">
    <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
        <strong>Cierre de turno a presentar</strong>
        <a href="#" id="link-comprobante-cierre" class="btn btn-danger btn-sm d-none" target="_blank" rel="noopener">
            <i class="fa fa-file-pdf-o"></i> Comprobante cierre
        </a>
    </div>
    <div class="card-body py-2">
        <div class="form-row align-items-end">
            <div class="form-group col-md-9 mb-md-0">
                <label for="etiqueta_cierre_turno" class="requerido">Cierre pendiente</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="etiqueta_cierre_turno" readonly
                           placeholder="Consultar cierres pendientes…" value="">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-warning consultacierrebingo" title="Buscar cierres pendientes">
                            <i class="fa fa-search"></i> Consultar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="aviso-sin-cierre-cargado" class="alert alert-info mt-3 mb-0 py-2">
            Elija un cierre de turno bingo cerrado en terminal y aún no presentado en caja.
        </div>
    </div>
</div>

<div id="panel-datos-rendicion" class="d-none">
    <div class="card card-outline card-primary mb-3">
        <div class="card-body py-3 text-center">
            <span class="text-muted d-block small mb-1">Total recaudación (base de cálculo)</span>
            <span class="bingo-rend-recaudacion" id="lbl-recaudacion">$0,00</span>
        </div>
    </div>
    <div class="bingo-rend-grid row">
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header py-2"><strong>Cartones vendidos</strong></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-bordered mb-0 bingo-rend-panel" id="tabla-cartones-presentacion">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cartones-presentacion"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header py-2"><strong>Conceptos de rendición</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0 bingo-rend-panel">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th class="text-right">%</th>
                                <th class="text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-conceptos-presentacion"></tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="row small text-muted mb-1">
                        <div class="col-6">Jornada: <span id="lbl-fecha-jornada">—</span></div>
                        <div class="col-6">Terminal: <span id="lbl-terminal">—</span></div>
                    </div>
                    <div class="row small text-muted mb-2">
                        <div class="col-6">Operador: <span id="lbl-operador">—</span></div>
                        <div class="col-6">Depósito: <strong id="lbl-deposito">$0,00</strong></div>
                    </div>
                    <div class="font-weight-bold">
                        Saldo rendición: <span id="lbl-saldo-final">$0,00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label for="observacion">Observación caja</label>
        <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="500">{{ old('observacion') }}</textarea>
    </div>
</div>
