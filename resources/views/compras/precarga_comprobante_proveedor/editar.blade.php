@extends("theme.$theme.layout")
@section('titulo')
    Precarga de Comprobantes de Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/precarga_comprobante_proveedor/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @if (($data->estado ?? '') === \App\Support\Compras\PrecargaComprobanteEstados::CARGADA_ANITA)
        <div class="alert alert-info">
            <i class="fa fa-check-circle"></i>
            Esta precarga está marcada como <strong>ya cargada en Anita</strong>
            @if (!empty($data->anita_nro_interno))
                (nro. interno {{ $data->anita_nro_interno }})
            @endif
            y no se puede generar el comprobante desde el ERP.
        </div>
        @elseif (\App\Support\Compras\PrecargaComprobanteEstados::puedeMarcarCargadaAnita((string) ($data->estado ?? '')) && ! $data->comprobante_proveedor)
        <div class="alert alert-light border">
            Si esta factura ya se cargó en Anita, podés marcarla para sacarla de Pendientes.
            @include('compras.precarga_comprobante_proveedor.partials.boton_marcar_cargada_anita', [
                'precargaId' => $data->id,
                'claseBoton' => 'btn btn-outline-info btn-sm',
                'etiquetaBoton' => 'Marcar como ya cargada en Anita',
            ])
        </div>
        @endif
        @if ($data->comprobante_proveedor)
        <div class="alert alert-warning">
            <i class="fa fa-info-circle"></i>
            Esta precarga ya tiene el comprobante
            <strong>#{{ $data->comprobante_proveedor->id }}</strong>
            ({{ $data->comprobante_proveedor->estado }}
            @if (filled($data->comprobante_proveedor->letra) || filled($data->comprobante_proveedor->numerocomprobante))
                — {{ $data->comprobante_proveedor->letra }}{{ $data->comprobante_proveedor->sucursal }}-{{ $data->comprobante_proveedor->numerocomprobante }}
            @endif
            ).
            No se puede generar otro desde aquí.
            @if (can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false))
                <a href="{{ route('editar_comprobante_proveedor', ['id' => $data->comprobante_proveedor->id]) }}" class="alert-link">
                    Abrir comprobante #{{ $data->comprobante_proveedor->id }}
                </a>
            @endif
        </div>
        @endif
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Precarga de Comprobantes de Proveedores</h3>&nbsp;ID:&nbsp;{{$data->id }}
                <div class="card-tools">
                    @if ($data->comprobante_proveedor && (can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false)))
                    <a href="{{ route('editar_comprobante_proveedor', ['id' => $data->comprobante_proveedor->id]) }}" class="btn btn-outline-primary btn-sm mr-2">
                        <i class="fa fa-external-link"></i> Ver comprobante #{{ $data->comprobante_proveedor->id }}
                    </a>
                    @endif
                    @include('compras.precarga_comprobante_proveedor.partials.boton_ver_factura_pdf', [
                        'precargaId' => $data->id,
                        'rutaalmacenamiento' => $data->rutaalmacenamiento ?? null,
                        'claseExtra' => 'mr-2',
                    ])
                    <a href="{{route('precarga_comprobante_proveedor')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_precarga_comprobante_proveedor', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
                    @include('compras.precarga_comprobante_proveedor.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if (($data->estado ?? '') !== \App\Support\Compras\PrecargaComprobanteEstados::CARGADA_ANITA)
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
