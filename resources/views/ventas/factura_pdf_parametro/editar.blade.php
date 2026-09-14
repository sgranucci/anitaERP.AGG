@extends("theme.$theme.layout")
@section('titulo')
    Parámetros PDF factura
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-file-text-o"></i> Parámetros PDF de factura</h3>
            </div>
            <form method="POST" action="{{ route('actualizar_factura_pdf_parametro') }}" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        Textos de membrete y pie del PDF (FAC/REM). Prioridad:
                        <strong>empresa</strong> → <strong>global</strong> → <code>.env</code>.
                        No viven en el punto de venta: el PV aporta domicilio/teléfono/CUIT de emisión.
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="ambito">Ámbito</label>
                        <div class="col-lg-4">
                            <select name="ambito" id="ambito" class="form-control"
                                onchange="var e=document.getElementById('empresa_id'); window.location='{{ route('factura_pdf_parametro') }}?ambito='+this.value+(e&&this.value==='empresa'?'&empresa_id='+e.value:'');">
                                <option value="empresa" @selected($ambito === 'empresa')>Por empresa</option>
                                <option value="global" @selected($ambito === 'global')>Global (todas)</option>
                            </select>
                        </div>
                    </div>

                    @if ($ambito === 'empresa')
                        <div class="form-group row">
                            <label class="col-lg-3 control-label text-right pr-2" for="empresa_id">Empresa</label>
                            <div class="col-lg-4">
                                <select name="empresa_id" id="empresa_id" class="form-control"
                                    onchange="window.location='{{ route('factura_pdf_parametro') }}?ambito=empresa&empresa_id='+this.value">
                                    @foreach ($empresa_query as $e)
                                        <option value="{{ $e->id }}" @selected((int) $empresa_id === (int) $e->id)>{{ $e->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="empresa_id" value="">
                    @endif

                    <hr>
                    @foreach ($filas as $fila)
                        @php $idCampo = 'param_'.$fila['clave']; @endphp
                        <div class="form-group row">
                            <label for="{{ $idCampo }}" class="col-lg-3 control-label text-right pr-2">{{ $fila['etiqueta'] }}</label>
                            <div class="col-lg-8">
                                <input type="text"
                                    class="form-control"
                                    name="valores[{{ $fila['clave'] }}]"
                                    id="{{ $idCampo }}"
                                    value="{{ old('valores.'.$fila['clave'], $fila['valor']) }}"
                                    maxlength="500">
                                <small class="form-text text-muted">{{ $fila['ayuda'] }} <code>{{ $fila['clave'] }}</code></small>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
