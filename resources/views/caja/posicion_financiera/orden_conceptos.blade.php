@extends("theme.$theme.layout")
@section('titulo')
    Orden de conceptos — Posición financiera
@endsection

@section('styles')
@include('includes.tabs-activas-estilos')
<style>
    .posfin-orden-preview {
        background: #F8FBFD;
        border: 1px solid #D6EAF8;
        border-radius: 4px;
        padding: 10px 12px;
        font-size: 13px;
    }
    .posfin-orden-preview ol {
        margin-bottom: 0;
        padding-left: 1.3rem;
    }
    .posfin-orden-tabla thead th {
        background: #85C1E9;
        color: #17202A;
    }
    .posfin-orden-multiempresa {
        font-size: 11px;
        color: #1B4F72;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/posicion_financiera/orden_conceptos.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Orden de conceptos</h3>
                <div class="card-tools">
                    <a href="{{ route('posicion_financiera') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a posición financiera
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Las cuentas son distintas en cada empresa. El orden se guarda por uso
                    (gastronomía/vending, estacionamiento, m&aacute;quinas), con el mismo n&uacute;mero
                    para el medio equivalente. La plantilla inicial es la de Biyemas.
                </p>

                <form method="post" action="{{ route('posicion_financiera_guardar_orden_conceptos') }}" id="form-posfin-orden" class="mb-0">
                    @csrf
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-posfin-orden" role="tablist">
                            @foreach ($bloques as $idx => $bloque)
                                <li class="nav-item">
                                    <a class="nav-link {{ $idx === 0 ? 'active' : '' }}"
                                       data-toggle="tab"
                                       href="#tab-posfin-{{ $idx }}"
                                       role="tab">
                                        {{ $bloque['label'] }}
                                        @if (! empty($bloque['alineado']))
                                            <span class="badge badge-success">alineado</span>
                                        @else
                                            <span class="badge badge-warning">revisar</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="tab-content pt-3">
                        @foreach ($bloques as $idx => $bloque)
                            <div class="tab-pane fade {{ $idx === 0 ? 'show active' : '' }}"
                                 id="tab-posfin-{{ $idx }}"
                                 role="tabpanel"
                                 data-uso="{{ $bloque['uso'] }}">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered table-hover posfin-orden-tabla mb-3">
                                                <thead>
                                                    <tr>
                                                        <th style="width:90px;">Orden</th>
                                                        <th>Concepto</th>
                                                        @foreach ($empresas as $empresa)
                                                            <th>{{ $empresa->nombre }}</th>
                                                        @endforeach
                                                        <th class="text-center" style="width:90px;">Mover</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="posfin-orden-tbody">
                                                    @foreach ($bloque['filas'] as $filaIdx => $fila)
                                                        @php
                                                            $nameBase = 'filas['.$bloque['uso'].'-'.$filaIdx.']';
                                                        @endphp
                                                        <tr class="posfin-orden-fila">
                                                            <td>
                                                                <input type="hidden" name="{{ $nameBase }}[uso]" value="{{ $bloque['uso'] }}">
                                                                @foreach ($fila['ids'] as $cuentaId)
                                                                    <input type="hidden" name="{{ $nameBase }}[ids][]" value="{{ $cuentaId }}">
                                                                @endforeach
                                                                <input type="number"
                                                                       name="{{ $nameBase }}[orden]"
                                                                       class="form-control form-control-sm posfin-orden-input"
                                                                       value="{{ old($nameBase.'.orden', $fila['orden']) }}"
                                                                       min="0" max="9999" step="10">
                                                            </td>
                                                            <td>
                                                                <strong class="posfin-orden-concepto">{{ $fila['concepto'] }}</strong>
                                                            </td>
                                                            @foreach ($empresas as $empresa)
                                                                @php
                                                                    $cuentaEmp = $fila['cuentas_por_empresa'][(int) $empresa->id] ?? null;
                                                                @endphp
                                                                <td>
                                                                    @if ($cuentaEmp)
                                                                        <div>{{ $cuentaEmp['etiqueta'] }}</div>
                                                                        <small class="text-muted">{{ $cuentaEmp['codigo'] }}</small>
                                                                        @if (! empty($cuentaEmp['multiempresa']))
                                                                            <div class="posfin-orden-multiempresa">Todas las empresas</div>
                                                                        @endif
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                            <td class="text-center text-nowrap">
                                                                <button type="button" class="btn btn-accion-tabla posfin-orden-subir" title="Subir">
                                                                    <i class="fa fa-arrow-up"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-accion-tabla posfin-orden-bajar" title="Bajar">
                                                                    <i class="fa fa-arrow-down"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="posfin-orden-preview">
                                            <strong>Preview del bloque</strong>
                                            <p class="text-muted small mb-2">Así quedan los medios en la grilla (orden de Biyemas si aplicó esa plantilla).</p>
                                            <ol class="posfin-orden-preview-lista">
                                                @foreach ($bloque['filas'] as $fila)
                                                    <li>{{ $fila['concepto'] }}</li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Guardar orden
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <form method="post" action="{{ route('posicion_financiera_aplicar_orden_biyemas') }}" class="d-inline"
                      onsubmit="return confirm('¿Reemplazar el orden actual por el de Biyemas en gastronomía, estacionamiento y máquinas?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-magic"></i> Aplicar orden Biyemas
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
