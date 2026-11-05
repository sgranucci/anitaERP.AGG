@php
    use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;
    $lbl = $lbl ?? 'col-lg-4 control-label text-right pr-2';
    $inp = $inp ?? 'col-lg-7';
    $modo = old('modo_pos', $data->modo_pos ?? GastronomiaCrownPosModos::RESTO);
    $politica = old('politica_comanda', $data->politica_comanda ?? GastronomiaCrownPosModos::POLITICA_FACTURA_Y_COMANDA);
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-desktop"></i> Puesto POS Crown</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Perfil del puesto del POS Crown: modo, comandas, sala, listas, salidas y facturación.
        </p>
        <div class="form-group row">
            <label for="modo_pos" class="{{ $lbl }} requerido">Modo de uso</label>
            <div class="{{ $inp }}">
                <select name="modo_pos" id="modo_pos" class="form-control" required>
                    @foreach (GastronomiaCrownPosModos::modos() as $valor => $etiqueta)
                        <option value="{{ $valor }}" {{ $modo === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="politica_comanda" class="{{ $lbl }} requerido">Política de comanda</label>
            <div class="{{ $inp }}">
                <select name="politica_comanda" id="politica_comanda" class="form-control" required>
                    @foreach (GastronomiaCrownPosModos::politicasComanda() as $valor => $etiqueta)
                        <option value="{{ $valor }}" {{ $politica === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label class="{{ $lbl }}">Opciones</label>
            <div class="{{ $inp }}">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="solo_marcha" value="0">
                    <input type="checkbox" class="custom-control-input" id="solo_marcha" name="solo_marcha" value="1"
                        {{ (int) old('solo_marcha', $data->solo_marcha ?? 0) === 1 ? 'checked' : '' }}>
                    <label class="custom-control-label" for="solo_marcha">Solo marcha / no factura (<code>-nf</code>)</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="habilita_hotel" value="0">
                    <input type="checkbox" class="custom-control-input" id="habilita_hotel" name="habilita_hotel" value="1"
                        {{ (int) old('habilita_hotel', $data->habilita_hotel ?? 0) === 1 ? 'checked' : '' }}>
                    <label class="custom-control-label" for="habilita_hotel">Cargo a habitación / hotel (<code>-h</code>)</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="habilita_canje_gst" value="0">
                    <input type="checkbox" class="custom-control-input" id="habilita_canje_gst" name="habilita_canje_gst" value="1"
                        {{ (int) old('habilita_canje_gst', $data->habilita_canje_gst ?? 1) === 1 ? 'checked' : '' }}>
                    <label class="custom-control-label" for="habilita_canje_gst">Canje GST / Wigos</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="pide_cubiertos" value="0">
                    <input type="checkbox" class="custom-control-input" id="pide_cubiertos" name="pide_cubiertos" value="1"
                        {{ (int) old('pide_cubiertos', $data->pide_cubiertos ?? 1) === 1 ? 'checked' : '' }}>
                    <label class="custom-control-label" for="pide_cubiertos">Pide cubiertos al facturar</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="pide_mozo_manual" value="0">
                    <input type="checkbox" class="custom-control-input" id="pide_mozo_manual" name="pide_mozo_manual" value="1"
                        {{ (int) old('pide_mozo_manual', $data->pide_mozo_manual ?? 0) === 1 ? 'checked' : '' }}>
                    <label class="custom-control-label" for="pide_mozo_manual">Pide mozo manual (<code>-mm</code>)</label>
                </div>
            </div>
        </div>
        <div class="form-group row">
            <label for="sala_id" class="{{ $lbl }}">Sala (filtro default)</label>
            <div class="{{ $inp }}">
                <select name="sala_id" id="sala_id" class="form-control">
                    <option value="">Todas / sin filtro</option>
                    @foreach (($sala_query ?? collect()) as $sala)
                        <option value="{{ $sala->id }}" {{ (int) old('sala_id', $data->sala_id ?? 0) === (int) $sala->id ? 'selected' : '' }}>
                            {{ $sala->codigo }} — {{ $sala->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="listaprecio_id" class="{{ $lbl }} requerido">Lista de precios</label>
            <div class="{{ $inp }}">
                <select name="listaprecio_id" id="listaprecio_id" class="form-control" required>
                    <option value="">Seleccione…</option>
                    @foreach ($listaprecio_query as $lp)
                        <option value="{{ $lp->id }}" {{ (int) old('listaprecio_id', $data->listaprecio_id ?? 0) === (int) $lp->id ? 'selected' : '' }}>
                            {{ $lp->nombre }} @if ($lp->codigo)({{ $lp->codigo }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="listaprecio_hotel_id" class="{{ $lbl }}">Lista precios hotel</label>
            <div class="{{ $inp }}">
                <select name="listaprecio_hotel_id" id="listaprecio_hotel_id" class="form-control">
                    <option value="">Misma que POS</option>
                    @foreach ($listaprecio_query as $lp)
                        <option value="{{ $lp->id }}" {{ (int) old('listaprecio_hotel_id', $data->listaprecio_hotel_id ?? 0) === (int) $lp->id ? 'selected' : '' }}>
                            {{ $lp->nombre }} @if ($lp->codigo)({{ $lp->codigo }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        @php
            $salidaElegida = function (string $campo) use ($data, $salida_query) {
                $id = (int) old($campo, $data->{$campo} ?? 0);
                if ($id <= 0) {
                    return null;
                }

                return ($salida_query ?? collect())->firstWhere('id', $id);
            };
            $descSalida = function ($salida): string {
                if ($salida === null) {
                    return '';
                }
                $ubicacion = trim((string) ($salida->ubicacion ?? ''));

                return $ubicacion !== '' ? $ubicacion : trim((string) ($salida->usos_etiqueta ?? ''));
            };
        @endphp
        @include('configuracion.partials.campo_consulta_salida', [
            'prefix' => 'comanda',
            'label' => 'Salida de comandas',
            'inputName' => 'salida_comanda_id',
            'inputId' => 'salida_comanda_id',
            'salidaId' => old('salida_comanda_id', $data->salida_comanda_id ?? ''),
            'nombre' => optional($salidaElegida('salida_comanda_id'))->nombre ?? '',
            'descripcion' => $descSalida($salidaElegida('salida_comanda_id')),
            'required' => true,
            'col_label' => $lbl,
            'col_input' => $inp,
        ])
        @include('configuracion.partials.campo_consulta_salida', [
            'prefix' => 'factura',
            'label' => 'Salida de facturas',
            'inputName' => 'salida_factura_id',
            'inputId' => 'salida_factura_id',
            'salidaId' => old('salida_factura_id', $data->salida_factura_id ?? ''),
            'nombre' => optional($salidaElegida('salida_factura_id'))->nombre ?? '',
            'descripcion' => $descSalida($salidaElegida('salida_factura_id')),
            'required' => true,
            'col_label' => $lbl,
            'col_input' => $inp,
        ])
        @include('configuracion.partials.campo_consulta_salida', [
            'prefix' => 'comanda_b',
            'label' => 'Salida comanda B',
            'inputName' => 'salida_comanda_b_id',
            'inputId' => 'salida_comanda_b_id',
            'salidaId' => old('salida_comanda_b_id', $data->salida_comanda_b_id ?? ''),
            'nombre' => optional($salidaElegida('salida_comanda_b_id'))->nombre ?? '',
            'descripcion' => $descSalida($salidaElegida('salida_comanda_b_id')),
            'required' => false,
            'col_label' => $lbl,
            'col_input' => $inp,
        ])
        @include('configuracion.partials.campo_consulta_salida', [
            'prefix' => 'comanda_k',
            'label' => 'Salida comanda K',
            'inputName' => 'salida_comanda_k_id',
            'inputId' => 'salida_comanda_k_id',
            'salidaId' => old('salida_comanda_k_id', $data->salida_comanda_k_id ?? ''),
            'nombre' => optional($salidaElegida('salida_comanda_k_id'))->nombre ?? '',
            'descripcion' => $descSalida($salidaElegida('salida_comanda_k_id')),
            'required' => false,
            'col_label' => $lbl,
            'col_input' => $inp,
        ])
        @include('configuracion.partials.campo_consulta_salida', [
            'prefix' => 'hotel',
            'label' => 'Salida cargo hotel',
            'inputName' => 'salida_hotel_id',
            'inputId' => 'salida_hotel_id',
            'salidaId' => old('salida_hotel_id', $data->salida_hotel_id ?? ''),
            'nombre' => optional($salidaElegida('salida_hotel_id'))->nombre ?? '',
            'descripcion' => $descSalida($salidaElegida('salida_hotel_id')),
            'required' => false,
            'col_label' => $lbl,
            'col_input' => $inp,
            'ayuda' => 'Impresión del cargo a habitación. La comanda principal es «Salida de comandas».',
        ])
        <div class="form-group row">
            <label for="puntoventa_cae_id" class="{{ $lbl }} requerido">Punto de venta electrónico</label>
            <div class="{{ $inp }}">
                <select name="puntoventa_cae_id" id="puntoventa_cae_id" class="form-control" required>
                    <option value="">Seleccione…</option>
                    @foreach ($puntoventa_cae_query as $pv)
                        <option value="{{ $pv->id }}" {{ (int) old('puntoventa_cae_id', $data->puntoventa_cae_id ?? 0) === (int) $pv->id ? 'selected' : '' }}>
                            {{ $pv->codigo }} — {{ $pv->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="puntoventa_caea_id" class="{{ $lbl }} requerido">Punto de venta CAEA</label>
            <div class="{{ $inp }}">
                <select name="puntoventa_caea_id" id="puntoventa_caea_id" class="form-control" required>
                    <option value="">Seleccione…</option>
                    @foreach ($puntoventa_caea_query as $pv)
                        <option value="{{ $pv->id }}" {{ (int) old('puntoventa_caea_id', $data->puntoventa_caea_id ?? 0) === (int) $pv->id ? 'selected' : '' }}>
                            {{ $pv->codigo }} — {{ $pv->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipotransaccion_id" class="{{ $lbl }} requerido">Tipo de factura</label>
            <div class="{{ $inp }}">
                <select name="tipotransaccion_id" id="tipotransaccion_id" class="form-control" required>
                    <option value="">Seleccione…</option>
                    @foreach ($tipotransaccion_query as $tt)
                        <option value="{{ $tt->id }}" {{ (int) old('tipotransaccion_id', $data->tipotransaccion_id ?? 0) === (int) $tt->id ? 'selected' : '' }}>
                            {{ $tt->abreviatura }} — {{ $tt->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipotransaccion_nota_credito_id" class="{{ $lbl }} requerido">Tipo de nota de crédito</label>
            <div class="{{ $inp }}">
                <select name="tipotransaccion_nota_credito_id" id="tipotransaccion_nota_credito_id" class="form-control" required>
                    <option value="">Seleccione…</option>
                    @foreach ($tipotransaccion_nota_credito_query as $tt)
                        <option value="{{ $tt->id }}" {{ (int) old('tipotransaccion_nota_credito_id', $data->tipotransaccion_nota_credito_id ?? 0) === (int) $tt->id ? 'selected' : '' }}>
                            {{ $tt->abreviatura }} — {{ $tt->nombre }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Lo usa el botón Revertir de comprobantes del día. El tipo tiene que tener signo Resta.</small>
            </div>
        </div>

        @php
            $planoQuery = $plano_query ?? collect();
            $planosAsignados = $planos_asignados ?? [];
            $planosOld = old('plano_ids');
            $ordenOld = old('plano_orden', []);
        @endphp
        <div class="form-group row">
            <label class="{{ $lbl }}">Planos de mesas</label>
            <div class="{{ $inp }}">
                <div class="card card-outline card-secondary mb-2">
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0" id="tabla-planos-pc">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:3rem;"></th>
                                    <th>Plano</th>
                                    <th>Sala</th>
                                    <th style="width:6rem;">Orden</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($planoQuery as $plano)
                                    @php
                                        $marcado = $planosOld !== null
                                            ? in_array((string) $plano->id, (array) $planosOld, true)
                                            : array_key_exists((int) $plano->id, $planosAsignados);
                                        $orden = $ordenOld[$plano->id] ?? ($planosAsignados[(int) $plano->id] ?? $plano->orden ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" value="{{ $plano->id }}"
                                                       id="plano_id_{{ $plano->id }}" name="plano_ids[]"
                                                       {{ $marcado ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="plano_id_{{ $plano->id }}">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td>
                                            {{ $plano->codigo ? $plano->codigo.' — ' : '' }}{{ $plano->nombre }}
                                            @unless ($plano->activo)
                                                <span class="badge badge-secondary">inactivo</span>
                                            @endunless
                                        </td>
                                        <td>{{ optional($plano->sala)->codigo }} {{ optional($plano->sala)->nombre }}</td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm"
                                                   name="plano_orden[{{ $plano->id }}]" min="0" max="999"
                                                   value="{{ (int) $orden }}">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-muted text-center py-2">
                                            No hay planos cargados para esta empresa.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <small class="form-text text-muted">
                    Si la PC tiene planos asignados, el POS muestra el mapa de mesas en lugar de la lista de botones.
                    <a href="{{ route('consultar_plano_mesa_gastronomia_crown') }}" target="_blank" rel="noopener">Administrar planos</a>.
                </small>
            </div>
        </div>
    </div>
</div>
