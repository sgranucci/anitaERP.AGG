@extends("theme.$theme.layout")
@section('titulo')
    Importar ahorros
@endsection

@section('scripts')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, ',', '.');
    $esResultado = $resultado !== null;
    $detalleResultado = $esResultado ? collect($resultado['detalle'])->keyBy('fila') : collect();
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $esResultado ? 'Resultado de la importación' : 'Vista previa' }}@if ($nombreOriginal) &mdash; {{ $nombreOriginal }}@endif</h3>
                <div class="card-tools">
                    <a href="{{ route('importar_ahorro_compras') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> {{ $esResultado ? 'Importar otro archivo' : 'Volver' }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if ($esResultado)
                    <div class="alert {{ $cantError > 0 ? 'alert-warning' : 'alert-success' }} py-2">
                        Se guardaron <b>{{ $cantOk }}</b> fila(s).
                        @if ($cantError > 0) <b>{{ $cantError }}</b> fila(s) no se guardaron (ver la columna Mensaje). @endif
                    </div>
                @else
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="mr-3"><span class="badge badge-success">{{ $cantOk }}</span> v&aacute;lidas</span>
                        <span class="mr-3"><span class="badge badge-danger">{{ $cantError }}</span> con error (no se guardan)</span>
                        @if ($cantOk > 0)
                            <form method="POST" action="{{ route('importar_ahorro_compras_confirmar') }}" class="ml-auto"
                                  onsubmit="return confirm('Se guardarán {{ $cantOk }} fila(s). ¿Confirma?');">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fa fa-check"></i> Confirmar e importar {{ $cantOk }} fila(s) v&aacute;lida(s)
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="tabla-ancha-grilla" data-tabla-ancha>
                    <div class="tabla-ancha-scroll-top" hidden><div class="tabla-ancha-scroll-top-inner"></div></div>
                    <div class="tabla-ancha-wrap">
                        <table class="table table-sm table-bordered mb-0 tabla-ancha" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Fila</th>
                                    <th>Documento</th>
                                    <th>Empresa</th>
                                    <th>SKU</th>
                                    <th class="text-right">Precio ant.</th>
                                    <th class="text-right">Precio actual</th>
                                    <th class="text-right">Cant.</th>
                                    <th class="text-right">Ahorro $</th>
                                    <th class="text-right">%</th>
                                    <th>Estado</th>
                                    <th>Mensaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $f)
                                    @php
                                        $ok = $f['estado'] === 'ok';
                                        $res = $detalleResultado->get($f['fila']);
                                        $guardada = $esResultado ? ($res['ok'] ?? false) : $ok;
                                        $neg = $f['ahorro_pesos'] !== null && $f['ahorro_pesos'] < 0;
                                    @endphp
                                    <tr class="{{ $guardada ? '' : 'table-danger' }}">
                                        <td>{{ $f['fila'] }}</td>
                                        <td class="text-nowrap">{{ $f['doc_texto'] ?: ($f['entrada']['documento'].' '.$f['entrada']['numero']) }}</td>
                                        <td>{{ $f['empresa_texto'] }}</td>
                                        <td>{{ $f['entrada']['sku'] }}</td>
                                        <td class="text-right">{{ is_numeric($f['entrada']['precio_anterior']) ? $fmt($f['entrada']['precio_anterior'], 4) : $f['entrada']['precio_anterior'] }}</td>
                                        <td class="text-right">@if ($f['precio_actual'] !== null) {{ $f['moneda_actual'] }} {{ $fmt($f['precio_actual'], 4) }} @endif</td>
                                        <td class="text-right">@if ($f['cantidad'] !== null) {{ $fmt($f['cantidad'], 2) }} @endif</td>
                                        <td class="text-right {{ $neg ? 'text-danger' : 'text-success' }}">@if ($f['ahorro_pesos'] !== null) {{ $fmt($f['ahorro_pesos']) }} @endif</td>
                                        <td class="text-right {{ $neg ? 'text-danger' : 'text-success' }}">@if ($f['porc'] !== null) {{ $fmt($f['porc'], 1) }} @endif</td>
                                        <td>
                                            @if ($esResultado)
                                                <span class="badge {{ $guardada ? 'badge-success' : 'badge-danger' }}">{{ $guardada ? 'Guardada' : 'No guardada' }}</span>
                                            @else
                                                <span class="badge {{ $ok ? 'badge-success' : 'badge-danger' }}">{{ $ok ? 'OK' : 'Error' }}</span>
                                            @endif
                                        </td>
                                        <td class="small">
                                            @if ($esResultado && $res)
                                                {{ $res['mensaje'] }}
                                            @else
                                                {{ $f['mensaje'] }}
                                            @endif
                                            @if ($ok && ! $esResultado)
                                                @foreach ($f['avisos'] as $aviso)
                                                    <div class="text-muted">{{ $aviso }}</div>
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
