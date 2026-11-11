{{-- Modal alta provisoria de bienes de uso desde factura contabilizada --}}
@php
    $buModal = $bien_uso_modal ?? [];
    $buPrefill = $buModal['prefill'] ?? [];
    $buRenglones = $buModal['renglones_default'] ?? [];
    $buPuede = (bool) ($buModal['puede'] ?? false);
    $buExistentes = (int) ($buModal['bienes_existentes'] ?? 0);
    $tiposBien = \App\Models\Contable\BienUso::$enumTipoBien;
@endphp
@if ($buPuede && ($puede_cargar_bienes_uso ?? false))
<div class="modal fade" id="modal-bienes-uso-provisorios" tabindex="-1" role="dialog"
    aria-labelledby="modal-bienes-uso-provisorios-titulo" aria-hidden="true"
    data-url="{{ route('guardar_bienes_uso_provisorios_comprobante_proveedor', $data->id) }}"
    data-abrir="{{ ! empty($abrir_modal_bienes_uso) ? '1' : '0' }}"
    data-existentes="{{ $buExistentes }}">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="modal-bienes-uso-provisorios-titulo">
                    Cargar bienes de uso (provisorios)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2 text-muted small">
                    Se crean en estado <strong>Provisorio</strong>. Completar rubro, cuentas y pasar a Activo en el ABM.
                    @if ($buExistentes > 0)
                        Ya hay <strong>{{ $buExistentes }}</strong> bien(es) vinculado(s) a esta factura.
                    @endif
                </p>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="mb-0 small text-muted">Proveedor</label>
                        <div class="form-control-plaintext py-1">
                            {{ $buPrefill['proveedor_codigo'] ?? '' }}
                            — {{ $buPrefill['proveedor_nombre'] ?? '' }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="mb-0 small text-muted">Factura</label>
                        <div class="form-control-plaintext py-1">{{ $buPrefill['factura_label'] ?? '' }}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="mb-0 small text-muted">Fecha</label>
                        <div class="form-control-plaintext py-1">
                            {{ ! empty($buPrefill['fecha_factura']) ? \Carbon\Carbon::parse($buPrefill['fecha_factura'])->format('d/m/Y') : '' }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="mb-0 small text-muted">Empresa / neto sugerido</label>
                        <div class="form-control-plaintext py-1">
                            {{ $buPrefill['empresa_nombre'] ?? '' }}
                            — {{ number_format((float) ($buPrefill['valor_sugerido'] ?? 0), 2, ',', '.') }}
                            {{ $buPrefill['moneda_nombre'] ?? '' }}
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="tabla-bienes-uso-provisorios">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:32%;">Descripci&oacute;n</th>
                                <th style="width:12%;">Valor</th>
                                <th style="width:12%;">Tipo bien</th>
                                <th style="width:36%;">Centro de costo</th>
                                <th style="width:8%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-bienes-uso-provisorios">
                            @foreach ($buRenglones as $idx => $ren)
                                @include('compras.comprobante_proveedor.partials.renglon_bien_uso_provisorio', [
                                    'idx' => $idx,
                                    'ren' => $ren,
                                    'tiposBien' => $tiposBien,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-renglon-bien-uso">
                    <i class="fa fa-plus"></i> Agregar rengl&oacute;n
                </button>
                <div id="bienes-uso-provisorios-error" class="text-danger small mt-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Omitir</button>
                <button type="button" class="btn btn-primary" id="btn-guardar-bienes-uso-provisorios">
                    <i class="fa fa-save"></i> Guardar bienes
                </button>
            </div>
        </div>
    </div>
</div>

<template id="template-renglon-bien-uso-provisorio">
    @include('compras.comprobante_proveedor.partials.renglon_bien_uso_provisorio', [
        'idx' => '__IDX__',
        'ren' => [
            'descripcion' => $buPrefill['descripcion_default'] ?? '',
            'valor_origen' => '',
            'tipo_bien' => 'I',
            'centrocosto_id' => $buPrefill['centrocosto_id'] ?? '',
            'centrocosto_codigo' => $buPrefill['centrocosto_codigo'] ?? '',
            'centrocosto_nombre' => $buPrefill['centrocosto_nombre'] ?? '',
        ],
        'tiposBien' => $tiposBien,
    ])
</template>
@endif
