@extends("theme.$theme.layout")
@section('titulo')
Cuentas automáticas del sistema
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuenta_automatica/editar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-link"></i> Cuentas contables automáticas por empresa</h3>
            </div>
            <form action="{{ route('actualizar_cuentas_automaticas_contables') }}" method="POST" id="form-cuentas-automaticas" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Pat&aacute;s fijas de asientos que no salen de art&iacute;culo, cliente, proveedor ni cuenta de caja.
                        Recepci&oacute;n proveedores y cierre Waitry pueden tener override en su propia configuraci&oacute;n;
                        si existe override de m&oacute;dulo, el proceso usa esa cuenta (no la de este cat&aacute;logo).
                        Solo se listan empresas activas (asignadas a al menos un usuario).
                    </p>
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id,
                        'col_label' => 'col-lg-3 col-form-label',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-8">
                            <table class="table table-sm table-bordered table-hover mb-0" id="cuentas-automaticas-table">
                                <thead>
                                    <tr style="background-color:#85C1E9">
                                        <th style="width:14%">M&oacute;dulo</th>
                                        <th style="width:26%">Uso en el sistema</th>
                                        <th style="width:14%">C&oacute;digo</th>
                                        <th style="width:34%">Descripci&oacute;n cuenta</th>
                                        <th style="width:10%">Efectivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        @include('contable.cuenta_automatica.partials.fila', ['fila' => $fila])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar cat&aacute;logo</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@endsection
