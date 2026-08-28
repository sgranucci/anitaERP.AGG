@extends("theme.$theme.layout")
@section('titulo')
    COT electr&oacute;nico ARBA
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (!empty($errorCuit))
            <div class="alert alert-danger">{{ $errorCuit }}</div>
        @endif

        @if (!empty($resultadoProceso))
            <div class="alert alert-{{ ($resultadoProceso['ok'] ?? false) ? 'success' : 'danger' }}">
                {{ $resultadoProceso['mensaje'] ?? '' }}
                @if (!empty($resultadoProceso['archivo']['nombre']))
                    <div class="small mt-1">Archivo: {{ $resultadoProceso['archivo']['nombre'] }}</div>
                @endif
                @if (!empty($resultadoProceso['sesion_id']))
                    <div class="mt-1">
                        <a href="{{ route('cot_electronico', ['sesion_id' => $resultadoProceso['sesion_id']]) }}#sesion-detalle" class="alert-link">
                            Ver detalle de la sesi&oacute;n #{{ $resultadoProceso['sesion_id'] }}
                        </a>
                    </div>
                    @if (($resultadoProceso['ok'] ?? false))
                        @if (! empty($imprimirAlProcesar) && empty($tieneImpresoraAsignada))
                            <div class="small mt-2">
                                Marc&oacute; imprimir al procesar, pero no tiene impresora asignada.
                                Configure <strong>Mi impresora</strong> o use el bot&oacute;n Imprimir COT.
                            </div>
                        @endif
                        <div class="mt-2">
                            <a href="{{ route('sesion_impresion_cot', ['id' => $resultadoProceso['sesion_id'], 'auto' => 1]) }}"
                                class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> Imprimir COT
                            </a>
                            <a href="{{ route('sesion_impresion_cot', ['id' => $resultadoProceso['sesion_id'], 'pdf' => 1]) }}"
                                class="btn btn-outline-danger btn-sm">
                                <i class="fa fa-file-pdf"></i> PDF constancia
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        @endif

        @if (!empty($resultadoPruebaConexion))
            @php
                $pruebaOk = (bool) ($resultadoPruebaConexion['ok'] ?? false);
                $httpStatus = $resultadoPruebaConexion['http_status'] ?? null;
                $conectividadOk = array_key_exists('conectividad_ok', $resultadoPruebaConexion)
                    ? (bool) $resultadoPruebaConexion['conectividad_ok']
                    : ($httpStatus >= 200 && $httpStatus < 500);
                $autenticacionOk = (bool) ($resultadoPruebaConexion['autenticacion_ok'] ?? $pruebaOk);
            @endphp
            <div class="alert alert-{{ $pruebaOk ? 'success' : 'danger' }}">
                <strong>Prueba de conexi&oacute;n ARBA</strong>
                @if ($pruebaOk)
                    <span class="badge badge-success ml-1">Lista para enviar remitos</span>
                @else
                    <span class="badge badge-danger ml-1">Revisar credenciales</span>
                @endif
                <div class="mt-2 mb-1">{{ $resultadoPruebaConexion['mensaje'] ?? '' }}</div>
                <ul class="small mb-0 pl-3">
                    <li>
                        <strong>Conectividad:</strong>
                        @if ($conectividadOk)
                            servidor ARBA alcanzable
                            @if ($httpStatus)
                                (HTTP {{ $httpStatus }})
                            @endif
                        @else
                            no se pudo contactar al servidor
                        @endif
                    </li>
                    <li>
                        <strong>Autenticaci&oacute;n CIT:</strong>
                        @if ($autenticacionOk)
                            usuario y clave aceptados
                        @else
                            rechazada
                            @if (!empty($resultadoPruebaConexion['codigo_error']))
                                (c&oacute;digo ARBA {{ $resultadoPruebaConexion['codigo_error'] }})
                            @endif
                        @endif
                    </li>
                    @if (!empty($resultadoPruebaConexion['url']))
                        <li><strong>URL:</strong> {{ $resultadoPruebaConexion['url'] }}</li>
                    @endif
                    @if (!empty($resultadoPruebaConexion['ambiente']))
                        <li><strong>Ambiente:</strong> {{ strtoupper($resultadoPruebaConexion['ambiente']) }}</li>
                    @endif
                </ul>
                @if (! $autenticacionOk)
                    <div class="small mt-2 mb-0">
                        Verifique en <code>.env</code> las claves <code>ARBA_COT_USER</code> y <code>ARBA_COT_PASSWORD</code>
                        (clave CIT de producci&oacute;n ARBA, distinta de homologaci&oacute;n). Luego ejecute
                        <code>php artisan config:clear</code>.
                    </div>
                @endif
            </div>
        @endif

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Generaci&oacute;n COT electr&oacute;nico</h3>
                <div class="card-tools d-flex align-items-center">
                    <form method="post" action="{{ route('cot_electronico_probar_conexion') }}" class="mr-2 mb-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-light" title="Valida URL, usuario y clave CIT sin enviar remitos">
                            <i class="fa fa-plug"></i> Probar conexi&oacute;n ARBA
                        </button>
                    </form>
                    <span class="badge badge-{{ $ambiente === 'prod' ? 'danger' : 'warning' }}">
                        Ambiente {{ strtoupper($ambiente) }}
                    </span>
                </div>
            </div>

            <form method="get" action="{{ route('cot_electronico') }}" id="form-cot-electronico" autocomplete="off"
                data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}"
                data-impresora-nombre="{{ $impresoraUsuario['nombre'] ?? '' }}"
                data-tiene-impresora="{{ ! empty($tieneImpresoraAsignada) ? '1' : '0' }}">
                <input type="hidden" name="consultar" id="input-consultar" value="">
                <div class="card-body">
                    <div class="form-group row">
                        <label for="fecha" class="col-lg-2 col-form-label requerido text-right pr-2">Fecha facturas</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha" class="form-control"
                                value="{{ old('fecha', $fecha) }}" required>
                        </div>
                        <div class="col-lg-7">
                            <p class="form-text mb-0">
                                Fecha de remitos / facturas Anita. Lee remitos de Anita (comprob + pendmae) y suma los de anitaERP que a&uacute;n no est&eacute;n en el bridge. Seleccione repartos, dominio y CUIT del chofer.
                            </p>
                        </div>
                    </div>

                    <h5 class="mt-3">Repartos incluidos</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="tabla-repartos-cot">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:110px;">C&oacute;digo</th>
                                    <th>Nombre</th>
                                    <th style="width:140px;">Dominio cami&oacute;n</th>
                                    <th style="width:160px;">CUIT chofer</th>
                                    <th>Titular CUIT</th>
                                    <th style="width:70px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $filasReparto = count($repartos) ? $repartos : [['transporte_id'=>'','codigo'=>'','nombre'=>'','patente'=>'','cuit_chofer'=>'']]; @endphp
                                @foreach ($filasReparto as $idx => $reparto)
                                    <tr class="fila-reparto">
                                        <td>
                                            <input type="hidden" name="reparto_transporte_id[]" class="input-transporte-id"
                                                value="{{ old('reparto_transporte_id.'.$idx, $reparto['transporte_id'] ?? '') }}">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="reparto_codigo[]" class="form-control input-codigo-reparto"
                                                    value="{{ old('reparto_codigo.'.$idx, $reparto['codigo'] ?? '') }}" maxlength="10">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-primary btn-consulta-reparto-cot" title="Consulta repartos">
                                                        <i class="fa fa-search"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="reparto_nombre[]" class="form-control form-control-sm input-nombre-reparto" readonly
                                                value="{{ old('reparto_nombre.'.$idx, $reparto['nombre'] ?? '') }}">
                                        </td>
                                        <td>
                                            <input type="text" name="reparto_patente[]" class="form-control form-control-sm input-patente-reparto"
                                                value="{{ old('reparto_patente.'.$idx, $reparto['patente'] ?? '') }}" maxlength="20">
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="reparto_cuit_chofer[]"
                                                    class="form-control input-cuit-reparto"
                                                    placeholder="XX-XXXXXXXX-X"
                                                    maxlength="13"
                                                    value="{{ old('reparto_cuit_chofer.'.$idx, \App\Support\Ventas\CuitFormatoValidacionSupport::formatear($reparto['cuit_chofer'] ?? '')) }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text cot-cuit-loading d-none" title="Consultando padr&oacute;n">
                                                        <i class="fa fa-spinner fa-spin"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <small class="text-danger cot-cuit-error d-none">CUIT inv&aacute;lida</small>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm input-titular-cuit" readonly
                                                value="" title="Se completa al validar la CUIT en padr&oacute;n ARCA">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-reparto" title="Quitar fila">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-agregar-reparto">
                        <i class="fa fa-plus"></i> Agregar reparto
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm ml-1 btn-limpiar-sesion-cot"
                        title="Quita repartos y remitos consultados. Conserva la fecha para pedir otro reparto.">
                        <i class="fa fa-eraser"></i> Limpiar sesi&oacute;n
                    </button>

                    @if ($consultado)
                        <hr>
                        <h5 class="mt-3">Remitos facturados</h5>
                        @if (count($remitos) === 0)
                            <div class="alert alert-warning mb-0">No se encontraron remitos Anita ni anitaERP para la fecha y repartos indicados.</div>
                        @else
                            <p class="mb-2">
                                <span class="badge badge-success">{{ $cantidadRemitosPendientes }} pendientes listos para presentar</span>
                                @if (($cantidadRemitosBloqueados ?? 0) > 0)
                                    <span class="badge badge-warning">{{ $cantidadRemitosBloqueados }} sin importe</span>
                                @endif
                                @if ($cantidadRemitosEmitidos > 0)
                                    <span class="badge badge-secondary">{{ $cantidadRemitosEmitidos }} ya emitidos (no se vuelven a enviar)</span>
                                @endif
                            </p>
                            @if (($cantidadRemitosBloqueados ?? 0) > 0)
                                <div class="alert alert-warning">
                                    Hay {{ $cantidadRemitosBloqueados }} remito(s) sin importe usable
                                    (ni factura Anita/ERP ni neto/seguro/líneas del remito).
                                    Esos remitos no se presentan: el COT no sale con $1 de relleno.
                                </div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="tabla-remitos-cot">
                                    <thead style="background-color:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" id="check-todos-remitos"
                                                    {{ $cantidadRemitosPendientes > 0 ? 'checked' : 'disabled' }}>
                                            </th>
                                            <th>N&deg; remito</th>
                                            <th>Fecha factura</th>
                                            <th>Cliente</th>
                                            <th>Reparto</th>
                                            <th class="text-right">Kilos</th>
                                            <th class="text-right">Importe</th>
                                            <th>Factura</th>
                                            <th>Estado COT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($remitos as $remito)
                                            @php $remitoImporteOk = ! empty($remito['importe_ok']); @endphp
                                            <tr class="{{ !empty($remito['ya_enviado']) ? 'table-secondary' : (! $remitoImporteOk ? 'table-warning' : '') }}">
                                                <td class="text-center">
                                                    @if (empty($remito['ya_enviado']) && $remitoImporteOk)
                                                        <input type="checkbox" name="remitos_seleccionados[]"
                                                            class="check-remito-cot-pendiente"
                                                            value="{{ $remito['clave'] }}"
                                                            data-importe-ok="1"
                                                            checked>
                                                    @elseif (empty($remito['ya_enviado']))
                                                        <input type="checkbox" name="remitos_seleccionados[]"
                                                            class="check-remito-cot-bloqueado"
                                                            value="{{ $remito['clave'] }}"
                                                            data-importe-ok="0"
                                                            disabled
                                                            title="{{ $remito['importe_motivo'] ?? 'Sin importe de factura' }}">
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ $remito['tipo'] ?? 'REM' }} {{ $remito['letra'] ?? 'R' }} {{ $remito['sucursal'] ?? '' }} {{ $remito['numero_remito'] }}</td>
                                                <td>{{ $remito['fecha_factura'] ?? '' }}</td>
                                                <td>{{ $remito['cliente_codigo'] }} {{ $remito['cliente_nombre'] }}</td>
                                                <td>{{ $remito['transporte_codigo'] }} {{ $remito['transporte_nombre'] }}</td>
                                                <td class="text-right">{{ number_format((float) $remito['kilos'], 2, ',', '.') }}</td>
                                                <td class="text-right @if (! $remitoImporteOk) table-warning font-weight-bold @endif">
                                                    {{ number_format((float) $remito['importe'], 2, ',', '.') }}
                                                    @if (! $remitoImporteOk)
                                                        <div class="small text-danger">
                                                            {{ $remito['importe_motivo'] ?? 'Sin neto gravado + exento de la factura' }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (! empty($remito['factura_codigo']))
                                                        {{ $remito['factura_codigo'] }}
                                                    @endif
                                                    <div class="small text-muted">
                                                        {{ $remito['importe_origen_etiqueta'] ?? ($remito['desde_factura'] ? 'Factura' : 'Remito directo') }}
                                                    </div>
                                                </td>
                                                <td>
                                                    @if (!empty($remito['ya_enviado']))
                                                        <span class="badge badge-secondary">Ya emitido</span>
                                                        @if (!empty($remito['cot_previo']))
                                                            <div class="small">COT {{ $remito['cot_previo'] }}</div>
                                                        @endif
                                                        @if (!empty($remito['sesion_previa_id']))
                                                            <div class="small text-muted">Sesi&oacute;n #{{ $remito['sesion_previa_id'] }}</div>
                                                        @endif
                                                    @else
                                                        <span class="badge badge-success">Pendiente</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if (!empty($resultadoProceso['resultados']))
                            <h5 class="mt-4">Resultado del env&iacute;o</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead style="background-color:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Remito</th>
                                            <th>Cliente</th>
                                            <th>Reparto</th>
                                            <th>Procesado</th>
                                            <th>N&deg; &uacute;nico</th>
                                            <th>COT</th>
                                            <th>Error</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($resultadoProceso['resultados'] as $res)
                                            <tr>
                                                <td>{{ $res['numero_remito'] ?? '' }}</td>
                                                <td>{{ $res['cliente_nombre'] ?? '' }}</td>
                                                <td>{{ $res['transporte_codigo'] ?? '' }}</td>
                                                <td>{{ $res['procesado'] ?? '' }}</td>
                                                <td>{{ $res['nro_unico'] ?? '' }}</td>
                                                <td>{{ $res['cot'] ?? '' }}</td>
                                                <td class="small text-danger">{{ $res['error'] ?? '' }}</td>
                                                <td class="text-nowrap">
                                                    @if (! empty($res['cot']) && ! empty($resultadoProceso['sesion_id']) && ! empty($res['id']))
                                                        <a href="{{ route('sesion_impresion_cot', ['id' => $resultadoProceso['sesion_id'], 'remito_envio_id' => $res['id'], 'auto' => 1]) }}"
                                                            class="btn btn-outline-success btn-sm" title="Imprimir esta constancia">
                                                            <i class="fa fa-print"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-info" id="btn-consultar-remitos">
                        <i class="fa fa-search"></i> Consultar remitos
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-limpiar-sesion-cot"
                        title="Quita repartos y remitos consultados. Conserva la fecha para pedir otro reparto.">
                        <i class="fa fa-eraser"></i> Limpiar sesi&oacute;n
                    </button>
                    @if ($consultado && ($cantidadRemitosPendientes ?? 0) > 0)
                        <button type="submit" class="btn btn-success" id="btn-procesar-cot">
                            <i class="fa fa-paper-plane"></i> Procesar env&iacute;o ARBA
                        </button>
                    @endif
                    <input type="hidden" name="procesar" id="input-procesar" value="">
                    <div class="form-check form-check-inline mt-2 mt-md-0 ml-md-3 align-middle">
                        <input type="hidden" name="imprimir_al_procesar" value="0">
                        <input type="checkbox" name="imprimir_al_procesar" id="imprimir_al_procesar"
                            class="form-check-input" value="1"
                            {{ ! empty($imprimirAlProcesar) ? 'checked' : '' }}>
                        <label class="form-check-label" for="imprimir_al_procesar">
                            Imprimir COT al procesar
                        </label>
                    </div>
                    <span class="small text-muted align-middle">
                        @if (! empty($impresoraUsuario['nombre']))
                            Impresora:
                            <strong>{{ $impresoraUsuario['nombre'] }}</strong>
                            @if (($impresoraUsuario['ubicacion'] ?? '') !== '')
                                ({{ $impresoraUsuario['ubicacion'] }})
                            @endif
                        @else
                            Sin impresora asignada
                        @endif
                    </span>
                    @include('includes.ventas.link_mi_impresora', [
                        'claseBtnMiImpresora' => 'btn btn-outline-secondary btn-sm ml-2',
                    ])
                </div>
            </form>
        </div>

        @include('ventas.cot_electronico.partials.sesiones_envio')
        @include('ventas.cot_electronico.partials.sesion_detalle')
    </div>
</div>

@include('includes.ventas.modalconsultatransporte')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/cot_electronico/proceso.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cot_electronico/proceso.js')) }}"></script>
@endsection
