@extends("theme.$theme.layout")
@section('titulo')
    Editar orden de pago
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
@include('includes.contable.asiento_montos_formato_js')
<script src="{{ asset('assets/pages/scripts/contable/asiento/asiento_externo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/asiento_externo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/cbu_pago.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/banco/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/cheques.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/form.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/crear.js') }}" type="text/javascript"></script>
<script>
    $(function () {
        if (typeof activa_eventos_consulta_cbu_pago === 'function') {
            activa_eventos_consulta_cbu_pago();
        }
    });
</script>
@endsection

@section('contenido')
@php
    $estado = (string) ($data->estado ?? '');
    $puedeActualizar = can('actualizar-pagoproveedor', false) && ! in_array($estado, \App\Models\Compras\Pagoproveedor::estadosFinalesBloqueados(), true);
    $puedeConfirmar = can('confirmar-pagoproveedor', false) && $estado === 'PRE CARGA';
    $puedeEliminar = can('borrar-pagoproveedor', false) && $estado === 'PRE CARGA';
    $puedeAnular = can('anular-pagoproveedor', false) && ! in_array($estado, ['BAJA', 'REVERTIDA'], true);
    $puedeRevertir = can('revertir-pagoproveedor', false) && in_array($estado, ['CONFIRMADA', 'PAGADA', 'CONCILIADA'], true);
    $puedePagada = can('marcar-pagada-pagoproveedor', false) && $estado === 'CONFIRMADA';
    $puedeConciliada = can('marcar-conciliada-pagoproveedor', false) && in_array($estado, ['CONFIRMADA', 'PAGADA'], true);
@endphp
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar orden de pago — {{ $estado }}</h3>
                <div class="card-tools">
                    <a href="{{ route('pagoproveedor') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
                </div>
            </div>
            <form action="{{ route('actualizar_pagoproveedor', $data->id) }}" method="POST" id="form-pagoproveedor" class="form-horizontal form--label-right" autocomplete="off">
                @csrf
                @method('PUT')
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm"><i class="fa fa-user"></i> Datos / Deuda</button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Cuentas</button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Cheques</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Retenciones</button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Historia</button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Asiento Contable</button>
                </div>
                <div class="card-body">
                    @include('compras.pagoproveedor.form')
                    @include('compras.pagoproveedor.form2')
                    @include('compras.pagoproveedor.form3')
                    @include('compras.pagoproveedor.form4')
                    @include('compras.pagoproveedor.form5')
                    @include('includes.contable.formasientoexterno')
                </div>
            </form>
            <div class="card-footer">
                @if ($puedeActualizar)
                    <button type="submit" class="btn btn-success" form="form-pagoproveedor" id="botonform0">
                        <i class="fa fa-save"></i> Actualizar
                    </button>
                @endif
                @if ($puedeConfirmar)
                    <form action="{{ route('confirmar_pagoproveedor', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Confirmar esta orden de pago?');">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Confirmar</button>
                    </form>
                @endif
                @if ($puedePagada)
                    <form action="{{ route('marcar_pagada_pagoproveedor', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Marcar como PAGADA? (bridge Interbanking pendiente)');">
                        @csrf
                        <button type="submit" class="btn btn-outline-success"><i class="fa fa-university"></i> Marcar pagada</button>
                    </form>
                @endif
                @if ($puedeConciliada)
                    <form action="{{ route('marcar_conciliada_pagoproveedor', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Marcar como CONCILIADA?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-info"><i class="fa fa-balance-scale"></i> Marcar conciliada</button>
                    </form>
                @endif
                @if ($puedeRevertir)
                    <form action="{{ route('revertir_pagoproveedor', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Revertir esta OP (compensatoria)?');">
                        @csrf
                        <button type="submit" class="btn btn-warning"><i class="fa fa-undo"></i> Revertir</button>
                    </form>
                @endif
                @if ($puedeAnular)
                    <form action="{{ route('anular_pagoproveedor', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Anular físicamente esta OP? Esta acción no se puede deshacer.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="fa fa-ban"></i> Anular</button>
                    </form>
                @endif
                @if ($puedeEliminar)
                    <form action="{{ route('eliminar_pagoproveedor', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta precarga?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="fa fa-times-circle"></i> Eliminar</button>
                    </form>
                @endif
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('imprimir_pagoproveedor', $data->id) }}">
                    <i class="fa fa-print"></i> Imprimir
                </a>
            </div>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.compras.modalconsultacbupago')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.contable.modalconsultacuentacontable')
@endsection
