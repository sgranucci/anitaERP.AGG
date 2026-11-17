@php
    use App\Support\Contable\MayorFuenteConsultaSupport;
    $erpHabilitado = (bool) ($erp_habilitado ?? config('contable.mayor_concepto.fuente_erp_habilitada', false));
    $fuenteMayor = MayorFuenteConsultaSupport::normalizarModo($filtros['fuente_mayor'] ?? null);
    if (! $erpHabilitado) {
        $fuenteMayor = MayorFuenteConsultaSupport::MODO_ANITA;
    }
    $cuentasMeta = $cuentas_como_debajo_limite_meta ?? [];
    $cuentas = collect($cuentasMeta)->pluck('codigo')->filter()->values()->all();
    if ($cuentas === []) {
        $cuentas = \App\Support\Contable\MayorConceptoListadoFiltros::cuentasComoDebajoLimite($filtros ?? []);
    }
    $textoCuentas = implode("\n", $cuentas);
    $limite = (int) config('contable.mayor_concepto.limite_caja_banco', 112010008);
    $idPrefix = $id_prefix ?? 'mco';
@endphp
@if ($erpHabilitado)
    <div class="form-group row mb-2" id="{{ $idPrefix }}-panel-cuentas-debajo-limite-wrap"
         style="{{ $fuenteMayor === MayorFuenteConsultaSupport::MODO_ERP ? '' : 'display:none;' }}">
        <label class="col-lg-2 control-label">Reglas ERP</label>
        <div class="col-lg-9">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                data-toggle="collapse"
                data-target="#{{ $idPrefix }}-panel-cuentas-debajo-limite"
                aria-expanded="{{ $cuentas !== [] ? 'true' : 'false' }}"
                id="{{ $idPrefix }}-btn-cuentas-debajo-limite">
                <i class="fa {{ $cuentas !== [] ? 'fa-chevron-down' : 'fa-chevron-right' }}"></i>
                Cuentas como debajo del límite
                <span class="badge badge-secondary ml-1" id="{{ $idPrefix }}-badge-cuentas-debajo-limite"
                    @if ($cuentas === []) style="display:none;" @endif>{{ count($cuentas) }}</span>
            </button>
            <div class="collapse {{ $cuentas !== [] ? 'show' : '' }} mt-2"
                 id="{{ $idPrefix }}-panel-cuentas-debajo-limite">
                <div class="border rounded p-2 bg-light">
                    <p class="small text-muted mb-2">
                        Cuentas fuera del límite ({{ number_format($limite, 0, ',', '.') }})
                        que se tratan como patas de caja/banco. Solo motor ERP; se recuerda por usuario.
                    </p>

                    <input type="hidden" name="cuentas_como_debajo_limite"
                        id="{{ $idPrefix }}-cuentas-como-debajo-limite"
                        value="{{ $textoCuentas }}">

                    <div class="mco-cuenta-debajo-campo mb-2" id="{{ $idPrefix }}-cuenta-debajo-campo" data-campo="puntual">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control codigocuentacontable"
                                id="{{ $idPrefix }}-cuenta-debajo-codigo" value=""
                                placeholder="111010-001"
                                title="Código de cuenta. Al escribir se abre la consulta. F1 también."
                                autocomplete="off" style="max-width: 125px;">
                            <input type="text" class="form-control nombrecuentacontable"
                                id="{{ $idPrefix }}-cuenta-debajo-nombre" value=""
                                placeholder="Nombre de la cuenta" readonly>
                            <div class="input-group-append">
                                <button type="button" title="Consultar cuentas (F1)"
                                    class="btn btn-outline-secondary consultacuentacontable">
                                    <i class="fa fa-search"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary"
                                    id="{{ $idPrefix }}-btn-agregar-cuenta-debajo"
                                    title="Agregar cuenta a la lista">
                                    <i class="fa fa-plus"></i> Agregar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-1" style="max-height: 220px; overflow-y: auto;">
                        <table class="table table-sm table-bordered mb-0" id="{{ $idPrefix }}-tabla-cuentas-debajo-limite">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width: 130px;">Código</th>
                                    <th>Cuenta contable</th>
                                    <th style="width: 70px;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="{{ $idPrefix }}-tbody-cuentas-debajo-limite">
                                @foreach ($cuentasMeta as $cta)
                                    <tr data-codigo="{{ $cta['codigo'] ?? '' }}">
                                        <td>{{ $cta['codigo_fmt'] ?? ($cta['codigo'] ?? '') }}</td>
                                        <td>{{ $cta['nombre'] ?? '' }}</td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-outline-danger btn-xs mco-btn-quitar-cuenta-debajo"
                                                title="Quitar">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0" id="{{ $idPrefix }}-aviso-sin-cuentas-debajo"
                        @if ($cuentas !== []) style="display:none;" @endif>
                        Sin cuentas. Buscá con el código / lupa (F1) y agregá a la lista.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif
