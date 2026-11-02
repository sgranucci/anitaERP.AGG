@php
    $roles = \App\Support\Ventas\TransferenciaCrown\TransferenciaCrownCuentaClaves::ROLES;
    $sinCentro = collect($imputacionesCrown)->filter(fn ($f) => (int) ($f['centrocosto']['id'] ?? 0) <= 0)->count();
    $sucursalEdicion = is_array($pvEdicion) ? (int) ($pvEdicion['sucursal'] ?? 0) : 0;
@endphp

<div class="card card-outline card-info mb-0" id="transferencias-crown-card">
    <div class="card-header py-2 d-flex align-items-center flex-wrap" style="gap:.5rem;">
        <h3 class="card-title mb-0" style="font-size:1rem;">
            Transferencias Crown
            <span class="badge badge-info ml-1">{{ count($imputacionesCrown) }} PV</span>
            @if ($sinCentro > 0)
                <span class="badge badge-warning">{{ $sinCentro }} sin centro</span>
            @endif
        </h3>
        <div class="ml-auto" style="min-width:12rem;max-width:18rem;">
            <input type="text" id="filtro-pv-crown" class="form-control form-control-sm" placeholder="Buscar PV o centro…" autocomplete="off">
        </div>
    </div>
    <div class="card-body p-2">
        <p class="text-muted small mb-2 mb-md-2 px-1">
            Centro de costo por punto de venta (precargado desde Anita). Se aplica solo a cuentas que manejan CC.
            Gastronomía 5xxx/6xxx · ARCA/PXSOL/Havanna 1xxx/2xxx.
        </p>

        <div class="table-responsive" id="tabla-pv-crown-scroll" style="max-height:32rem;">
            <table class="table table-sm table-bordered table-hover mb-0" id="tabla-pv-crown" style="font-size:.82rem;">
                <thead style="position:sticky;top:0;z-index:1;">
                    <tr style="background:#85C1E9;color:#17202A;">
                        <th style="min-width:9rem;">Punto de venta</th>
                        <th style="min-width:7rem;">Centro</th>
                        <th class="text-center">Gastro venta</th>
                        <th class="text-center">Gastro efe.</th>
                        <th class="text-center">ARCA venta</th>
                        <th class="text-center">ARCA efe.</th>
                        <th style="width:3.5rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($imputacionesCrown as $filaPv)
                        @php
                            $cc = $filaPv['centrocosto'] ?? ['id' => 0, 'codigo' => '', 'nombre' => ''];
                            $cGastroVenta = $filaPv['cuentas']['gastro_venta']['codigo'] ?? '';
                            $cGastroEfe = $filaPv['cuentas']['gastro_efectivo']['codigo'] ?? '';
                            $cArcaVenta = $filaPv['cuentas']['arca_venta']['codigo'] ?? '';
                            $cArcaEfe = $filaPv['cuentas']['arca_efectivo']['codigo'] ?? '';
                            $sucursalFila = (int) ($filaPv['sucursal'] ?? 0);
                            $editandoEsta = $sucursalEdicion > 0 && $sucursalEdicion === $sucursalFila;
                            $busca = strtolower(
                                ($filaPv['codigo'] ?? '').' '.($filaPv['nombre'] ?? '').' '.($filaPv['sucursal'] ?? '').' '.
                                ($cc['codigo'] ?? '').' '.($cc['nombre'] ?? '')
                            );
                        @endphp
                        <tr id="pv-crown-{{ $sucursalFila }}"
                            data-busca="{{ $busca }}"
                            @if ((int) ($cc['id'] ?? 0) <= 0) class="table-warning" @endif
                            @if ($editandoEsta) style="outline:2px solid #3498db;outline-offset:-2px;" @endif>
                            <td class="text-nowrap py-1">
                                <strong>{{ $filaPv['codigo'] }}</strong>
                                <div class="text-muted text-truncate" style="max-width:11rem;" title="{{ $filaPv['nombre'] }}">{{ $filaPv['nombre'] }}</div>
                            </td>
                            <td class="py-1" title="{{ $cc['nombre'] ?? '' }}">
                                @if ((int) ($cc['id'] ?? 0) > 0)
                                    <span class="badge badge-light border">{{ $cc['codigo'] }}</span>
                                    <div class="text-muted text-truncate" style="max-width:8rem;">{{ $cc['nombre'] }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center text-nowrap py-1" title="{{ $filaPv['cuentas']['gastro_venta']['nombre'] ?? '' }}">{{ $cGastroVenta ?: '—' }}</td>
                            <td class="text-center text-nowrap py-1" title="{{ $filaPv['cuentas']['gastro_efectivo']['nombre'] ?? '' }}">{{ $cGastroEfe ?: '—' }}</td>
                            <td class="text-center text-nowrap py-1" title="{{ $filaPv['cuentas']['arca_venta']['nombre'] ?? '' }}">{{ $cArcaVenta ?: '—' }}</td>
                            <td class="text-center text-nowrap py-1" title="{{ $filaPv['cuentas']['arca_efectivo']['nombre'] ?? '' }}">{{ $cArcaEfe ?: '—' }}</td>
                            <td class="text-center py-1">
                                <a class="btn btn-outline-primary btn-xs px-1 py-0 js-editar-pv-crown"
                                   href="{{ route('cuentas_automaticas_contables', ['empresa_id' => $empresa_id, 'pv' => $sucursalFila]) }}#pv-crown-{{ $sucursalFila }}"
                                   title="Editar cuentas y centro">
                                    <i class="fa fa-pencil"></i>
                                </a>
                            </td>
                        </tr>
                        @if ($editandoEsta && is_array($pvEdicion))
                            @php
                                $ccEdit = $pvEdicion['centrocosto'] ?? ['id' => 0, 'codigo' => '', 'nombre' => ''];
                            @endphp
                            <tr class="pv-crown-editor" data-busca="{{ $busca }}" id="pv-crown-editor-{{ $sucursalFila }}">
                                <td colspan="7" class="p-2" style="background:#f8fbff;">
                                    <form action="{{ route('actualizar_cuentas_automaticas_contables') }}" method="POST" class="mb-0" autocomplete="off" data-enter-campos="1">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="solo_transferencia_pv" value="1">
                                        <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                        <input type="hidden" name="pv_sucursal" value="{{ $sucursalFila }}">
                                        <div class="d-flex align-items-center flex-wrap mb-2" style="gap:.5rem;">
                                            <strong class="mb-0">{{ $pvEdicion['codigo'] }}</strong>
                                            <span class="text-muted small text-truncate" style="max-width:18rem;">{{ $pvEdicion['nombre'] }}</span>
                                            <div class="ml-auto">
                                                <button type="submit" class="btn btn-primary btn-sm py-0 px-2"><i class="fa fa-save"></i> Guardar</button>
                                                <a href="{{ route('cuentas_automaticas_contables', ['empresa_id' => $empresa_id]) }}#pv-crown-{{ $sucursalFila }}" class="btn btn-outline-secondary btn-sm py-0 px-2">Cerrar</a>
                                            </div>
                                        </div>

                                        @include('contable.partials.campo_consulta_centrocosto', [
                                            'prefix' => 'transf_crown_pv',
                                            'layout' => 'form_row',
                                            'label' => 'Centro de costo',
                                            'inputName' => 'centrocosto_id',
                                            'centrocostoId' => (int) ($ccEdit['id'] ?? 0) ?: '',
                                            'codigo' => $ccEdit['codigo'] ?? '',
                                            'descripcion' => $ccEdit['nombre'] ?? '',
                                            'required' => false,
                                            'col_label' => 'col-lg-2 col-form-label control-label text-right pr-2',
                                            'col_input' => 'col-lg-7',
                                            'ayuda' => 'Vacío = sin centro. Solo se imputa si la cuenta maneja CC.',
                                        ])

                                        <div class="row mt-1">
                                            @foreach ($roles as $perfil => $listaRoles)
                                                <div class="col-md-6">
                                                    <div class="small font-weight-bold text-muted mb-1">
                                                        {{ $perfil === 'arca' ? 'ARCA / PXSOL / Havanna' : 'Gastronomía' }}
                                                    </div>
                                                    @foreach ($listaRoles as $rol => $meta)
                                                        @php
                                                            $input = $perfil.'_'.$rol;
                                                            $cuenta = $pvEdicion['cuentas'][$input] ?? ['id' => 0, 'codigo' => '', 'nombre' => ''];
                                                        @endphp
                                                        <div class="form-group row mb-1 tm-cuentacontable-campo">
                                                            <label class="col-4 col-form-label col-form-label-sm text-right pr-1">{{ $meta['etiqueta'] }}</label>
                                                            <div class="col-3 pl-0 pr-1">
                                                                <input type="hidden" class="cuentacontable_id" name="pv_cuentas[{{ $input }}]" value="{{ (int) ($cuenta['id'] ?? 0) ?: '' }}">
                                                                <div class="input-group input-group-sm">
                                                                    <input type="text" class="codigocuentacontable form-control" value="{{ $cuenta['codigo'] ?? '' }}" autocomplete="off" placeholder="Cód.">
                                                                    <div class="input-group-append">
                                                                        <button type="button" title="Consulta" class="btn btn-outline-secondary consultacuentacontable px-1">
                                                                            <i class="fa fa-search"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-5 pl-0">
                                                                <input type="text" class="nombrecuentacontable form-control form-control-sm" value="{{ $cuenta['nombre'] ?? '' }}" readonly tabindex="-1">
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="small text-muted mt-1 px-1">
            Tarjeta / cheque / débito se editan al abrir el PV. La grilla muestra las cuentas que usa el asiento.
        </div>
    </div>
</div>
