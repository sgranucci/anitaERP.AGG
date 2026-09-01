@extends("theme.$theme.layout")
@section('titulo')
    LSD {{ $p->periodoLabel() }} / {{ $p->nro_liquidacion_afip }}
@endsection

@section('contenido')
@php
    $validaciones = $p->validaciones_json ?? [];
    $errores = collect($validaciones)->where('nivel', 'error');
    $avisos = collect($validaciones)->where('nivel', 'warning');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Presentación LSD {{ $p->periodoLabel() }} · nro. AFIP {{ $p->nro_liquidacion_afip }}
                    ({{ $p->identificacion }})
                </h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual-lsd')
                    <a href="{{ route('consultar_lsd_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>Estado:</strong> {{ $p->estadoLabel() }}
                    · <strong>Empresa:</strong> {{ $p->nombreempresa }}
                    · <strong>Liquidación:</strong> {{ optional($p->liquidacion)->numero }} {{ optional($p->liquidacion)->descripcion }}
                    · <strong>Reg. 04:</strong> {{ $p->cantidad_registros_04 }}
                    · <strong>Archivo:</strong> {{ $p->archivo_nombre }}
                </p>
                <div class="mb-3">
                    <a href="{{ route('descargar_lsd_sueldos', $p->id) }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-download"></i> Descargar TXT
                    </a>
                    @if ($puedePresentar && $p->estado !== 'presentada')
                        <form action="{{ route('presentar_lsd_sueldos', $p->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Marcar presentada en ARCA</button>
                        </form>
                        <form action="{{ route('rechazar_lsd_sueldos', $p->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">Marcar rechazada</button>
                        </form>
                    @endif
                    @if ($puedeRectificar && $p->identificacion === 'SJ')
                        <form action="{{ route('rectificar_lsd_sueldos', $p->id) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Generar rectificativa RE (omite 02 y 03)?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">Generar rectificativa RE</button>
                        </form>
                    @endif
                    @if ($puedeBorrar)
                        <form action="{{ route('eliminar_lsd_sueldos', $p->id) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Eliminar esta presentación?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fa fa-times-circle"></i> Eliminar
                            </button>
                        </form>
                    @endif
                </div>

                @if ($errores->isNotEmpty() || $avisos->isNotEmpty())
                    <div class="alert {{ $errores->isNotEmpty() ? 'alert-danger' : 'alert-warning' }}">
                        <strong>Validaciones previas</strong>
                        <ul class="mb-0 pl-3">
                            @foreach ($validaciones as $v)
                                <li>{{ $v['nivel'] ?? '' }}: {{ $v['mensaje'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @foreach (['01','02','03','04','05','06'] as $tipo)
                    @php $regs = $porTipo->get($tipo, collect()); @endphp
                    @if ($regs->isEmpty())
                        @continue
                    @endif
                    <h5 class="mt-3">Registro {{ $tipo }} ({{ $regs->count() }})</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:60px;">Línea</th>
                                    <th style="width:110px;">CUIL</th>
                                    <th>Contenido</th>
                                    <th style="width:90px;">Estado</th>
                                    @if ($puedeGenerar && $p->estado !== 'presentada')
                                        <th style="width:90px;">Editar</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($regs as $r)
                                    <tr>
                                        <td>{{ $r->nro_linea }}</td>
                                        <td>{{ $r->cuil }}</td>
                                        <td><code style="font-size:11px;word-break:break-all;">{{ $r->lineaEfectiva() }}</code></td>
                                        <td>{{ $r->estado_linea }}@if ($r->mensaje) <small>{{ $r->mensaje }}</small>@endif</td>
                                        @if ($puedeGenerar && $p->estado !== 'presentada')
                                            <td>
                                                <form action="{{ route('override_lsd_sueldos', ['id' => $p->id, 'registroId' => $r->id]) }}" method="POST">
                                                    @csrf
                                                    <input type="text" name="contenido_override" class="form-control form-control-sm"
                                                           value="{{ $r->lineaEfectiva() }}">
                                                    <button type="submit" class="btn btn-xs btn-outline-secondary mt-1">Guardar</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
