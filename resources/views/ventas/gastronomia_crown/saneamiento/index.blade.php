@extends("theme.$theme.layout")
@section('titulo')
    Saneamiento cuentas Crown
@endsection

@section("scripts")
<script>
    window.SANEAMIENTO_CROWN = {
        csrf: @json(csrf_token()),
        empresaId: @json((int) $empresa_id),
        salaId: @json((int) $sala_id),
        puedeEjecutar: @json((bool) ($puede_ejecutar ?? false)),
        confirmacion: @json($diagnostico['confirmacion'] ?? null),
        urlCerrar: @json(route('gastronomia_crown_saneamiento_api_cerrar')),
        urlJornada: @json($url_jornada ?? ''),
        desdeJornada: @json((bool) ($desde_jornada ?? false)),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/saneamiento.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/saneamiento.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="saneamiento-crown-app">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-warning">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0">Saneamiento de cuentas abiertas (Crown)</h3>
                @if (! empty($desde_jornada) && ! empty($url_jornada))
                    <a href="{{ $url_jornada }}" class="btn btn-outline-light btn-sm ml-auto">
                        <i class="fa fa-reply"></i> Volver a jornada
                    </a>
                @endif
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Uso administrativo.</strong>
                    Lista las cuentas abiertas o en cobro de la sala y permite cerrarlas
                    <strong>sin facturar</strong> (con confirmación) para desbloquear el cierre de jornada.
                    No modifica comprobantes fiscales.
                    @if (! ($puede_ejecutar ?? false))
                        <br>Su usuario solo puede <strong>consultar</strong>.
                        Para ejecutar acciones solicite el permiso
                        <em>Ejecutar saneamiento cuentas gastronomía Crown</em>.
                    @endif
                </div>

                <form method="get" action="{{ route('gastronomia_crown_saneamiento') }}" class="form-inline mb-4" id="form-filtro-saneamiento-crown">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresas' => $empresas,
                        'empresa_id' => $empresa_id,
                        'select_class' => 'mr-3',
                        'required' => true,
                        'permite_todas' => false,
                    ])
                    <label class="mr-2" for="sala_id">Sala</label>
                    <select name="sala_id" id="sala_id" class="form-control mr-3" required>
                        <option value="">Seleccione…</option>
                        @foreach ($salas as $sala)
                            <option value="{{ $sala->id }}" @selected((int) $sala_id === (int) $sala->id)>
                                {{ $sala->codigo }} — {{ $sala->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @if (! empty($desde_jornada))
                        <input type="hidden" name="desde_jornada" value="1">
                    @endif
                    <button type="submit" class="btn btn-secondary btn-sm">Consultar</button>
                </form>

                @if (! empty($falta_elegir_sala))
                    <div class="alert alert-info mb-0">Elija la sala para ver las cuentas abiertas.</div>
                @else
                    @php
                        $total = (int) ($diagnostico['total'] ?? 0);
                        $conItems = (int) ($diagnostico['con_items'] ?? 0);
                        $vacias = (int) ($diagnostico['vacias'] ?? 0);
                        $confirmacion = $diagnostico['confirmacion'] ?? null;
                        $cuentas = $diagnostico['cuentas'] ?? [];
                    @endphp

                    <div class="mb-3">
                        <span class="badge badge-danger">{{ $total }} abierta(s)</span>
                        @if ($conItems > 0)
                            <span class="badge badge-warning">{{ $conItems }} con consumos</span>
                        @endif
                        @if ($vacias > 0)
                            <span class="badge badge-info">{{ $vacias }} vacía(s)</span>
                        @endif
                    </div>

                    @if ($total === 0)
                        <div class="alert alert-success">
                            No hay cuentas abiertas ni en cobro en esta sala.
                            @if (! empty($url_jornada))
                                Puede
                                <a href="{{ $url_jornada }}" class="alert-link">volver a cerrar la jornada</a>.
                            @endif
                        </div>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0" id="tabla-saneamiento-crown">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>ID</th>
                                        <th>Mesa</th>
                                        <th>Mozo</th>
                                        <th>PC</th>
                                        <th>Abierta</th>
                                        <th class="text-right">Ítems</th>
                                        <th class="text-right">Total</th>
                                        <th>Estado</th>
                                        @if ($puede_ejecutar ?? false)
                                            <th></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cuentas as $c)
                                        <tr data-cuenta-id="{{ $c['id'] }}">
                                            <td>{{ $c['id'] }}</td>
                                            <td>{{ $c['mesa'] }}</td>
                                            <td>{{ $c['mozo'] }}</td>
                                            <td>{{ $c['identificador_pc'] !== '' ? $c['identificador_pc'] : '—' }}</td>
                                            <td>{{ $c['abierta_en'] }}</td>
                                            <td class="text-right">{{ $c['lineas'] }}</td>
                                            <td class="text-right">{{ number_format((float) $c['total'], 2, ',', '.') }}</td>
                                            <td>
                                                @if (! empty($c['con_items']))
                                                    <span class="badge badge-warning">con consumos</span>
                                                @else
                                                    <span class="badge badge-info">vacía</span>
                                                @endif
                                                <span class="badge badge-secondary">{{ $c['estado'] }}</span>
                                            </td>
                                            @if ($puede_ejecutar ?? false)
                                                <td class="text-nowrap">
                                                    <button type="button"
                                                            class="btn btn-outline-danger btn-sm js-cerrar-una"
                                                            data-cuenta-id="{{ $c['id'] }}">
                                                        Cerrar
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($puede_ejecutar ?? false)
                            <div class="border rounded p-3" style="max-width:36rem;background:#fff;">
                                <h4 class="h6 mb-2">Cerrar todas sin facturar</h4>
                                <p class="small text-muted mb-2">
                                    Escriba exactamente
                                    <code id="saneamiento-confirmacion-txt">{{ $confirmacion }}</code>
                                    para confirmar el cierre de las {{ $total }} cuenta(s).
                                </p>
                                <div class="form-row align-items-end">
                                    <div class="col-md-7 mb-2">
                                        <input type="text" class="form-control form-control-sm"
                                               id="saneamiento-confirmacion"
                                               autocomplete="off"
                                               placeholder="{{ $confirmacion }}">
                                    </div>
                                    <div class="col-md-5 mb-2">
                                        <button type="button" class="btn btn-danger btn-sm btn-block" id="btn-cerrar-todas">
                                            Cerrar todas
                                        </button>
                                    </div>
                                </div>
                                <div id="saneamiento-mensaje" class="small mt-2 d-none" role="status"></div>
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
