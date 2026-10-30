@extends("theme.$theme.layout")

@section('titulo')
    {{ $edicion ? 'Editar cotización' : 'Nueva cotización' }}
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
                <h3 class="card-title">{{ $edicion ? 'Editar cotización' : 'Nueva cotización' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_cotizacion', $filtrosQuery) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_tesoreria_crown_cotizacion') }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" data-enter-campos="1">
                @csrf
                <div class="card-body">
                    <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Sala</label>
                        <div class="col-lg-3">
                            <input type="text" class="form-control" readonly value="{{ $sala->codigo }} — {{ $sala->nombre }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="jornada" class="col-lg-4 control-label text-right pr-2 requerido">Jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="jornada" id="jornada" class="form-control" required value="{{ old('jornada', $jornada) }}" @if($edicion) readonly @endif>
                        </div>
                    </div>
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title mb-0">Tasas compra / venta</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Tipo de valor</th>
                                        <th>Compra</th>
                                        <th>Venta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($filas as $fila)
                                        @php
                                            $tipo = $fila['tipo'];
                                            $compra = old('compra.'.$tipo->id, $fila['compra']);
                                            $venta = old('venta.'.$tipo->id, $fila['venta']);
                                        @endphp
                                        <tr>
                                            <td>{{ $tipo->codigo }} — {{ $tipo->nombre }}</td>
                                            <td>
                                                @if ($tipo->cotizacion_fija)
                                                    <input type="text" class="form-control form-control-sm text-right" value="1" readonly>
                                                    <input type="hidden" name="compra[{{ $tipo->id }}]" value="1">
                                                @else
                                                    <input type="number" class="form-control form-control-sm text-right" name="compra[{{ $tipo->id }}]" min="0" step="0.000001" required value="{{ $compra }}">
                                                @endif
                                            </td>
                                            <td>
                                                @if ($tipo->cotizacion_fija)
                                                    <input type="text" class="form-control form-control-sm text-right" value="1" readonly>
                                                    <input type="hidden" name="venta[{{ $tipo->id }}]" value="1">
                                                @else
                                                    <input type="number" class="form-control form-control-sm text-right" name="venta[{{ $tipo->id }}]" min="0" step="0.000001" required value="{{ $venta }}">
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p class="text-muted mb-0">El stock de dólares y euros queda en cantidad. Esta cotización solo valúa los informes en pesos. Fichas y pesos quedan en 1.</p>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
