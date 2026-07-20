@php
    use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
@endphp
<div id="dotacion-panel"
     data-url-crear="{{ route('guardar_dotacion_agrupamiento', ['id' => $agrupamiento->id]) }}"
     data-url-update-base="{{ url('sueldos/agrupamiento/dotacion') }}">

    <div class="d-flex align-items-center mb-3">
        <div>
            <h5 class="mb-0">Dotaci&oacute;n anual de indumentaria</h5>
            <small class="text-muted">Agrupamiento #{{ $agrupamiento->codigo }} — {{ $agrupamiento->descripcion }}</small>
        </div>
        @if ($puedeEditar)
            <form action="{{ route('sincronizar_dotacion_agrupamiento') }}" method="POST" class="ml-auto"
                  onsubmit="return confirm('¿Sincronizar la dotación de TODOS los agrupamientos desde Anita? Solo se agregan filas nuevas.');">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                </button>
            </form>
        @endif
    </div>

    @if ($puedeEditar)
    <form id="form-dotacion" class="mb-3 border rounded p-2 bg-light">
        <input type="hidden" id="dotacion_id" value="">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="dotacion_sexo">Sexo</label>
                <select id="dotacion_sexo" class="form-control form-control-sm">
                    @foreach ($sexos as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-1" for="dotacion_prenda">Prenda</label>
                <select id="dotacion_prenda" class="form-control form-control-sm">
                    <option value="">— Prenda —</option>
                    @foreach ($prendas as $p)
                        <option value="{{ $p->id }}">{{ $p->codigo }} - {{ $p->descripcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 mb-2">
                <label class="small mb-1" for="dotacion_color">Color (opcional)</label>
                <select id="dotacion_color" class="form-control form-control-sm">
                    <option value="">— Cualquiera —</option>
                    @foreach ($colores as $c)
                        <option value="{{ $c->id }}">{{ $c->codigo }} - {{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-1 mb-2">
                <label class="small mb-1" for="dotacion_limite">Tope/a&ntilde;o</label>
                <input type="number" step="0.01" min="0" id="dotacion_limite" class="form-control form-control-sm" value="1">
            </div>
            <div class="form-group col-md-1 mb-2">
                <label class="small mb-1" for="dotacion_orden">Orden</label>
                <input type="number" min="0" id="dotacion_orden" class="form-control form-control-sm" value="0">
            </div>
            <div class="form-group col-md-1 mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block" id="btn-guardar-dotacion" title="Agregar / actualizar">
                    <i class="fa fa-save"></i>
                </button>
            </div>
        </div>
        <div>
            <span id="dotacion-form-titulo" class="small text-muted">Agregar prenda a la dotaci&oacute;n</span>
            <button type="button" class="btn btn-link btn-sm text-secondary d-none py-0" id="btn-cancelar-dotacion">Cancelar edici&oacute;n</button>
        </div>
    </form>
    @endif

    <div class="row">
        @foreach ($sexos as $val => $label)
            @php $filas = $dotacion[$val] ?? collect(); @endphp
            <div class="col-lg-6">
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-2">
                        <h6 class="card-title mb-0">
                            <i class="fa fa-fw {{ $val === '1' ? 'fa-male' : 'fa-female' }}"></i>
                            {{ $label }} <span class="badge badge-secondary">{{ $filas->count() }}</span>
                        </h6>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width:44%">Prenda</th>
                                    <th style="width:26%">Color</th>
                                    <th class="text-center" style="width:16%">Tope/a&ntilde;o</th>
                                    @if ($puedeEditar)<th class="text-center" style="width:14%"></th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    @php
                                        $dataDotacion = [
                                            'id' => $fila->id,
                                            'sexo' => $fila->sexo,
                                            'prenda_id' => $fila->prenda_id,
                                            'color_id' => $fila->color_id,
                                            'limite_anual' => (float) $fila->limite_anual,
                                            'orden' => $fila->orden,
                                        ];
                                    @endphp
                                    <tr>
                                        <td>{{ $fila->prenda->codigo ?? '' }} - {{ $fila->prenda->descripcion ?? '—' }}</td>
                                        <td>{{ $fila->color ? ($fila->color->nombre) : 'Cualquiera' }}</td>
                                        <td class="text-center">{{ rtrim(rtrim(number_format($fila->limite_anual, 2, '.', ''), '0'), '.') }}</td>
                                        @if ($puedeEditar)
                                        <td class="text-center text-nowrap">
                                            <button type="button" class="btn btn-sm btn-link p-0 mr-1 btn-editar-dotacion"
                                                data-dotacion="{{ json_encode($dataDotacion) }}">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-eliminar-dotacion"
                                                data-url="{{ route('eliminar_dotacion_agrupamiento', ['id' => $fila->id]) }}">
                                                <i class="fa fa-times-circle"></i>
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $puedeEditar ? 4 : 3 }}" class="text-center text-muted py-3">Sin prendas asignadas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
