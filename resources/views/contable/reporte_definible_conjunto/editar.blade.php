@extends("theme.$theme.layout")
@section('titulo')
    Set {{ $data->codigo }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Set {{ $data->codigo }} — {{ $data->nombre }}</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_definible_conjunto') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="post" action="{{ route('actualizar_reporte_definible_conjunto', $data->id) }}" class="form-horizontal">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('includes.form-error')
                    @include('includes.mensaje')
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Código</label>
                        <div class="col-lg-4">
                            <input type="text" name="codigo" class="form-control" maxlength="30"
                                   value="{{ old('codigo', $data->codigo) }}" required @if(!$puede_actualizar) readonly @endif>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Nombre</label>
                        <div class="col-lg-8">
                            <input type="text" name="nombre" class="form-control" maxlength="80"
                                   value="{{ old('nombre', $data->nombre) }}" required @if(!$puede_actualizar) readonly @endif>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Observaciones</label>
                        <div class="col-lg-8">
                            <textarea name="observaciones" class="form-control" rows="2" @if(!$puede_actualizar) readonly @endif>{{ old('observaciones', $data->observaciones) }}</textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-8 offset-lg-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1"
                                       @if(old('activo', $data->activo)) checked @endif @if(!$puede_actualizar) disabled @endif>
                                <label class="custom-control-label" for="activo">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                @if ($puede_actualizar)
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                @endif
            </form>
        </div>

        <div class="card card-outline card-info mt-3">
            <div class="card-header">
                <strong>Cuentas del set</strong>
                <span class="badge badge-info">{{ $data->cuentas->count() }}</span>
            </div>
            <div class="card-body">
                @if ($puede_actualizar)
                    <form method="post" action="{{ route('guardar_cuenta_reporte_definible_conjunto', $data->id) }}" class="form-row align-items-end mb-3">
                        @csrf
                        <div class="form-group col-md-4 mb-2">
                            <label class="small mb-0">Código desde</label>
                            <input type="number" name="codigo_cuenta" class="form-control form-control-sm" required min="1">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Hasta</label>
                            <input type="number" name="codigo_hasta" class="form-control form-control-sm" min="1" placeholder="Opc.">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Origen</label>
                            <select name="origen" class="form-control form-control-sm">
                                <option value="R">R Real</option>
                                <option value="P">P Plan def.</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Signo</label>
                            <select name="signo" class="form-control form-control-sm">
                                <option value="1">+</option>
                                <option value="-1">−</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">Agregar</button>
                        </div>
                    </form>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Origen</th>
                                <th>Signo</th>
                                @if ($puede_actualizar)<th style="width:60px"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data->cuentas as $cta)
                                <tr>
                                    <td>
                                        {{ $cta->codigo_cuenta }}
                                        @if ($cta->codigo_hasta)
                                            – {{ $cta->codigo_hasta }}
                                        @endif
                                    </td>
                                    <td>{{ $cta->cuentacontable->nombre ?? '' }}</td>
                                    <td>{{ $cta->origen }}</td>
                                    <td>{{ (int)$cta->signo < 0 ? '−' : '+' }}</td>
                                    @if ($puede_actualizar)
                                        <td class="text-center">
                                            <form method="post" action="{{ route('eliminar_cuenta_reporte_definible_conjunto', ['id' => $data->id, 'cuentaId' => $cta->id]) }}"
                                                  onsubmit="return confirm('¿Quitar cuenta?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0">
                                                    <i class="fa fa-times-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $puede_actualizar ? 5 : 4 }}" class="text-center text-muted">Sin cuentas</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($puede_actualizar)
            <form method="post" action="{{ route('eliminar_reporte_definible_conjunto', $data->id) }}"
                  class="mt-3" onsubmit="return confirm('¿Eliminar el set completo?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar set</button>
            </form>
        @endif
    </div>
</div>
@endsection
