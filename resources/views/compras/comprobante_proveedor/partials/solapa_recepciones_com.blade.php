@php
    use App\Support\Compras\ComprobanteProveedorEstados;
    use App\Support\Compras\ComprobanteProveedorModoCarga;
    use App\Support\Compras\ComprobanteProveedorCotizacionSupport;

    $seleccionados = old('recepcion_proveedor_ids', $recepciones_seleccionadas ?? []);
    $seleccionadosInt = array_map('intval', (array) $seleccionados);
    $bloqueado = ($esEdicion ?? false) && ($data->estado ?? '') === ComprobanteProveedorEstados::CONTABILIZADO;
    $comObligatoria = (bool) ($com_obligatoria ?? false);
    $modoRecepcion = old('modo_carga', $data->modo_carga ?? '') === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION
        || $comObligatoria;
    $hayRecepciones = ($recepciones_disponibles ?? collect())->isNotEmpty() || count($seleccionadosInt) > 0;
    $comResolucion = $com_resolucion ?? null;
    $toleranciaPct = (float) ($com_tolerancia_pct ?? 0);
    $importeRef = (float) ($comResolucion['importe_comparacion'] ?? 0);
    $cotizacionFactura = (float) old('cotizacion', $data->cotizacion ?? 1);
    $monedaId = (int) old('moneda_id', $data->moneda_id ?? 1);
    $esMe = ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaId);
    $importeRefMn = \App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport::aMonedaLocal(
        $importeRef,
        $monedaId,
        $cotizacionFactura
    );
    $puedeVerRecepcion = can('editar-recepcion-proveedor', false) || can('listar-recepcion-proveedor', false);
    $mostrarIds = collect($seleccionadosInt)
        ->merge(($recepciones_disponibles ?? collect())->pluck('id'))
        ->unique()
        ->values();
@endphp

@if ($modoRecepcion && $hayRecepciones)
<div id="cp-bloque-recepciones-com" class="mt-2"
     data-tolerancia-pct="{{ $toleranciaPct }}"
     data-importe-ref="{{ $importeRefMn }}"
     data-cotizacion-factura="{{ $cotizacionFactura }}"
     data-es-me="{{ $esMe ? '1' : '0' }}">
    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2">
            <h4 class="card-title mb-0">
                <i class="fa fa-truck"></i> Recepciones COM (referencia)
                @if ($comObligatoria)
                    <span class="badge badge-danger ml-1">Obligatoria</span>
                @endif
            </h4>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Seleccione la(s) recepción(es) COM a facturar.
                @if ($comObligatoria)
                    <strong>Obligatorio</strong> porque el legajo tiene COM disponible (paso de Compras a Cuentas a Pagar).
                @endif
                Al guardar se controla cotización (ME) y tolerancia de importe vs provisión COM
                ({{ number_format($toleranciaPct, 2, ',', '.') }}% según centro de costo de la OC; además ±$0,05).
                Al contabilizar: el asiento de la recepción no se modifica; la factura debita la provisión (neto COM),
                impuestos y —si el neto supera la COM— la diferencia prorrateada en cuentas de artículos; el haber va a proveedores.
            </p>

            @if (! empty($comResolucion['importe_comparacion']))
            <p class="small mb-2">
                Importe de referencia del comprobante
                ({{ $comResolucion['importe_comparacion_etiqueta'] ?? 'comparación' }})
                @if (abs($importeRefMn - $importeRef) > 0.05)
                    en moneda local:
                    <strong>${{ number_format($importeRefMn, 2, ',', '.') }}</strong>
                    <span class="text-muted">(origen {{ number_format($importeRef, 2, ',', '.') }})</span>
                @else
                    :
                    <strong>${{ number_format($importeRefMn, 2, ',', '.') }}</strong>
                @endif
            </p>
            @endif

            @if (! empty($comResolucion['ambigua']) && ! empty($comResolucion['mensaje']))
            <div class="alert alert-warning py-2">
                <i class="fa fa-exclamation-triangle"></i> {{ $comResolucion['mensaje'] }}
            </div>
            @endif

            <div id="cp-com-resumen-diferencia" class="alert alert-secondary py-2 small mb-3" style="display:none;"></div>

            @if ($bloqueado)
                <ul class="list-unstyled mb-0">
                    @foreach ($data->comprobante_proveedor_recepciones ?? [] as $vinculo)
                        <li>
                            <small>Recepción #{{ $vinculo->recepcion_proveedor_id }}</small>
                            @if ($puedeVerRecepcion)
                                <a href="{{ route('editar_recepcion_proveedor', ['id' => $vinculo->recepcion_proveedor_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                   class="btn btn-outline-primary btn-xs ml-2" target="_blank" rel="noopener">Ver</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="table-responsive">
                <table class="table table-sm table-bordered mb-3" id="cp-tabla-recepciones-com">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width40 text-center"></th>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Número</th>
                            <th>OC</th>
                            <th>Moneda</th>
                            <th class="text-right">Neto COM</th>
                            <th class="text-right">Cotización</th>
                            <th>Asiento provisión</th>
                            <th class="text-center" style="width:70px;">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mostrarIds as $recepcionId)
                            @php
                                $recepcion = ($recepciones_disponibles ?? collect())->firstWhere('id', $recepcionId);
                                if (! $recepcion) {
                                    $vinculo = optional($data->comprobante_proveedor_recepciones ?? collect())
                                        ->firstWhere('recepcion_proveedor_id', $recepcionId);
                                    $recepcion = $vinculo?->recepcion_proveedores;
                                }
                                $importeComMe = (float) ($recepcion->importe_provision_com ?? 0);
                                $importeComMn = (float) ($recepcion->importe_provision_com_mn ?? $importeComMe);
                                $monedaComId = (int) ($recepcion->moneda_id ?? 1);
                                $monedaComNombre = $recepcion->monedas->nombre
                                    ?? ($monedaComId > 1 ? 'ME#'.$monedaComId : 'PESOS');
                                $esMeCom = ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaComId);
                                $coincide = $importeRefMn > 0 && abs($importeComMn - $importeRefMn) <= 0.05;
                                $cotCom = (float) ($recepcion->cotizacion ?? 1);
                                $cotDistinta = $esMe && abs($cotCom - $cotizacionFactura) > 0.0000005;
                            @endphp
                            @if ($recepcion)
                            <tr class="cp-com-fila {{ $coincide ? 'table-success' : '' }}"
                                data-recepcion-id="{{ $recepcion->id }}"
                                data-importe-com="{{ $importeComMn }}"
                                data-importe-com-me="{{ $importeComMe }}"
                                data-cotizacion-com="{{ $cotCom }}">
                                <td class="text-center align-middle">
                                    <input type="checkbox"
                                           class="cp-com-check"
                                           name="recepcion_proveedor_ids[]"
                                           value="{{ $recepcion->id }}"
                                           @if (in_array((int) $recepcion->id, $seleccionadosInt, true)) checked @endif>
                                </td>
                                <td class="align-middle">{{ $recepcion->id }}</td>
                                <td class="align-middle"><small>{{ $recepcion->fecha ? $recepcion->fecha->format('d/m/Y') : '' }}</small></td>
                                <td class="align-middle"><small>{{ $recepcion->numerorecepcion ?? '' }}</small></td>
                                <td class="align-middle"><small>{{ optional($recepcion->ordencompras)->numeroordencompra ?? ($recepcion->ordencompra_id ?? '—') }}</small></td>
                                <td class="align-middle"><small>{{ $monedaComNombre }}</small></td>
                                <td class="text-right align-middle">
                                    @if ($esMeCom && abs($importeComMn - $importeComMe) > 0.05)
                                        <small>
                                            {{ number_format($importeComMe, 2, ',', '.') }}
                                            <span class="text-muted">× {{ number_format($cotCom, 4, ',', '.') }}</span>
                                            <br><strong>${{ number_format($importeComMn, 2, ',', '.') }}</strong>
                                        </small>
                                    @else
                                        <small>${{ number_format($importeComMn, 2, ',', '.') }}</small>
                                    @endif
                                </td>
                                <td class="text-right align-middle">
                                    <small class="{{ $cotDistinta ? 'text-danger font-weight-bold' : '' }}">
                                        {{ number_format($cotCom, 4, ',', '.') }}
                                    </small>
                                </td>
                                <td class="align-middle"><small>#{{ $recepcion->asiento_id ?? '—' }}</small></td>
                                <td class="text-center align-middle">
                                    @if ($puedeVerRecepcion)
                                    <a href="{{ route('editar_recepcion_proveedor', ['id' => $recepcion->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="btn-accion-tabla tooltipsC text-primary"
                                       title="Ver recepción #{{ $recepcion->id }}"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                    @else
                                    <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        @empty
                            <tr><td colspan="10" class="text-muted">No hay recepciones COM disponibles en el legajo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <h5 class="mb-2"><i class="fa fa-cubes"></i> Artículos de las recepciones seleccionadas</h5>
                <div id="cp-com-articulos-wrap">
                    @foreach ($mostrarIds as $recepcionId)
                        @php
                            $recepcion = ($recepciones_disponibles ?? collect())->firstWhere('id', $recepcionId);
                            if (! $recepcion) {
                                $vinculo = optional($data->comprobante_proveedor_recepciones ?? collect())
                                    ->firstWhere('recepcion_proveedor_id', $recepcionId);
                                $recepcion = $vinculo?->recepcion_proveedores;
                            }
                            $articulos = $recepcion?->recepcion_proveedor_articulos ?? collect();
                            $seleccionada = in_array((int) $recepcionId, $seleccionadosInt, true);
                        @endphp
                        @if ($recepcion)
                        <div class="cp-com-articulos-bloque mb-3" data-recepcion-id="{{ $recepcion->id }}"
                             style="{{ $seleccionada ? '' : 'display:none;' }}">
                            <p class="mb-1 font-weight-bold small">
                                COM #{{ $recepcion->id }}
                                @if ($puedeVerRecepcion)
                                    <a href="{{ route('editar_recepcion_proveedor', ['id' => $recepcion->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="btn btn-outline-primary btn-xs ml-2" target="_blank" rel="noopener">Abrir recepción</a>
                                @endif
                            </p>
                            @if ($articulos->isEmpty())
                                <p class="text-muted small mb-0">Sin artículos en esta recepción.</p>
                            @else
                                <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0 cp-com-tabla-articulos" style="table-layout:fixed;width:100%;">
                                    <colgroup>
                                        <col style="width:34%;">
                                        <col style="width:12%;">
                                        <col style="width:8%;">
                                        <col style="width:14%;">
                                        <col style="width:14%;">
                                        <col style="width:18%;">
                                    </colgroup>
                                    <thead style="background-color:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Artículo</th>
                                            <th class="text-right">Cantidad</th>
                                            <th>UM</th>
                                            <th class="text-right">Precio</th>
                                            <th class="text-right">Importe</th>
                                            <th>Detalle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($articulos as $linea)
                                            @php
                                                $cant = (float) ($linea->cantidad ?? 0);
                                                $precio = (float) ($linea->precio ?? 0);
                                                $sku = $linea->articulos->sku ?? '';
                                                $nombreArt = $linea->articulos->descripcion ?? ($linea->detalle ?? '—');
                                            @endphp
                                            <tr>
                                                <td class="align-middle"><small>{{ $sku }} {{ $nombreArt }}</small></td>
                                                <td class="text-right align-middle"><small>{{ number_format($cant, 3, ',', '.') }}</small></td>
                                                <td class="align-middle"><small>{{ $linea->unidadesmedida->nombre ?? '' }}</small></td>
                                                <td class="text-right align-middle"><small>{{ number_format($precio, 4, ',', '.') }}</small></td>
                                                <td class="text-right align-middle"><small>{{ number_format($cant * $precio, 2, ',', '.') }}</small></td>
                                                <td class="align-middle"><small>{{ $linea->detalle ?? '' }}</small></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                </div>
                            @endif
                        </div>
                        @endif
                    @endforeach
                    <p id="cp-com-articulos-vacio" class="text-muted small" style="{{ count($seleccionadosInt) > 0 ? 'display:none;' : '' }}">
                        Marque una recepción COM para ver sus artículos.
                    </p>
                </div>

                <div class="mt-3 p-2 border rounded bg-light small" id="cp-com-asiento-hint">
                    <strong><i class="fa fa-calculator"></i> Impacto en asiento (modo COM):</strong>
                    <ul class="mb-0 pl-3 mt-1">
                        <li>Debe: reversión de provisión (facturas a recibir) por el neto COM seleccionado</li>
                        <li>Debe: impuestos de la factura (conceptos IVA)</li>
                        <li>Debe: si neto factura &gt; provisión COM → diferencia prorrateada en cuentas de artículos de la COM</li>
                        <li>Haber: cuenta del proveedor (según moneda MN/ME)</li>
                        <li>El asiento de la recepción permanece intacto</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
@elseif ($modoRecepcion && ! $hayRecepciones)
<div class="alert alert-warning mt-3" id="cp-bloque-com-vacio">
    <i class="fa fa-exclamation-triangle"></i>
    <strong>Sin recepciones COM para elegir.</strong>
    No hay COM confirmadas con asiento de provisión disponibles en la OC/legajo
    (o ya están facturadas en otro comprobante). Confirme una recepción en Stock o revise el legajo.
</div>
@elseif (($com_politica['permite_factura_anticipada'] ?? false))
<div class="alert alert-info mt-3" id="cp-bloque-factura-anticipada">
    <i class="fa fa-info-circle"></i>
    <strong>Factura anticipada:</strong> la OC es anticipada y todavía no hay COM con provisión.
    El neto irá a la cuenta de anticipo. Puede cargar más de una factura anticipada en este legajo;
    cuando exista recepción COM, las siguientes facturas deberán asociarla.
</div>
@elseif (($com_politica['bloquea_sin_com'] ?? false))
<div class="alert alert-danger mt-3">
    <i class="fa fa-exclamation-triangle"></i>
    El flujo OC/COM/factura exige asignación de recepción COM, pero no hay COM confirmadas con provisión disponibles
    y la OC no es anticipada. Confirme una recepción en Stock antes de continuar.
</div>
@endif
