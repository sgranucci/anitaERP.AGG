{{-- Importación: siempre visible, colapsado por defecto (AGG / Crown / todas las altas). --}}
@php
    $vinculosImp = $importacion_gastos_vinculados ?? [];
    $prefImp = $importacion_form ?? [];
    $bloqueado = ! empty($bloqueado_edicion);
    $puedeAsociar = can('listar-importacion', false)
        || can('editar-importacion', false)
        || can('crear-importacion', false)
        || can('crear-comprobante-proveedor', false)
        || can('editar-comprobante-proveedor', false)
        || can('actualizar-comprobante-proveedor', false);
    $hayVinculos = is_array($vinculosImp) && $vinculosImp !== [];
    $cpColLabel = $cpColLabel ?? 'col-lg-4 control-label text-right pr-2';
    $cpColInput = $cpColInput ?? 'col-lg-8';
    $impCollapseId = 'cp-panel-importacion-body';
@endphp
@if ($puedeAsociar || $hayVinculos)
<div class="card card-outline card-info mb-3" id="cp-panel-importacion">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <button type="button"
                    class="btn btn-link btn-sm text-left p-0 text-dark text-decoration-none collapsed"
                    data-toggle="collapse"
                    data-target="#{{ $impCollapseId }}"
                    aria-expanded="false"
                    aria-controls="{{ $impCollapseId }}"
                    id="btn-cp-toggle-importacion">
                <i class="fa fa-ship"></i> Importaci&oacute;n
                <small class="text-muted font-weight-normal">
                    Carpeta de comercio exterior (opcional)
                    @if ($hayVinculos)
                        <span class="badge badge-info ml-1">{{ count($vinculosImp) }}</span>
                    @endif
                </small>
                <i class="fa fa-chevron-right ml-1 text-muted js-cp-imp-chevron" style="font-size:.75rem;"></i>
            </button>
        </h3>
    </div>
    <div id="{{ $impCollapseId }}" class="collapse" aria-labelledby="btn-cp-toggle-importacion">
        <div class="card-body py-2">
            @if ($puedeAsociar)
                @include('compras.partials.campo_consulta_importacion', [
                    'prefix' => 'cp',
                    'importacionId' => old('importacion_id', $prefImp['importacion_id'] ?? ''),
                    'codigo' => old('importacion_codigo', $prefImp['importacion_codigo'] ?? ''),
                    'estado' => old('importacion_estado', $prefImp['importacion_estado'] ?? ''),
                    'inputName' => 'importacion_id',
                    'inputId' => 'cp_importacion_id',
                    'required' => false,
                    'readonly' => $bloqueado,
                    'col_label' => $cpColLabel,
                    'col_input' => $cpColInput,
                    'ayuda' => 'Opcional (flete, seguro, despachante…). Al guardar se crea/actualiza un gasto <strong>real</strong> en la carpeta con el total de esta factura.',
                ])

                <div class="form-group row">
                    <label class="{{ $cpColLabel }}">Tipo de gasto</label>
                    <div class="{{ $cpColInput }}">
                        <input type="hidden" name="importacion_gasto_tipo_id" id="cp_importacion_gasto_tipo_id"
                               value="{{ old('importacion_gasto_tipo_id', $prefImp['importacion_gasto_tipo_id'] ?? '') }}">
                        <div class="d-flex align-items-center" style="gap:4px;">
                            @if (! $bloqueado)
                                <button type="button" class="btn-accion-tabla" id="btn-cp-consulta-gasto-tipo" title="Tipo de gasto (F1)">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                            @endif
                            <input type="text" id="cp_importacion_gasto_tipo_codigo" class="form-control"
                                   style="width:5rem; flex-shrink:0;"
                                   value="{{ old('importacion_gasto_tipo_codigo', $prefImp['importacion_gasto_tipo_codigo'] ?? '') }}"
                                   placeholder="C&oacute;d." autocomplete="off"
                                   {{ $bloqueado ? 'readonly' : '' }}>
                            <input type="text" id="cp_importacion_gasto_tipo_nombre" class="form-control" readonly
                                   value="{{ old('importacion_gasto_tipo_nombre', $prefImp['importacion_gasto_tipo_nombre'] ?? '') }}"
                                   placeholder="Tipo de gasto">
                        </div>
                        <small class="form-text text-muted">Obligatorio si eligi&oacute; carpeta. Define inventariable vs resultado.</small>
                    </div>
                </div>
            @endif

            @if ($hayVinculos)
                <div class="table-responsive {{ $puedeAsociar ? 'mt-2' : '' }}">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Carpeta</th>
                                <th>Estado</th>
                                <th>Tipo de gasto</th>
                                <th>Naturaleza</th>
                                <th class="text-right">Importe</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vinculosImp as $v)
                                <tr>
                                    <td>
                                        @if (! empty($v['url']))
                                            <a href="{{ $v['url'] }}" class="text-primary font-weight-bold" target="_blank" rel="noopener">
                                                {{ $v['codigo'] !== '' ? $v['codigo'] : ('#'.$v['importacion_id']) }}
                                            </a>
                                        @else
                                            {{ $v['codigo'] !== '' ? $v['codigo'] : ('#'.$v['importacion_id']) }}
                                        @endif
                                    </td>
                                    <td>{{ $v['estado_etiqueta'] }}</td>
                                    <td>
                                        @if (($v['tipo_codigo'] ?? '') !== '')
                                            <span class="text-muted">{{ $v['tipo_codigo'] }}</span>
                                            {{ $v['tipo_nombre'] }}
                                        @else
                                            {{ $v['tipo_nombre'] ?: '—' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if (($v['naturaleza'] ?? '') === 'real')
                                            <span class="badge badge-success">Real</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst((string) ($v['naturaleza'] ?? 'estimado')) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float) ($v['importe'] ?? 0), 2, ',', '.') }}</td>
                                    <td>{{ $v['fecha'] ?? '—' }}</td>
                                    <td class="text-nowrap">
                                        @if (! empty($v['url']))
                                            <a href="{{ $v['url'] }}" class="btn-accion-tabla" target="_blank" rel="noopener" title="Abrir carpeta">
                                                <i class="fa fa-external-link text-primary"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($puedeAsociar)
                <p class="small text-muted mb-0">Sin v&iacute;nculo. Si aplica, elija carpeta + tipo de gasto y guarde.</p>
            @endif
        </div>
    </div>
</div>
<script>
(function () {
    var body = document.getElementById(@json($impCollapseId));
    var chevron = document.querySelector('#cp-panel-importacion .js-cp-imp-chevron');
    if (!body || !chevron || typeof jQuery === 'undefined') return;
    jQuery(body).on('show.bs.collapse', function () {
        chevron.classList.remove('fa-chevron-right');
        chevron.classList.add('fa-chevron-down');
    }).on('hide.bs.collapse', function () {
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-right');
    });
})();
</script>
@endif
