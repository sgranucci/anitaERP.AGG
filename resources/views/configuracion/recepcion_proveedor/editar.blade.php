@extends("theme.$theme.layout")
@section('titulo')
Configuración recepción proveedores
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/recepcion_proveedor/editar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $cuentasConfig = [
        [
            'campo' => 'cuentacontable_provision_facturas_id',
            'label' => 'Provisión facturas a recibir',
            'relacion' => optional($config)->cuentacontable_provision_facturas,
        ],
        [
            'campo' => 'cuentacontable_factura_anticipada_id',
            'label' => 'Factura anticipada',
            'relacion' => optional($config)->cuentacontable_factura_anticipada,
        ],
        [
            'campo' => 'cuentacontable_anticipo_bienes_uso_id',
            'label' => 'Anticipo bienes de uso',
            'relacion' => optional($config)->cuentacontable_anticipo_bienes_uso,
        ],
        [
            'campo' => 'cuentacontable_proveedores_intangible_id',
            'label' => 'Proveedores intangible',
            'relacion' => optional($config)->cuentacontable_proveedores_intangible,
        ],
    ];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cog"></i> Configuración contable por empresa</h3>
                <div class="card-tools">
                    <a href="{{ route('recepcion_proveedor') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> Volver a Recepciones
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_configuracion_recepcion_proveedor') }}" method="POST" id="form-config-contable" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id,
                        'col_label' => 'col-lg-3 col-form-label',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label"></label>
                        <div class="col-lg-8">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="activa_contabilidad" name="activa_contabilidad" value="1"
                                    @checked(old('activa_contabilidad', $config->activa_contabilidad ?? config('recepcion_proveedor.contabilidad_activa')))>
                                <label class="custom-control-label" for="activa_contabilidad">Generar asiento contable al confirmar recepción</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-8">
                            <table class="table table-sm table-bordered table-hover mb-0" id="cuentas-contables-recepcion-table">
                                <thead>
                                    <tr style="background-color:#85C1E9">
                                        <th style="width:28%">Concepto</th>
                                        <th style="width:18%">C&oacute;digo</th>
                                        <th style="width:54%">Descripci&oacute;n</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cuentasConfig as $cuentaFila)
                                        @include('configuracion.recepcion_proveedor.partials.fila_cuentacontable', [
                                            'campo' => $cuentaFila['campo'],
                                            'label' => $cuentaFila['label'],
                                            'config' => $config,
                                            'relacion' => $cuentaFila['relacion'],
                                        ])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>

        <div class="card card-secondary mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-sliders-h"></i> Tolerancias de diferencias por centro de costo</h3>
            </div>
            <form action="{{ route('guardar_tolerancias_recepcion_proveedor') }}" method="POST" id="form-tolerancias" autocomplete="off">
                @csrf
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Configure tolerancias solo para los centros de costo que lo requieran.
                        Los centros sin regla usan los valores por defecto del sistema (variables <code>RECEPCION_PROVEEDOR_TOL_*</code>).
                        Prefijo LAB ({{ config('recepcion_proveedor.sku_prefijo_laboratorio') }}) o uso de artículo laboratorio disparan aviso configurable.
                    </p>
                    <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                    <table class="table table-sm table-bordered table-hover mb-2" id="tolerancia-recepcion-table">
                        <thead>
                            <tr style="background-color:#85C1E9">
                                <th style="width:32%">Centro de costo</th>
                                <th style="width:10%">C&oacute;digo</th>
                                <th style="width:14%">Tol. cantidad %</th>
                                <th style="width:14%">Tol. precio %</th>
                                <th style="width:16%">Tol. precio abs.</th>
                                <th style="width:6%"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-tolerancia-recepcion">
                            @foreach ($filasTolerancia as $tol)
                                @include('configuracion.recepcion_proveedor.partials.fila_tolerancia', [
                                    'tolerancia' => $tol,
                                    'indice' => $loop->index,
                                    'centrocosto_query' => $centrocosto_query,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                    @include('configuracion.recepcion_proveedor.template_tolerancia', ['centrocosto_query' => $centrocosto_query])
                    <div class="row">
                        <div class="col-md-12">
                            <button type="button" id="agrega-renglon-tolerancia" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-secondary"><i class="fa fa-save"></i> Guardar tolerancias</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@endsection
