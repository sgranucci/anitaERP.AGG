@extends("theme.$theme.layout")
@section('titulo')
    Firma del recibo de sueldos
@endsection

@section('styles')
<style>
    .firma-preview-thumb {
        display: inline-block;
        padding: 4px 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background: #fff;
        min-width: 90px;
        text-align: center;
    }
    .firma-preview-thumb img {
        max-height: 48px;
        max-width: 140px;
        vertical-align: middle;
    }
    .firma-preview-edit img {
        max-height: 72px;
        max-width: 220px;
    }
    .tabla-firmas tr.fila-activa {
        background: #eaf2f8;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-outline card-secondary mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-list"></i> Firmas cargadas</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0 tabla-firmas">
                        <thead>
                            <tr>
                                <th style="width:22%">Ámbito / Empresa</th>
                                <th style="width:18%">Firmante</th>
                                <th style="width:16%">Cargo</th>
                                <th style="width:18%">Firma</th>
                                <th style="width:14%">Origen</th>
                                <th style="width:12%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($listado as $fila)
                                @php
                                    $activa = ($ambito === $fila['ambito'])
                                        && (
                                            $ambito === 'global'
                                            || (int) $empresa_id === (int) ($fila['empresa_id'] ?? 0)
                                        );
                                    $href = $fila['ambito'] === 'global'
                                        ? route('recibo_firma_sueldos', ['ambito' => 'global'])
                                        : route('recibo_firma_sueldos', ['ambito' => 'empresa', 'empresa_id' => $fila['empresa_id']]);
                                @endphp
                                <tr class="{{ $activa ? 'fila-activa' : '' }}">
                                    <td>
                                        @if ($fila['ambito'] === 'global')
                                            <span class="badge badge-secondary">Global</span>
                                            {{ $fila['empresa_nombre'] }}
                                        @else
                                            {{ $fila['empresa_nombre'] }}
                                        @endif
                                    </td>
                                    <td>{{ $fila['nombre'] }}</td>
                                    <td>{{ $fila['cargo'] }}</td>
                                    <td>
                                        @if (! empty($fila['preview_src']))
                                            <span class="firma-preview-thumb">
                                                <img src="{{ $fila['preview_src'] }}" alt="Firma {{ $fila['empresa_nombre'] }}">
                                            </span>
                                        @else
                                            <span class="text-muted">Sin imagen</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fila['tiene_propia'])
                                            <span class="badge badge-success">Propia</span>
                                        @elseif ($fila['origen'] === 'global')
                                            <span class="badge badge-info">Usa global</span>
                                        @else
                                            <span class="badge badge-light">Default</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ $href }}" class="btn btn-xs {{ $activa ? 'btn-primary' : 'btn-outline-primary' }}">
                                            {{ $activa ? 'Editando' : 'Editar' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted text-center py-3">No hay empresas para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-pencil"></i>
                    Editar firma
                    @if ($ambito === 'global')
                        — Global
                    @else
                        — {{ optional($empresa_query->firstWhere('id', $empresa_id))->nombre ?? 'Empresa' }}
                    @endif
                </h3>
            </div>
            <form method="POST" action="{{ route('actualizar_recibo_firma_sueldos') }}" class="form-horizontal" autocomplete="off" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        Prioridad al emitir el recibo:
                        <strong>empresa</strong> → <strong>global</strong> → default del sistema.
                        Elegí una fila arriba o cambiá el ámbito acá.
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="ambito">Ámbito</label>
                        <div class="col-lg-4">
                            <select name="ambito" id="ambito" class="form-control"
                                onchange="var e=document.getElementById('empresa_id'); window.location='{{ route('recibo_firma_sueldos') }}?ambito='+this.value+(e&&this.value==='empresa'?'&empresa_id='+e.value:'');">
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
                                    onchange="window.location='{{ route('recibo_firma_sueldos') }}?ambito=empresa&empresa_id='+this.value">
                                    @foreach ($empresa_query as $e)
                                        <option value="{{ $e->id }}" @selected((int) $empresa_id === (int) $e->id)>{{ $e->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if (($origen ?? '') === 'global' || ($origen ?? '') === 'default')
                            <div class="alert alert-warning py-2">
                                Esta empresa todavía no tiene firma propia: se está mostrando el fallback
                                <strong>{{ $origen === 'global' ? 'global' : 'default del sistema' }}</strong>.
                                Al guardar se crea la configuración de esta empresa.
                            </div>
                        @endif
                    @else
                        <input type="hidden" name="empresa_id" value="">
                    @endif

                    <hr>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="nombre">Nombre del firmante</label>
                        <div class="col-lg-5">
                            <input type="text" class="form-control" name="nombre" id="nombre" maxlength="120"
                                   value="{{ old('nombre', $datos['nombre']) }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="cargo">Cargo</label>
                        <div class="col-lg-5">
                            <input type="text" class="form-control" name="cargo" id="cargo" maxlength="120"
                                   value="{{ old('cargo', $datos['cargo']) }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="imagen">Imagen de firma</label>
                        <div class="col-lg-7">
                            @if (! empty($preview_src))
                                <div class="mb-2 p-2 border rounded bg-white d-inline-block firma-preview-edit">
                                    <img src="{{ $preview_src }}" alt="Vista previa firma">
                                </div>
                                <div class="small text-muted mb-2">
                                    @if (! empty($tiene_subida))
                                        Imagen cargada para este ámbito.
                                    @else
                                        Mostrando la firma default del sistema.
                                    @endif
                                </div>
                            @endif
                            <input type="file" class="form-control-file" name="imagen" id="imagen"
                                   accept="image/png,image/jpeg,.png,.jpg,.jpeg">
                            @if (! empty($tiene_subida))
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="quitar_imagen" id="quitar_imagen" value="1">
                                    <label class="form-check-label" for="quitar_imagen">
                                        Quitar imagen cargada (usar default)
                                    </label>
                                </div>
                            @endif
                            <small class="form-text text-muted">PNG o JPG, hasta 2 MB.</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
