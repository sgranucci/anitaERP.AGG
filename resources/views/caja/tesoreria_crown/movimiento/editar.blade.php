@extends("theme.$theme.layout")

@section('titulo')
    {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script>
(function () {
    function texto(numero) {
        return numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function recalcular(panel) {
        var total = 0;
        panel.querySelectorAll('input[data-denom]').forEach(function (input) {
            var cantidad = parseFloat(String(input.value).replace(',', '.'));
            var denominacion = parseFloat(input.getAttribute('data-denom'));
            if (!isNaN(cantidad) && !isNaN(denominacion)) {
                total += cantidad * denominacion;
            }
        });
        var escrito = texto(total);
        panel.querySelectorAll('.js-total-valor').forEach(function (nodo) {
            nodo.textContent = escrito;
        });
        var codigo = panel.getAttribute('data-grupo');
        if (codigo) {
            document.querySelectorAll('.js-total-barra[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = escrito;
            });
        }
    }
    document.querySelectorAll('.js-panel-valor').forEach(function (panel) {
        recalcular(panel);
        panel.addEventListener('input', function () { recalcular(panel); });
    });

    var campos = Array.prototype.slice.call(document.querySelectorAll('#form-cantidades-crown input[data-denom], #form-cantidades-crown input.js-importe'));
    if (!campos.length) {
        return;
    }
    campos[0].focus();
    campos[0].select();
    campos.forEach(function (campo, indice) {
        campo.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter') {
                return;
            }
            evento.preventDefault();
            var siguiente = campos[indice + 1];
            if (siguiente) {
                siguiente.focus();
                siguiente.select();
                return;
            }
            var boton = document.getElementById('btn-guardar-cantidades');
            if (boton) {
                boton.focus();
            }
        });
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.mensaje')
@php
    $esHabilitacion = $comprobante->tipo === \App\Support\Caja\TesoreriaCrownComprobanteSupport::HABILITACION;
    $etiquetaEntrega = \App\Support\Caja\TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, 'entrega');
    $etiquetaRecibe = \App\Support\Caja\TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, 'recibe');
    $ladoCajero = \App\Support\Caja\TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo);
    $ladoTesoreria = \App\Support\Caja\TesoreriaCrownFirmaSupport::ladoTesoreria($comprobante->tipo);
    $urlFirma = $comprobante->qr_token ? route('firma_qr_tesoreria_crown_movimiento', $comprobante->qr_token) : '';
    $qrFirma = $urlFirma !== '' ? \App\Support\Ventas\QrCodePngSupport::dataUri($urlFirma, 200) : '';
    $cajeroFirma = $comprobante->asignacion->cajero;
    $yaRevertido = \App\Models\Caja\TesoreriaCrownComprobante::query()->where('revierte_id', $comprobante->id)->exists();
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }}
                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($comprobante->numero) }}
                    · {{ $comprobante->jornada->fecha->format('d/m/Y') }}
                    — {{ $comprobante->asignacion->deposito->nombre ?? '' }}
                    — {{ $comprobante->asignacion->cajero->nombre ?? '' }}
                </h3>
                <div class="card-tools d-flex align-items-center" style="gap:.35rem;">
                    <a href="{{ route('pdf_tesoreria_crown_movimiento', $comprobante->id) }}" class="btn btn-light btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf"></i> Imprimir
                    </a>
                    @if ($comprobante->estado === 'C' && $comprobante->revierte_id === null && ! $yaRevertido && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                        <form method="POST" action="{{ route('revertir_tesoreria_crown_movimiento', $comprobante->id) }}" class="d-inline mb-0" onsubmit="return confirm('¿Revertir este comprobante? Solo se puede una vez.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-light btn-sm">Revertir</button>
                        </form>
                    @endif
                    <a href="{{ route('ver_tesoreria_crown_jornada', $comprobante->jornada_id) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a la jornada
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Estado: <strong>{{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($comprobante->estado) }}</strong>
                    @if ($comprobante->revierte_id)
                        <span class="text-muted">Reversa del comprobante {{ $comprobante->revierte_id }}.</span>
                    @endif
                    @if ($yaRevertido)
                        <span class="text-muted">Ya tiene reversa.</span>
                    @endif
                </p>
                @if ($comprobante->observacion)
                    <p class="text-muted">{{ $comprobante->observacion }}</p>
                @endif

                @if ($esHabilitacion)
                    <p class="text-muted">Efectivo de la habilitación: pesos, dólares y euros, de mayor denominación a menor.</p>
                @else
                    <p class="text-muted">Efectivo, fichas y, al final, cashless y tarjetas.@if (\App\Support\Caja\TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->asignacion->tipo_tarea ?? '')) Esta caja se abrió como {{ \App\Support\Caja\TesoreriaCrownAsignacionCatalogo::tarea($comprobante->asignacion->tipo_tarea) }}, así que también pide el informe de gastronomía u hotel.@endif</p>
                @endif

                <form method="POST" action="{{ route('guardar_tesoreria_crown_movimiento', $comprobante->id) }}" id="form-cantidades-crown">
                    @csrf
                    @if ($editable)
                        <div class="border rounded px-3 py-2 mb-3 bg-light d-flex flex-wrap align-items-center" id="barra-totales-crown" style="position:sticky;top:3.5rem;z-index:5;">
                            @foreach ($grupos as $grupoBarra)
                                @php
                                    $totalBarra = 0.0;
                                    foreach ($grupoBarra['filas'] as $filaBarra) {
                                        $totalBarra += (float) $filaBarra->denominacion * (float) ($cantidades[$filaBarra->id] ?? 0);
                                    }
                                @endphp
                                <span class="mr-4 mb-1">
                                    <span class="text-muted">{{ $grupoBarra['titulo'] }}</span>
                                    <strong class="js-total-barra" data-grupo="{{ $grupoBarra['codigo'] }}">{{ number_format($totalBarra, 2, ',', '.') }}</strong>
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <div class="row">
                        @foreach ($grupos as $grupo)
                            @php
                                $totalGrupo = 0.0;
                                foreach ($grupo['filas'] as $filaTotal) {
                                    $totalGrupo += (float) $filaTotal->denominacion * (float) ($cantidades[$filaTotal->id] ?? 0);
                                }
                            @endphp
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card card-outline card-info h-100 mb-0 js-panel-valor" data-grupo="{{ $grupo['codigo'] }}">
                                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0">{{ $grupo['titulo'] }}</h3>
                                        <strong class="js-total-valor">{{ number_format($totalGrupo, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="card-body p-0 table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr>
                                                    <th>Denominación</th>
                                                    <th>Tipo</th>
                                                    <th style="width:110px;">Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($grupo['filas'] as $valor)
                                                    @php
                                                        $denominacion = (float) $valor->denominacion;
                                                        $denominacionTexto = fmod($denominacion, 1.0) === 0.0
                                                            ? number_format($denominacion, 0, ',', '.')
                                                            : number_format($denominacion, 2, ',', '.');
                                                    @endphp
                                                    <tr>
                                                        <td class="text-right">{{ $denominacionTexto }}</td>
                                                        <td>{{ $valor->descripcion }}</td>
                                                        <td>
                                                            @if ($editable)
                                                                <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" data-denom="{{ $denominacion }}" value="{{ old('cantidad.'.$valor->id, $cantidades[$valor->id] ?? '') }}">
                                                            @else
                                                                {{ $cantidades[$valor->id] ?? '' }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer py-2 text-right">
                                        Total <strong class="js-total-valor">{{ number_format($totalGrupo, 2, ',', '.') }}</strong>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if (! $esHabilitacion)
                        @include('caja.tesoreria_crown.movimiento.partials.medios_rendicion')
                    @endif
                    @if ($editable && can('crear-tesoreria-crown-movimiento', false))
                        <button type="submit" class="btn btn-primary" name="accion" value="guardar" id="btn-guardar-cantidades">Guardar borrador</button>
                        <button type="submit" class="btn btn-outline-primary ml-1" name="accion" value="pedir-firmas">Pedir firmas</button>
                        <span class="text-muted small ml-2">Pedir firmas guarda las cantidades de esta pantalla y las pasa a firma.</span>
                    @endif
                </form>

                @php
                    $firmas = [
                        'entrega' => [$etiquetaEntrega, $comprobante->entregaUsuario, $comprobante->entrega_at],
                        'recibe' => [$etiquetaRecibe, $comprobante->recibeUsuario, $comprobante->recibe_at],
                    ];
                @endphp
                <div class="row mt-4">
                    @foreach ($firmas as $lado => [$etiqueta, $usuarioFirma, $cuando])
                        <div class="col-md-6">
                            <div class="border rounded p-3 mb-3 h-100">
                                <strong>{{ $etiqueta }}</strong>
                                <p class="mb-2">{{ \App\Support\Caja\TesoreriaCrownFirmaSupport::textoFirma($usuarioFirma, $cuando) }}</p>
                                @if ($lado === $ladoCajero && $comprobante->estado === 'P' && $cuando === null && $qrFirma !== '')
                                    <p class="mb-2">El cajero {{ $cajeroFirma->nombre ?? '' }} escanea este código con el teléfono y firma con su usuario.</p>
                                    <img src="{{ $qrFirma }}" alt="QR de firma del cajero" width="180" height="180">
                                    @if (! $cajeroFirma || ! $cajeroFirma->usuario_id)
                                        <p class="text-danger small mt-2 mb-0">Este cajero no tiene usuario. Vinculalo abajo: sin eso el teléfono no deja firmar.</p>
                                    @else
                                        <p class="text-muted small mt-2 mb-0">Firma el usuario {{ $cajeroFirma->usuario->usuario ?? '' }}.</p>
                                    @endif
                                @endif
                                @if ($lado === $ladoTesoreria && $comprobante->estado === 'P' && $cuando === null && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                                    <form method="POST" action="{{ route('firmar_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off">
                                        @csrf
                                        <p class="small text-muted">Firmá con tu usuario. La clave no es la del usuario genérico de la caja.</p>
                                        <div class="form-row">
                                            <div class="col-6 mb-2">
                                                <input type="text" name="firma_usuario" class="form-control form-control-sm" placeholder="Usuario" autocomplete="off" required>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <input type="password" name="firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="new-password" required>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-sm">Firmar {{ $etiqueta }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($comprobante->estado === 'P' && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                    <div class="border rounded p-3 mb-3">
                        <strong>Usuario del cajero</strong>
                        @if ($cajeroFirma && $cajeroFirma->usuario_id)
                            <p class="mb-2">{{ $cajeroFirma->nombre }} firma con {{ $cajeroFirma->usuario->nombre ?? '' }} ({{ $cajeroFirma->usuario->usuario ?? '' }}).</p>
                        @else
                            <p class="mb-2">{{ $cajeroFirma->nombre ?? 'El cajero' }} todavía no tiene usuario.</p>
                        @endif
                        <form method="POST" action="{{ route('vincular_cajero_tesoreria_crown_movimiento', $comprobante->id) }}" class="mb-0">
                            @csrf
                            <div class="tm-usuario-campo d-flex flex-nowrap align-items-center" style="gap:4px;max-width:36rem;">
                                <input type="hidden" name="usuario_id" class="usuario_id" value="">
                                <input type="text" class="usuario_codigo_arbol form-control form-control-sm" placeholder="Usuario" autocomplete="off" style="width:7rem;" title="Login + Enter. F1 consulta">
                                <button type="button" title="Consulta usuarios (F1)" class="btn-accion-tabla consultausuario" data-omitir_filtro_empresa="1">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="nombreusuario form-control form-control-sm" placeholder="Nombre" readonly>
                                <button type="submit" class="btn btn-outline-primary btn-sm">Vincular</button>
                            </div>
                        </form>
                    </div>
                    @if (can('crear-tesoreria-crown-movimiento', false))
                        <form method="POST" action="{{ route('volver_borrador_tesoreria_crown_movimiento', $comprobante->id) }}" class="d-inline mb-3" onsubmit="return confirm('¿Volver a borrador? Se borran las firmas y el QR.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Volver a borrador</button>
                        </form>
                    @endif
                @endif

                @if (in_array($comprobante->estado, ['B', 'P'], true) && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('noconforme_tesoreria_crown_movimiento', $comprobante->id) }}" class="form-inline d-inline ml-2">
                        @csrf
                        <input type="text" name="observacion" class="form-control form-control-sm mr-2" maxlength="500" placeholder="Motivo" required>
                        <button type="submit" class="btn btn-outline-danger btn-sm">No conforme</button>
                    </form>
                @endif

                @if ($comprobante->estado === 'C' && $comprobante->revierte_id === null && ! $yaRevertido && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('revertir_tesoreria_crown_movimiento', $comprobante->id) }}" class="d-inline ml-2" onsubmit="return confirm('¿Revertir este comprobante? Solo se puede una vez.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Revertir</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@endsection
