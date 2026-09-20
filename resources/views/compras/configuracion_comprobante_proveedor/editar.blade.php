@extends("theme.$theme.layout")
@section('titulo')
Configuración comprobante proveedor
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/compras/configuracion_comprobante_proveedor/editar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/configuracion_comprobante_proveedor/editar.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cog"></i> Configuración comprobante proveedor</h3>
                <div class="card-tools">
                    <a href="{{ route('comprobante_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a comprobantes
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_configuracion_comprobante_proveedor') }}" method="POST" id="form-config-cp" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id,
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])

                    @include('compras.configuracion_comprobante_proveedor.partials.flujo_proceso_selector')

                    @include('compras.configuracion_comprobante_proveedor.partials.contabilidad_com_selector', [
                        'comGeneraContabilidad' => $comGeneraContabilidad,
                        'empresa_id' => $empresa_id,
                    ])

                    @include('compras.configuracion_comprobante_proveedor.partials.controles_linea', [
                        'config' => $config,
                    ])

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2"></label>
                        <div class="col-lg-8">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1"
                                    @checked(old('activo', $config->activo ?? true))>
                                <label class="custom-control-label" for="activo">Controles de legajo activos</label>
                            </div>
                            <p class="text-muted small mb-0">
                                Si está activo: tolerancia de importe factura vs recepción COM por centro de costo de la OC,
                                cotización ME y (si se habilitan arriba) match SKU/precio.
                                Default importe (sin CC específico): 5% (editable en la grilla de abajo). Centro de costo 85 (Gastronomía): 5% salvo que se indique otra cosa.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">Guardar configuración</button>
                </div>
            </form>
        </div>

        <div class="card card-outline card-info mt-3">
            <div class="card-header">
                <h3 class="card-title">Tolerancias de importe por centro de costo</h3>
                <p class="text-muted mb-0 mt-1" style="font-size:12px;">
                    Cada sentido se controla por separado. Dejar un límite vacío significa
                    <strong>no verificar ese caso</strong>: sirve para permitir facturación parcial contra una
                    misma COM sin frenar el legajo. Si se completan el % y el importe, alcanza con que se pase
                    uno de los dos. Diferencias de hasta 0,05 siempre se aceptan.
                </p>
                <p class="text-muted mb-0 mt-1" style="font-size:12px;">
                    <strong>Qué hacer si se pasa</strong> define la reacción del circuito.
                    <em>No dejar cargar y devolver a Compras</em> frena la factura y avisa por correo: el
                    problema no entra a la contabilidad pero el legajo queda detenido.
                    <em>Cargar y bloquear para pago</em> es el criterio de SAP: la factura se contabiliza y
                    queda en la cuenta del proveedor, pero ninguna propuesta de pago la toma hasta que
                    alguien la libere explicando por qué. Cierra el circuito contable sin pagar la diferencia.
                </p>
            </div>
            <form action="{{ route('guardar_tolerancias_comprobante_proveedor') }}" method="POST" id="form-tolerancias-cp" autocomplete="off">
                @csrf
                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-bordered mb-0" id="tolerancia-cp-table">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th rowspan="2">Centro de costo</th>
                                <th colspan="2" class="text-center">Factura mayor que la COM (sobrefacturación)</th>
                                <th colspan="2" class="text-center">Factura menor que la COM (facturación parcial)</th>
                                <th rowspan="2" style="width:230px;" class="text-center">Qué hacer si se pasa</th>
                                <th rowspan="2" class="width40"></th>
                            </tr>
                            <tr>
                                <th style="width:120px;" class="text-center">Límite %</th>
                                <th style="width:140px;" class="text-center">Límite importe</th>
                                <th style="width:120px;" class="text-center">Límite %</th>
                                <th style="width:140px;" class="text-center">Límite importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filasTolerancia as $indice => $fila)
                                @include('compras.configuracion_comprobante_proveedor.partials.fila_tolerancia', [
                                    'indice' => $indice,
                                    'fila' => $fila,
                                    'centrocosto_query' => $centrocosto_query,
                                ])
                            @empty
                                @include('compras.configuracion_comprobante_proveedor.partials.fila_tolerancia', [
                                    'indice' => 0,
                                    'fila' => null,
                                    'centrocosto_query' => $centrocosto_query,
                                    'forzar_default' => true,
                                ])
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-tolerancia-cp">
                        <i class="fa fa-plus"></i> Agregar centro de costo
                    </button>
                    <button type="submit" class="btn btn-success">Guardar tolerancias</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('compras.configuracion_comprobante_proveedor.partials.template_tolerancia', [
    'centrocosto_query' => $centrocosto_query,
])
@endsection
