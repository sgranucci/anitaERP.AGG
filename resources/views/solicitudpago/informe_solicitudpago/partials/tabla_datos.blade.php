@php
    $muestraCuota = ! empty($muestra_cuota);
    $incluirConcil = ! empty($incluir_conciliacion);
    $puedeVerSp = ! empty($puede_ver_sp);
    $puedeVerProveedor = ! empty($puede_ver_proveedor);
    $paraExport = ! empty($para_export);
    $colspanBase = 15 + ($muestraCuota ? 2 : 0) + ($incluirConcil ? 6 : 0);
@endphp
<table class="table table-sm table-bordered table-hover mb-0" id="{{ $paraExport ? 'tabla-export' : 'tabla-paginada' }}">
    <thead>
        <tr>
            <th>N&uacute;mero</th>
            <th>Fecha</th>
            <th>Vence</th>
            <th>Tratamiento</th>
            <th>Sector</th>
            <th>Concepto</th>
            <th>Forma de pago</th>
            <th class="num">N.Pro.</th>
            <th>Proveedor</th>
            <th>Mon</th>
            <th class="num">Importe</th>
            @if ($muestraCuota)
                <th class="num">Monto cuota</th>
                <th class="num">Cuota paga</th>
            @endif
            <th>Estado</th>
            <th>Refer.</th>
            <th>Observaci&oacute;n</th>
            <th>Empresa</th>
            @if ($incluirConcil)
                <th class="num">SP Debe</th>
                <th class="num">SP Haber</th>
                <th class="num">Mayor Debe</th>
                <th class="num">Mayor Haber</th>
                <th class="num">Diff</th>
                <th>Concil.</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php
                $fechaFmt = $fila->fecha ? \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') : '';
                $venceFmt = $fila->fecha_vencimiento ? \Carbon\Carbon::parse($fila->fecha_vencimiento)->format('d/m/Y') : '';
                $esMadre = ! empty($fila->es_madre_plan);
                $esHija = ! empty($fila->es_hija);
                $esSuspendida = ($fila->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::SUSPENDIDA;
                $claseFila = $esSuspendida
                    ? 'table-secondary'
                    : ($esMadre ? 'table-info' : ($esHija ? 'table-light' : ''));
            @endphp
            <tr class="{{ $claseFila }}">
                <td>
                    @if ($puedeVerSp && ! empty($fila->id))
                        <a class="text-primary font-weight-bold" target="_blank" rel="noopener"
                           href="{{ route('editar_solicitudpago', $fila->id) }}?origen=modal_consulta&vista=consulta">
                            {{ $fila->codigo }}
                        </a>
                    @else
                        {{ $fila->codigo }}
                    @endif
                    @if ($esMadre)
                        <span class="badge badge-primary ml-1">Madre</span>
                        @if (($fila->cuotas_total ?? 0) > 0)
                            <span class="badge badge-secondary ml-1">{{ $fila->cuotas_pagadas }}/{{ $fila->cuotas_total }}</span>
                        @endif
                    @elseif ($esHija)
                        <span class="badge badge-light border ml-1">Hija</span>
                    @endif
                </td>
                <td>{{ $fechaFmt }}</td>
                <td>{{ $venceFmt }}</td>
                <td>{{ $fila->tratamiento_label }}</td>
                <td>{{ trim(($fila->sector_codigo ? $fila->sector_codigo.' ' : '').($fila->sector_nombre ?? '')) }}</td>
                <td>{{ $fila->concepto_nombre }}</td>
                <td>{{ $fila->forma_pago_nombre }}</td>
                <td class="num">
                    @if ($puedeVerProveedor && ! empty($fila->proveedor_id))
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ route('editar_proveedor', $fila->proveedor_id) }}?origen=modal_consulta&vista=consulta">
                            {{ $fila->proveedor_codigo }}
                        </a>
                    @else
                        {{ $fila->proveedor_codigo }}
                    @endif
                </td>
                <td>{{ $fila->proveedor_nombre }}</td>
                <td>{{ $fila->moneda }}</td>
                <td class="num">{{ number_format((float) $fila->monto, 2, ',', '.') }}</td>
                @if ($muestraCuota)
                    <td class="num">{{ number_format((float) ($fila->monto_cuota ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ (int) ($fila->cuota_paga ?? 0) ?: '' }}</td>
                @endif
                <td>
                    @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $fila->estado ?? ''])
                </td>
                <td>
                    @if (! empty($fila->madre_id) && $puedeVerSp)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ route('editar_solicitudpago', $fila->madre_id) }}?origen=modal_consulta&vista=consulta"
                           title="Abrir SP madre">
                            {{ $fila->referencia }}
                        </a>
                    @else
                        {{ $fila->referencia }}
                    @endif
                </td>
                <td>{{ \Illuminate\Support\Str::limit((string) ($fila->observacion ?? ''), 40) }}</td>
                <td>{{ $fila->nombreempresa }}</td>
                @if ($incluirConcil)
                    <td class="num">{{ $fila->concil_sp_debe !== null ? number_format((float) $fila->concil_sp_debe, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $fila->concil_sp_haber !== null ? number_format((float) $fila->concil_sp_haber, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $fila->concil_mayor_debe !== null ? number_format((float) $fila->concil_mayor_debe, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $fila->concil_mayor_haber !== null ? number_format((float) $fila->concil_mayor_haber, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $fila->concil_diff !== null ? number_format((float) $fila->concil_diff, 2, ',', '.') : '' }}</td>
                    <td>
                        @if (($fila->concil_estado ?? '') === 'OK')
                            <span class="badge badge-success">OK</span>
                        @elseif (($fila->concil_estado ?? '') === 'DIF')
                            <span class="badge badge-danger">DIF</span>
                        @elseif (($fila->concil_estado ?? '') === 'N/A')
                            <span class="badge badge-secondary" title="{{ $fila->concil_detalle ?? '' }}">N/A</span>
                        @endif
                    </td>
                @endif
            </tr>
            @if ($muestraCuota && ! empty($fila->cuotas_detalle))
                <tr class="sp-cuotas-detalle">
                    <td colspan="{{ $colspanBase }}" class="bg-white p-2">
                        <div class="pl-3 border-left border-primary">
                            <div class="small text-muted mb-1">
                                <i class="fa fa-calendar-check"></i>
                                Cuotas del plan #{{ $fila->codigo }}
                                ({{ $fila->cuotas_pagadas }}/{{ $fila->cuotas_total }} generadas)
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="max-width: 920px;">
                                    <thead style="background:#d6eaf8;color:#17202A;">
                                        <tr>
                                            <th class="num" style="width:8%;">Cuota</th>
                                            <th style="width:14%;">Vence</th>
                                            <th class="num" style="width:18%;">Monto</th>
                                            <th style="width:14%;">SP hija</th>
                                            <th style="width:16%;">Estado</th>
                                            <th>V&iacute;nculo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($fila->cuotas_detalle as $cuota)
                                            <tr>
                                                <td class="num">{{ $cuota->nro_cuota }}</td>
                                                <td>{{ $cuota->fecha_vencimiento ? \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') : '' }}</td>
                                                <td class="num">{{ number_format((float) $cuota->monto, 2, ',', '.') }}</td>
                                                <td>
                                                    @if (! empty($cuota->hija_id) && $puedeVerSp)
                                                        <a class="text-primary font-weight-bold" target="_blank" rel="noopener"
                                                           href="{{ route('editar_solicitudpago', $cuota->hija_id) }}?origen=modal_consulta&vista=consulta">
                                                            #{{ $cuota->hija_codigo }}
                                                        </a>
                                                    @elseif (! empty($cuota->hija_codigo))
                                                        #{{ $cuota->hija_codigo }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (! empty($cuota->hija_estado))
                                                        @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $cuota->hija_estado])
                                                    @else
                                                        <span class="badge badge-light border">Pendiente</span>
                                                    @endif
                                                </td>
                                                <td class="small text-muted">
                                                    {{ ! empty($cuota->pagada) ? 'Cuota generada / vinculada' : 'Sin SP hija aún' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="{{ $colspanBase }}" class="text-center text-muted">
                    Sin registros
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
