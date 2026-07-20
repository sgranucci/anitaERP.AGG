@extends("theme.$theme.layout")
@section('titulo')
    F572 SiRADIG
@endsection

<?php use App\Support\Sueldos\SiradigTablas; ?>

@section('contenido')
@php
    $deducciones = $p->conceptos->where('grupo', SiradigTablas::GRUPO_DEDUCCION);
    $retenciones = $p->conceptos->where('grupo', SiradigTablas::GRUPO_RETENCION);
    $ajustes = $p->conceptos->where('grupo', SiradigTablas::GRUPO_AJUSTE);
    $labelsImp = SiradigTablas::INGRESO_APORTE_LABELS;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    F572 — {{ trim(($p->empleado_apellido ?? '').' '.($p->empleado_nombre ?? '')) }}
                    <span class="badge badge-{{ $p->seccion === 'A' ? 'primary' : 'secondary' }}">Sección {{ $p->seccion }}</span>
                    <span class="badge badge-info">Período {{ $p->periodo }}</span>
                    <span class="badge badge-light">Presentación N° {{ $p->nro_presentacion }}</span>
                    @if ($p->vigente)
                        <span class="badge badge-success">Vigente</span>
                    @else
                        <span class="badge badge-secondary">Reemplazada</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_siradig_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                    @if ($puedeBorrar)
                        <form action="{{ route('eliminar_siradig_sueldos', ['id' => $p->id]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Eliminar esta presentación F572?');">
                            @csrf @method('delete')
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-trash"></i> Eliminar</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                {{-- Cabecera / datos del empleado --}}
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr><th style="width:40%;background:#f5f9fc;">CUIL / CUIT</th><td>{{ $p->empleado_cuit }} ({{ SiradigTablas::tipoDocumento($p->empleado_tipo_doc) }})</td></tr>
                                <tr><th style="background:#f5f9fc;">Empleado</th><td>{{ trim(($p->empleado_apellido ?? '').' '.($p->empleado_nombre ?? '')) }}</td></tr>
                                <tr><th style="background:#f5f9fc;">Legajo ERP</th><td>{{ $p->empleado_id ? ('#'.optional($p->empleado)->legajo.' — '.optional($p->empleado)->nombre) : 'Sin vincular (no hay legajo con este CUIL en la empresa)' }}</td></tr>
                                <tr><th style="background:#f5f9fc;">Empresa</th><td>{{ optional($p->empresa)->nombre }}</td></tr>
                                <tr><th style="background:#f5f9fc;">Fecha presentación</th><td>{{ optional($p->fecha_presentacion)->format('d/m/Y') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr><th style="width:40%;background:#f5f9fc;">Domicilio</th><td>{{ trim(($p->dom_calle ?? '').' '.($p->dom_nro ?? '')) }} {{ $p->dom_piso ? ('Piso '.$p->dom_piso) : '' }} {{ $p->dom_dpto ? ('Dpto '.$p->dom_dpto) : '' }}</td></tr>
                                <tr><th style="background:#f5f9fc;">Localidad</th><td>{{ $p->dom_localidad }} (CP {{ $p->dom_cp }})</td></tr>
                                <tr><th style="background:#f5f9fc;">Provincia</th><td>{{ SiradigTablas::provincia($p->dom_provincia) }}</td></tr>
                                @if ($p->seccion === 'B')
                                    <tr><th style="background:#f5f9fc;">Agente de retención</th><td>{{ $p->agente_retencion_cuit }} — {{ $p->agente_retencion_denominacion }}</td></tr>
                                @endif
                                <tr><th style="background:#f5f9fc;">Importado</th><td>{{ optional($p->importado_at)->format('d/m/Y H:i') }} · {{ $p->archivo_nombre }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($p->seccion === 'B')
                    <div class="alert alert-secondary">Sección B (pluriempleo): informa que el agente de retención designado es <strong>{{ $p->agente_retencion_denominacion }}</strong>. No incluye deducciones.</div>
                @endif

                {{-- Cargas de familia --}}
                @if ($p->cargasFamilia->isNotEmpty())
                    <h5 class="text-primary"><i class="fa fa-users"></i> Cargas de familia</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered table-hover">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr><th>Documento</th><th>Apellido y nombre</th><th>Nacimiento</th><th>Parentesco</th><th>Meses</th><th class="text-right">% Ded.</th><th>Fecha límite</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($p->cargasFamilia as $cf)
                                    <tr>
                                        <td>{{ SiradigTablas::tipoDocumento($cf->tipo_doc) }} {{ $cf->nro_doc }}</td>
                                        <td>{{ trim(($cf->apellido ?? '').' '.($cf->nombre ?? '')) }}</td>
                                        <td>{{ optional($cf->fecha_nac)->format('d/m/Y') }}</td>
                                        <td>{{ SiradigTablas::parentesco($cf->parentesco) }}</td>
                                        <td>{{ $cf->mes_desde }} a {{ $cf->mes_hasta }}</td>
                                        <td class="text-right">{{ $cf->porcentaje_deduccion ? $cf->porcentaje_deduccion.'%' : '—' }}</td>
                                        <td>{{ optional($cf->fecha_limite)->format('d/m/Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Deducciones --}}
                @if ($deducciones->isNotEmpty())
                    <h5 class="text-primary"><i class="fa fa-percent"></i> Deducciones</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr><th style="width:22%;">Tipo</th><th>Detalle</th><th class="text-right" style="width:14%;">Monto total</th><th style="width:30%;">Períodos / detalles</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($deducciones as $c)
                                    <tr>
                                        <td>{{ $c->tipo }} - {{ SiradigTablas::deduccion($c->tipo) }}</td>
                                        <td>{{ $c->desc_basica }}@if($c->desc_adicional)<br><small class="text-muted">{{ $c->desc_adicional }}</small>@endif</td>
                                        <td class="text-right font-weight-bold">{{ $fmt($c->monto_total) }}</td>
                                        <td class="small">
                                            @foreach ($c->periodos as $per)
                                                <div>Meses {{ $per->mes_desde }}-{{ $per->mes_hasta }}: {{ $fmt($per->monto_mensual) }}</div>
                                            @endforeach
                                            @foreach ($c->detalles as $det)
                                                <div><span class="text-muted">{{ $det->nombre }}:</span> {{ $det->valor }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                                <tr style="background:#f5f9fc;"><td colspan="2" class="text-right font-weight-bold">Total deducciones</td><td class="text-right font-weight-bold">{{ $fmt($deducciones->sum('monto_total')) }}</td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Retenciones / percepciones / pagos a cuenta --}}
                @if ($retenciones->isNotEmpty())
                    <h5 class="text-primary"><i class="fa fa-hand-holding-usd"></i> Retenciones, percepciones y pagos a cuenta</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr><th style="width:26%;">Tipo</th><th>Detalle</th><th class="text-right" style="width:14%;">Monto total</th><th style="width:28%;">Períodos / detalles</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($retenciones as $c)
                                    <tr>
                                        <td>{{ $c->tipo }} - {{ SiradigTablas::retencion($c->tipo) }}</td>
                                        <td>{{ $c->desc_basica }}@if($c->desc_adicional)<br><small class="text-muted">{{ $c->desc_adicional }}</small>@endif</td>
                                        <td class="text-right font-weight-bold">{{ $fmt($c->monto_total) }}</td>
                                        <td class="small">
                                            @foreach ($c->periodos as $per)
                                                <div>Meses {{ $per->mes_desde }}-{{ $per->mes_hasta }}: {{ $fmt($per->monto_mensual) }}</div>
                                            @endforeach
                                            @foreach ($c->detalles as $det)
                                                <div><span class="text-muted">{{ $det->nombre }}:</span> {{ $det->valor }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                                <tr style="background:#f5f9fc;"><td colspan="2" class="text-right font-weight-bold">Total</td><td class="text-right font-weight-bold">{{ $fmt($retenciones->sum('monto_total')) }}</td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Ajustes --}}
                @if ($ajustes->isNotEmpty())
                    <h5 class="text-primary"><i class="fa fa-sliders-h"></i> Ajustes</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr><th style="width:26%;">Tipo</th><th>Detalle</th><th class="text-right" style="width:14%;">Monto total</th><th style="width:28%;">Detalles</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($ajustes as $c)
                                    <tr>
                                        <td>{{ $c->tipo }} - {{ SiradigTablas::ajuste($c->tipo) }}</td>
                                        <td>{{ $c->desc_basica }}@if($c->denominacion)<br><small class="text-muted">{{ $c->cuit }} {{ $c->denominacion }}</small>@endif</td>
                                        <td class="text-right font-weight-bold">{{ $fmt($c->monto_total) }}</td>
                                        <td class="small">
                                            @foreach ($c->detalles as $det)
                                                <div><span class="text-muted">{{ $det->nombre }}:</span> {{ $det->valor }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Otros empleadores (pluriempleo) --}}
                @if ($p->otrosEmpleadores->isNotEmpty())
                    <h5 class="text-primary"><i class="fa fa-building"></i> Ganancias liquidadas por otros empleadores</h5>
                    @foreach ($p->otrosEmpleadores as $oe)
                        <div class="card card-outline card-secondary mb-3">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">{{ $oe->cuit }} — {{ $oe->denominacion }}
                                    @if ($oe->convenio_colectivo)<span class="badge badge-light">CCT {{ $oe->convenio_colectivo }}</span>@endif
                                </h6>
                            </div>
                            <div class="card-body table-responsive p-0">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background-color:#85C1E9;color:#17202A;">
                                        <tr><th>Mes</th><th>Régimen</th><th>Concepto</th><th class="text-right">Importe</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($oe->meses as $mes)
                                            @php
                                                $filas = [];
                                                foreach ($labelsImp as $col => $lbl) {
                                                    if ($mes->{$col} !== null) { $filas[$lbl] = $mes->{$col}; }
                                                }
                                            @endphp
                                            @forelse ($filas as $lbl => $val)
                                                <tr>
                                                    @if ($loop->first)
                                                        <td rowspan="{{ count($filas) }}" class="align-middle font-weight-bold">{{ $mes->mes }}</td>
                                                        <td rowspan="{{ count($filas) }}" class="align-middle">{{ SiradigTablas::regimen($mes->regimen) }}</td>
                                                    @endif
                                                    <td>{{ $lbl }}</td>
                                                    <td class="text-right">{{ $fmt($val) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td>{{ $mes->mes }}</td><td>{{ SiradigTablas::regimen($mes->regimen) }}</td><td colspan="2" class="text-muted">Sin importes</td></tr>
                                            @endforelse
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Datos adicionales --}}
                @if ($p->datosAdicionales->isNotEmpty())
                    <h5 class="text-primary"><i class="fa fa-info-circle"></i> Datos adicionales</h5>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr><th>Dato</th><th>Meses</th><th>Valor</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($p->datosAdicionales as $da)
                                    <tr>
                                        <td>{{ SiradigTablas::datoAdicional($da->nombre) }}</td>
                                        <td>{{ $da->mes_desde ? ($da->mes_desde.' a '.$da->mes_hasta) : 'Todo el período' }}</td>
                                        <td>{{ $da->valor }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
