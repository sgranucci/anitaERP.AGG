<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Aprobación @if(isset($datosComprobante->numeroordenventa)) OV {{ $datosComprobante->numeroordenventa }} @elseif(isset($datosComprobante->numerorequisicion)) REQ {{ $datosComprobante->numerorequisicion }} @elseif(isset($datosComprobante->numeroordencompra)) OC {{ $datosComprobante->numeroordencompra }} @elseif($tipoArbol == 'Solicitudes de pago') SP {{ $datosComprobante->codigo ?? $datosComprobante->id }} @endif</title>
</head>
<body>
    @if ($tipoArbol == 'Ordenes de venta')
        <p>Hola! Tiene una Orden de venta para aprobación</p>
    @elseif ($tipoArbol == 'Pedidos')
        <p>Hola! Tiene un Pedido para aprobación</p>
    @elseif ($tipoArbol == 'Ordenes de compra')
        <p>Hola! Tiene una Orden de compra para aprobación</p>
        @php $mx = $mailExtras ?? []; @endphp
        @if (empty($mx['es_legajo_gastronomia']))
            @if (!empty($mx['estado_tras_aprobar']))
                <p>Al <strong>aprobar este paso</strong>, la orden de compra quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
            @else
                <p>En este nivel del árbol <strong>no está definido</strong> un estado destino al aprobar; solo avanzará la aprobación del flujo.</p>
            @endif
        @endif
    @elseif ($tipoArbol == 'Solicitudes de pago')
        @php $mx = $mailExtras ?? []; @endphp
        @if (!empty($mx['es_aviso_pago']))
            <p>Hola! Tiene una Solicitud de pago <strong>autorizada</strong> lista para pagar</p>
            <p>Este correo es un <strong>aviso a pagadores</strong>: la SP no cambia a Pagada desde el árbol.
                El estado <strong>Pagada</strong> se registra al emitir el ingreso/egreso (orden de pago).</p>
        @else
            <p>Hola! Tiene una Solicitud de pago para aprobación</p>
            @if (!empty($mx['estado_tras_aprobar']))
                <p>Al <strong>aprobar este paso</strong>, la solicitud de pago quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
            @else
                <p>En este nivel del árbol <strong>no se cambia</strong> el estado de cabecera al aprobar; solo avanzará la aprobación del flujo.</p>
            @endif
        @endif
    @else
        <p>Hola! Tiene una Requisición para aprobación</p>
        @php $mx = $mailExtras ?? []; @endphp
        @if (!empty($mx['estado_tras_aprobar']))
            <p>Al <strong>aprobar este paso</strong>, la requisición quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
        @else
            <p>En este nivel del árbol <strong>no está definido</strong> un estado destino para la requisición al aprobar; solo avanzará la aprobación del flujo.</p>
        @endif
    @endif

    <p>Estos son los datos:</p>
    @if ($tipoArbol == 'Pedidos')
        <ul>
            <li>Código: {{ $datosComprobante->codigo ?? $datosComprobante->id }}</li>
            <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
            <li>Cliente: {{ $datosComprobante->clientes->nombre ?? '' }}</li>
            <li>O. Compra: {{ $datosComprobante->orden_compra ?? '' }}</li>
            @php $mx = $mailExtras ?? []; @endphp
            @if (!empty($mx['monto_items']))
                <li>Monto: {{ $mx['moneda_abrev_items'] ?? '' }} {{ number_format($mx['monto_items'], 2, ',', '.') }}</li>
            @endif
            <br><br>
            <label for="Autorizar">Autorizar:</label>
            <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
            <br>
            <label for="Autorizar">Rechazar:</label>
            <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
        </ul>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
    @elseif ($tipoArbol == 'Ordenes de venta')
        <ul>
            <li>Tratamiento: {{ $datosComprobante->tratamiento }} </li>
            <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }} </li>
            <li>Número: {{ $datosComprobante->numeroordenventa }} </li>
            <li>Fecha de la órden: {{ date("d/m/Y", strtotime($datosComprobante->fecha ?? '')) }} </li>
            <li>Monto: {{$datosComprobante->monedas->abreviatura}} {{ number_format($datosComprobante->monto,2) }}</li>
            <li>Forma de pago: {{ $datosComprobante->formapagos->nombre }}</li>
            <li>Cliente: {{$datosComprobante->nombrecliente}}</li>
            <li>Comentarios: {{$datosComprobante->comentario}}</li>
            <li>Detalle a Facturar: {{$datosComprobante->detalle}}</li>
            <br><br>
            <label for="Autorizar">Autorizar:</label>
            <div>
                <p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p>
            </div>
            <br>
            <label for="Autorizar">Rechazar:</label>
            <div>
                <p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p>
            </div>
        </ul>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div>
            <p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p>
        </div>
    @elseif ($tipoArbol == 'Ordenes de compra')
        @php $mx = $mailExtras ?? []; @endphp
        @if (!empty($mx['es_legajo_gastronomia']))
            <div style="background:#fff3cd;border:1px solid #ffeeba;border-radius:4px;padding:12px 14px;margin:12px 0;font-size:14px;color:#856404;line-height:1.45;">
                &#9888; Tenés un <strong>legajo pendiente</strong> de
                <strong>{{ $mx['proveedor_nombre'] ?? ($datosComprobante->proveedores->nombre ?? '—') }}</strong>
                por <strong>$ {{ $mx['alerta_importe_fmt'] ?? number_format($mx['monto_items'] ?? 0, 2, ',', '.') }}</strong>
                @if (!empty($mx['alerta_con_iva']))
                    (IVA incluido)
                @endif
                — {{ $mx['centrocosto_corto'] ?? 'Gastronomía' }},
                OC {{ $datosComprobante->numeroordencompra }}.
            </div>
            @if (!empty($mx['estado_tras_aprobar']))
                <p>Al <strong>aprobar este paso</strong>, la orden de compra quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
            @endif
            <p style="font-size:14px;color:#444;">
                Use los enlaces de abajo para <strong>Autorizar</strong>, <strong>Rechazar</strong>
                o <strong>Visualizar</strong> el legajo completo (Factura + OC + COM).
            </p>
            <ul>
                <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }}</li>
                <li>Número OC: {{ $datosComprobante->numeroordencompra }}</li>
                <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
                <li>
                    Monto total ítems: {{ $mx['moneda_abrev_items'] ?? '—' }}
                    {{ number_format($mx['monto_items'] ?? 0, 2, ',', '.') }} (sin IVA)
                    @if (!empty($mx['total_factura_fmt']))
                        — <strong>Total factura:</strong> {{ $mx['moneda_abrev_items'] ?? 'PES' }}
                        {{ $mx['total_factura_fmt'] }} (con IVA)
                    @endif
                </li>
                <li>Proveedor: {{ $datosComprobante->proveedores->nombre ?? '—' }}</li>
                <li>Comentarios: {{ $datosComprobante->comentario }}</li>
                @if (!empty($mx['comentario_envio']))
                <li><strong>Comentario al enviar al árbol:</strong> {{ $mx['comentario_envio'] }}</li>
                @endif
                <li>Detalle: {{ $datosComprobante->detalle }}</li>
            </ul>
            <br>
            <label for="Autorizar">Autorizar:</label>
            <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
            <br>
            <label for="Rechazar">Rechazar:</label>
            <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
            <br>
            <label for="Visualizar">Visualizar (Factura + OC + COM):</label>
            <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
        @else
            <p style="font-size:14px;color:#444;">Al abrir los enlaces de <strong>Autorizar</strong> o <strong>Rechazar</strong> verá el detalle y podrá cargar observaciones antes de confirmar.</p>
            <ul>
                <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }}</li>
                <li>Número OC: {{ $datosComprobante->numeroordencompra }}</li>
                <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
                <li>Monto total ítems: {{ $mx['moneda_abrev_items'] ?? '—' }} {{ number_format($mx['monto_items'] ?? 0, 2) }}</li>
                <li>Proveedor: {{ $datosComprobante->proveedores->nombre ?? '—' }}</li>
                <li>Comentarios: {{ $datosComprobante->comentario }}</li>
                @if (!empty($mx['comentario_envio']))
                <li><strong>Comentario al enviar al árbol:</strong> {{ $mx['comentario_envio'] }}</li>
                @endif
                <li>Detalle: {{ $datosComprobante->detalle }}</li>
            </ul>
            <br>
            <label for="Autorizar">Autorizar:</label>
            <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
            <br>
            <label for="Rechazar">Rechazar:</label>
            <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
            <br>
            <label for="Visualizar">Visualizar:</label>
            <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
        @endif
    @elseif ($tipoArbol == 'Requisiciones de sala')
        @php $mx = $mailExtras ?? []; @endphp
        <p style="font-size:14px;color:#444;">Al abrir los enlaces de <strong>Autorizar</strong> o <strong>Rechazar</strong> verá el detalle en una pantalla adaptable a celular y podrá cargar observaciones antes de confirmar.</p>
        @if (!empty($mx['estado_tras_aprobar']))
            <p>Al <strong>aprobar este paso</strong>, la requisición quedará en estado <strong>{{ $mx['estado_tras_aprobar'] }}</strong>.</p>
        @else
            <p>En este nivel del árbol <strong>no está definido</strong> un estado destino al aprobar; solo avanzará la aprobación del flujo.</p>
        @endif
        @if (!empty($mx['genera_transferencia_laboratorio']))
            <p><strong>Importante:</strong> esta aprobación generará una <strong>transferencia de mercadería</strong> desde el depósito de la requisición hacia <strong>{{ $mx['deposito_laboratorio'] ?? 'laboratorio' }}</strong> (ítems reparación/devolución).</p>
            @php
                $pf = $mx['transferencia_laboratorio_preflight'] ?? [];
                $tmNoViable = !empty($pf['aplica']) && ($pf['viable'] ?? true) === false;
                $esCentroConsumo = !empty($pf['deposito_origen_es_centro_consumo']);
            @endphp
            @if($esCentroConsumo)
                <p style="font-size:13px;color:#555;">
                    {{ $pf['mensaje_informativo'] ?? 'El depósito de origen es centro de consumo: no se valida saldo y la transferencia se registrará igual.' }}
                </p>
            @elseif($tmNoViable)
                <p style="color:#c0392b;font-weight:bold;">
                    Atención: con el saldo actual del depósito de origen <strong>no se podrá realizar</strong> la transferencia automática al laboratorio.
                    {{ $pf['mensaje_resumen'] ?? '' }}
                </p>
                @php $lineasProblema = collect($pf['lineas_detalle'] ?? [])->filter(static fn ($f) => empty($f['ok']))->values(); @endphp
                @if($lineasProblema->isNotEmpty())
                    <p style="font-size:13px;margin-bottom:4px;">Ítems sin saldo suficiente:</p>
                    <ul style="font-size:13px;">
                        @foreach($lineasProblema as $fila)
                            <li>
                                {{ $fila['sku'] ?? '' }} {{ $fila['descripcion'] ?? '' }}
                                (req. {{ number_format((float) ($fila['cantidad_requerida'] ?? 0), 4, ',', '.') }}
                                @if(isset($fila['saldo_disponible']))
                                    / saldo {{ number_format((float) $fila['saldo_disponible'], 4, ',', '.') }}
                                @else
                                    / sin saldo
                                @endif
                                )
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        @endif
        <ul>
            <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }}</li>
            <li>Número: {{ $datosComprobante->numerorequisicion }}</li>
            <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
            <li>Monto total ítems: {{ number_format($mx['monto_items'] ?? 0, 2, ',', '.') }}</li>
            <li>Depósito origen: {{ optional($datosComprobante->depositos)->nombre ?? '—' }}</li>
            <li>Comentarios: {{ $datosComprobante->comentario }}</li>
            <li>Detalle: {{ $datosComprobante->detalle }}</li>
        </ul>
        <br>
        <label for="Autorizar">Autorizar:</label>
        <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
        <br>
        <label for="Rechazar">Rechazar:</label>
        <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
    @elseif ($tipoArbol == 'Solicitudes de pago')
        @php
            $mx = $mailExtras ?? [];
            $esAvisoPago = ! empty($mx['es_aviso_pago']);
            $linkPago = $mx['link_pago'] ?? null;
            $monedaAbr = trim((string) ($mx['moneda_abrev_items'] ?? (optional($datosComprobante->monedas)->abreviatura ?? '')));
            $montoFmt = $mx['monto_items_fmt']
                ?? number_format((float) ($mx['monto_items'] ?? $datosComprobante->monto ?? 0), 2, ',', '.');
            $conceptoLabel = optional($datosComprobante->conceptos)->codigo
                ? trim((optional($datosComprobante->conceptos)->codigo).' — '.(optional($datosComprobante->conceptos)->nombre ?? ''))
                : (optional($datosComprobante->conceptos)->nombre ?? '—');
            $sectorLabel = optional($datosComprobante->sectores)->codigo
                ? trim((optional($datosComprobante->sectores)->codigo).' — '.(optional($datosComprobante->sectores)->nombre ?? ''))
                : (optional($datosComprobante->sectores)->nombre ?? '—');
            $formaPagoLabel = optional($datosComprobante->formapagosol)->nombre ?? '—';
            $estadoLabel = \App\Support\Solicitudpago\SolicitudpagoEstados::label($datosComprobante->estado ?? '');
            $linkDescarga = $mx['link_descarga_paquete'] ?? null;
        @endphp
        @if ($esAvisoPago)
            <p style="font-size:14px;color:#444;">Use <strong>Ir a pagar</strong> para abrir Ingresos y egresos con la solicitud precargada (requiere iniciar sesi&oacute;n).
                <strong>Rechazar</strong> deja la SP en estado Rechazada si no corresponde pagar.</p>
        @else
            <p style="font-size:14px;color:#444;">Al abrir los enlaces de <strong>Autorizar</strong> o <strong>Rechazar</strong> verá el detalle en una pantalla adaptable a celular y podrá cargar observaciones antes de confirmar.</p>
        @endif
        <ul style="line-height:1.5;">
            <li><strong>Empresa:</strong> {{ $datosComprobante->empresas->nombre ?? '' }}</li>
            <li><strong>Código SP:</strong> {{ $datosComprobante->codigo ?? $datosComprobante->id }}</li>
            <li><strong>Fecha:</strong> {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
            <li><strong>Monto:</strong> {{ $monedaAbr !== '' ? $monedaAbr.' ' : '' }}{{ $montoFmt }}</li>
            <li><strong>Concepto:</strong> {{ $conceptoLabel }}</li>
            <li><strong>Sector:</strong> {{ $sectorLabel }}</li>
            <li><strong>Forma de pago:</strong> {{ $formaPagoLabel }}</li>
            <li><strong>Proveedor:</strong> {{ optional($datosComprobante->proveedores)->nombre ?? '—' }}</li>
            <li><strong>Beneficiario:</strong> {{ $datosComprobante->beneficiario ?? '—' }}</li>
            <li><strong>Estado:</strong> {{ $estadoLabel !== '' ? $estadoLabel : ($datosComprobante->estado ?? '—') }}</li>
            <li><strong>Detalle:</strong> {{ $datosComprobante->detalle ?? '' }}</li>
            <li><strong>Observación:</strong> {{ $datosComprobante->observacion ?? '' }}</li>
        </ul>
        <p style="margin-top:18px;">
            @if ($esAvisoPago && ! empty($linkPago))
                <a href="{{ $linkPago }}" style="background:#28a745; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; margin-right:8px; display:inline-block;">Ir a pagar</a>
            @elseif (! $esAvisoPago)
                <a href="{{ $linkAprobacion }}" style="background:#28a745; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; margin-right:8px; display:inline-block;">Autorizar</a>
            @endif
            <a href="{{ $linkRechazo }}" style="background:#dc3545; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; margin-right:8px; display:inline-block;">Rechazar</a>
            <a href="{{ $linkVisualizar }}" style="background:#007bff; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; margin-right:8px; display:inline-block;">Visualizar</a>
            @if (!empty($linkDescarga))
                <a href="{{ $linkDescarga }}" style="background:#6c757d; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; display:inline-block;">Descargar PDF + adjuntos</a>
            @endif
        </p>
        <p style="font-size:12px;color:#666;margin-top:10px;">
            @if (!empty($mx['adjuntos_mail_cantidad']))
                Este correo incluye como <strong>adjuntos</strong> el PDF de la solicitud
                @if (!empty($mx['tiene_archivos']))
                    y los archivos asociados
                @endif
                ({{ (int) $mx['adjuntos_mail_cantidad'] }} archivo(s)).
            @else
                Si el tamaño lo permite, este correo adjunta el PDF de la solicitud y los archivos asociados.
            @endif
            El botón <strong>Descargar PDF + adjuntos</strong> sigue disponible para bajar un único PDF unificado
            @if (!empty($mx['tiene_archivos']))
                con la impresión y todos los archivos.
            @else
                con la impresión de la solicitud.
            @endif
        </p>
        @if (!empty($mx['adjuntos_mail_omitidos']) && is_array($mx['adjuntos_mail_omitidos']))
            <p style="font-size:11px;color:#a66;margin-top:6px;">
                No se adjuntaron al mail (usar descarga): {{ implode('; ', $mx['adjuntos_mail_omitidos']) }}.
            </p>
        @endif
        <p style="font-size:11px;color:#888;margin-top:8px;">
            Enlaces directos:<br>
            @if ($esAvisoPago && ! empty($linkPago))
                Ir a pagar: <a href="{{ $linkPago }}">{{ $linkPago }}</a><br>
            @elseif (! $esAvisoPago)
                Autorizar: <a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a><br>
            @endif
            Rechazar: <a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a><br>
            Visualizar: <a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a>
            @if (!empty($linkDescarga))
                <br>Descargar: <a href="{{ $linkDescarga }}">{{ $linkDescarga }}</a>
            @endif
        </p>
    @elseif ($tipoArbol == 'Requisiciones')
        @php
            $mx = $mailExtras ?? [];
            $ccMail = trim((string) ($mx['centrocosto'] ?? ''));
            if ($ccMail === '') {
                $ccMail = trim((optional($datosComprobante->centrocostos)->codigo ?? '').' '.(optional($datosComprobante->centrocostos)->nombre ?? ''));
            }
            $historialMail = $mx['historial_aprobaciones'] ?? [];
        @endphp
        <p style="font-size:14px;color:#444;">Al abrir los enlaces de <strong>Autorizar</strong> o <strong>Rechazar</strong> verá el detalle en una pantalla adaptable a celular y podrá cargar observaciones antes de confirmar.</p>
        <ul>
            <li>Empresa: {{ $datosComprobante->empresas->nombre ?? '' }}</li>
            <li>Número: {{ $datosComprobante->numerorequisicion }}</li>
            <li>Fecha: {{ date('d/m/Y', strtotime($datosComprobante->fecha ?? '')) }}</li>
            <li>Solicitante: {{ $mx['solicitante'] ?? (optional($datosComprobante->usuarios)->nombre ?? '—') }}</li>
            <li>Centro de costo: {{ $ccMail !== '' ? $ccMail : '—' }}</li>
            <li>Monto total ítems (Σ cantidad × precio, moneda del primer ítem, cotización del día según fecha de la requisición): {{ $mx['moneda_abrev_items'] ?? '—' }} {{ number_format($mx['monto_items'] ?? 0, 2) }}</li>
            <li>Proveedor sugerido: {{ $datosComprobante->proveedores->nombre ?? '—' }}</li>
            <li>Comentarios: {{ $datosComprobante->comentario }}</li>
            @if (!empty($mx['comentario_envio']))
            <li><strong>Comentario al enviar al árbol:</strong> {{ $mx['comentario_envio'] }}</li>
            @endif
            <li>Detalle: {{ $datosComprobante->detalle }}</li>
        </ul>
        @if (!empty($historialMail))
            <p><strong>Aprobaciones previas del área</strong></p>
            <ul>
                @foreach ($historialMail as $h)
                    <li>
                        Nivel {{ $h['nivel'] ?? '—' }}:
                        {{ $h['firmante'] ?? '—' }}
                        @if (!empty($h['fecha']))
                            ({{ $h['fecha'] }})
                        @endif
                        @if (!empty($h['observacion']))
                            — {{ $h['observacion'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <br>
        <label for="Autorizar">Autorizar:</label>
        <div><p><a href="{{ $linkAprobacion }}">{{ $linkAprobacion }}</a></p></div>
        <br>
        <label for="Rechazar">Rechazar:</label>
        <div><p><a href="{{ $linkRechazo }}">{{ $linkRechazo }}</a></p></div>
        <br>
        <label for="Visualizar">Visualizar:</label>
        <div><p><a href="{{ $linkVisualizar }}">{{ $linkVisualizar }}</a></p></div>
    @endif
</body>
</html>
