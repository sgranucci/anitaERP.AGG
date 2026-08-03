@extends("theme.$theme.layout")
@section('titulo')
    Consulta Ganancias 4ta categor&iacute;a
@endsection

@section('contenido')
@php
    $mesesNom = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-percent"></i> Planilla de Ganancias (motor por f&oacute;rmulas)</h3>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('consultar_ganancias_sueldos') }}" class="form-inline flex-wrap mb-3">
                    <input type="hidden" name="consultar" value="1">
                    @include('includes.listado.filtro_empresa_asignada_inline', [
                        'empresa_query' => $empresa_query ?? collect(),
                        'empresa_id' => $empresaId,
                        'permite_todas' => true,
                        'opcion_todas' => '— Todas —',
                        'select_class' => 'form-control-sm mr-3',
                    ])
                    <label class="mr-2">Legajo</label>
                    <input type="number" name="legajo" class="form-control form-control-sm mr-3" value="{{ $legajo }}" required style="width:120px">
                    <label class="mr-2">A&ntilde;o</label>
                    <input type="number" name="anio" class="form-control form-control-sm mr-3" value="{{ $anio }}" min="2020" max="2100" style="width:90px">
                    <label class="mr-2">Hasta mes</label>
                    <select name="hasta_mes" class="form-control form-control-sm mr-3">
                        @for($m=1;$m<=12;$m++)
                            <option value="{{ $m }}" {{ (int)$hastaMes === $m ? 'selected' : '' }}>{{ $mesesNom[$m] }}</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-sm btn-info"><i class="fa fa-calculator"></i> Calcular</button>
                </form>

                <p class="text-muted small">
                    El plan de l&iacute;neas se define en <code>ganancia_linea_sueldos</code> (f&oacute;rmulas).
                    Las tablas Art.&nbsp;94 / Art.&nbsp;30 tienen vigencia mensual. Las entradas del legajo
                    se leen de <code>ganancia_movimiento_sueldos</code> (SIRADIG / carga).
                    En liquidaci&oacute;n use <code>ganancias()</code> o <code>ganancia_linea("RET_GANANCIAS")</code>.
                </p>

                @if ($legajo && ! $empleado)
                    <div class="alert alert-warning">No se encontr&oacute; el legajo {{ $legajo }}.</div>
                @endif

                @if ($empleado && $resultado)
                    <h5 class="mb-2">
                        {{ $empleado->legajo }} — {{ $empleado->nombre }}
                        &middot; Retenci&oacute;n mes {{ $mesesNom[$hastaMes] }}:
                        <strong>$ {{ number_format((float)$resultado['retencion_mes'], 2, ',', '.') }}</strong>
                    </h5>
                    @if (!empty($resultado['errores']))
                        <div class="alert alert-danger">
                            @foreach($resultado['errores'] as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Concepto</th>
                                    @for($m=1;$m<=$hastaMes;$m++)
                                        <th class="text-right">{{ $mesesNom[$m] }}</th>
                                    @endfor
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($resultado['lineas'] as $lin)
                                    @php
                                        $total = 0;
                                        for($m=1;$m<=$hastaMes;$m++) $total += (float)($resultado['matriz'][$m][$lin['codigo']] ?? 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            <code class="small">{{ $lin['codigo'] }}</code>
                                            {{ $lin['descripcion'] }}
                                        </td>
                                        @for($m=1;$m<=$hastaMes;$m++)
                                            @php $v = (float)($resultado['matriz'][$m][$lin['codigo']] ?? 0); @endphp
                                            <td class="text-right {{ $v < 0 ? 'text-danger' : '' }}">
                                                {{ number_format($v, 2, ',', '.') }}
                                            </td>
                                        @endfor
                                        <td class="text-right"><strong>{{ number_format($total, 2, ',', '.') }}</strong></td>
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
