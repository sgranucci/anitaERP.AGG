@php
    use App\Support\Contable\CuentacontableArbolSupport;
    $prefill = $prefill ?? [];
    $empresaIdForm = old('empresa_id', $data->empresa_id ?? ($empresaPrefillId ?? ($prefill['empresa_id'] ?? null)));
    $tipos = $tiposCuenta ?? CuentacontableArbolSupport::etiquetasTipo();
    $tipoActual = (string) old('tipocuenta', $data->tipocuenta ?? ($prefill['tipocuenta'] ?? ''));
    $nivelActual = old('nivel', $data->nivel ?? ($prefill['nivel'] ?? ''));
    $rubroActual = (int) old('rubrocontable_id', $data->rubrocontable_id ?? ($prefill['rubrocontable_id'] ?? 0));
    $manejaccosto = old('manejaccosto', $data->manejaccosto ?? 'N');
    $soloLectura = ! empty($soloConsulta);
@endphp
@if (! empty($data) && empty($soloConsulta))
    <div class="alert alert-light border mb-3">
        Nivel, “colgar de” y la vista del bloque se editan en el árbol (clic en la cuenta).
        Esta ficha es para código Anita, monetaria, centro de costo y diferencia de cambio.
        <a href="{{ route('cuentacontable', array_merge($filtrosQuery ?? [], ['vista' => 'arbol', 'cuenta' => $data->id])) }}">Abrir en el árbol</a>
    </div>
@endif
@if (! empty($prefill['padre_nombre']))
    <div class="alert alert-light border mb-3">
        Alta bajo <strong>{{ $prefill['padre_codigo'] }} {{ $prefill['padre_nombre'] }}</strong>.
        Nivel y rubro se precompletaron; el código lo define usted. Si es un título, el sistema crea la totalizadora gemela para Anita.
    </div>
@endif
<div class="row">
    <div class="col-lg-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresaIdForm,
            'col_input' => 'col-lg-8',
            'solo_lectura' => $soloLectura || ! empty($data),
        ])
        <div class="form-group row">
            <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100"
                       value="{{ old('nombre', $data->nombre ?? '') }}" required @if($soloLectura) readonly @endif>
            </div>
        </div>
        <div class="form-group row">
            <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
            <div class="col-lg-5">
                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="50"
                       value="{{ old('codigo', $data->codigo ?? '') }}" required @if($soloLectura) readonly @endif
                       placeholder="111010001">
                <small class="form-text text-muted">9 dígitos. Se muestra como 111010-001 en el árbol.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="nivel" class="col-lg-3 col-form-label requerido">Nivel</label>
            <div class="col-lg-3">
                <select name="nivel" id="nivel" class="form-control" required @if($soloLectura) disabled @endif>
                    <option value="">Nivel…</option>
                    @for ($n = 1; $n <= 5; $n++)
                        <option value="{{ $n }}" @selected((string) $nivelActual === (string) $n)>{{ $n }}</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="rubrocontable_id" class="col-lg-3 col-form-label requerido">Naturaleza</label>
            <div class="col-lg-8">
                <select name="rubrocontable_id" id="rubrocontable_id" class="form-control" required @if($soloLectura) disabled @endif>
                    <option value="">Seleccione…</option>
                    @foreach($rubrocontable_query as $rubrocontable)
                        <option value="{{ $rubrocontable->id }}" @selected($rubroActual === (int) $rubrocontable->id)>
                            {{ $rubrocontable->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipocuenta" class="col-lg-3 col-form-label requerido">Tipo de cuenta</label>
            <div class="col-lg-5">
                <select name="tipocuenta" id="tipocuenta" class="form-control" required @if($soloLectura) disabled @endif>
                    <option value="">Seleccione…</option>
                    @foreach($tipos as $valor => $label)
                        <option value="{{ $valor }}" @selected($tipoActual === (string) $valor)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Título = grupo de suma. Totalizadora = gemela Anita (…9999).</small>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="monetaria" class="col-lg-3 col-form-label requerido">Cuenta monetaria</label>
            <div class="col-lg-5">
                <select name="monetaria" id="monetaria" class="form-control" required @if($soloLectura) disabled @endif>
                    <option value="">Seleccione…</option>
                    <option value="S" @selected(old('monetaria', $data->monetaria ?? '') === 'S')>Monetaria</option>
                    <option value="N" @selected(old('monetaria', $data->monetaria ?? 'N') === 'N')>No monetaria</option>
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="manejaccosto" class="col-lg-3 col-form-label requerido">Centros de costo</label>
            <div class="col-lg-5">
                <select id="manejaccosto" name="manejaccosto" class="form-control" required @if($soloLectura) disabled @endif>
                    <option value="">Seleccione…</option>
                    <option value="S" @selected($manejaccosto === 'S')>Maneja c.costo</option>
                    <option value="N" @selected($manejaccosto === 'N')>No maneja c.costo</option>
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="ajustamonedaextranjera" class="col-lg-3 col-form-label requerido">Ajusta m/e</label>
            <div class="col-lg-7">
                <select id="ajustamonedaextranjera" name="ajustamonedaextranjera" class="form-control" required @if($soloLectura) disabled @endif>
                    <option value="">Seleccione…</option>
                    @foreach($ajustamonedaextranjera_enum as $item)
                        <option value="{{ $item['valor'] }}" @selected(old('ajustamonedaextranjera', $data->ajustamonedaextranjera ?? '') === $item['valor'])>
                            {{ $item['nombre'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="conceptogasto_id" class="col-lg-3 col-form-label{{ config('app.empresa') == 'AGG' ? ' requerido' : '' }}">Concepto cash flow</label>
            <div class="col-lg-8">
                <select name="conceptogasto_id" id="conceptogasto_id" class="form-control"
                        @if(config('app.empresa') == 'AGG') required @endif @if($soloLectura) disabled @endif>
                    <option value="">Seleccione…</option>
                    @foreach($conceptogasto_query as $conceptogasto)
                        <option value="{{ $conceptogasto->id }}" @selected(isset($data) && (int) $conceptogasto->id === (int) ($data->conceptogasto_id ?? 0))>
                            {{ $conceptogasto->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="cuentacontable_difcambio_id" class="col-lg-3 col-form-label">Dif. de cambio</label>
            <div class="col-lg-8">
                <select name="cuentacontable_difcambio_id" id="cuentacontable_difcambio_id" class="form-control" @if($soloLectura) disabled @endif>
                    <option value="">Ninguna</option>
                    @foreach($cuentacontable_query as $ctaDif)
                        <option value="{{ $ctaDif->id }}" @selected(isset($data) && (int) $ctaDif->id === (int) ($data->cuentacontable_difcambio_id ?? 0))>
                            {{ $ctaDif->codigo }} — {{ $ctaDif->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
<div class="card-body px-0" id="divcentrocosto" @if($manejaccosto !== 'S') hidden @endif>
    <h5 class="mb-2">Centros de costo válidos</h5>
    <table class="table table-sm" id="centrocosto-table">
        <thead>
            <tr>
                <th style="width: 50%;">Centro de costo</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-centrocosto-table">
        @if ($data->cuentacontable_centrocostos ?? '')
            @foreach (old('centrocostos', $data->cuentacontable_centrocostos->count() ? $data->cuentacontable_centrocostos : ['']) as $centrocosto)
                <tr class="item-centrocosto">
                    <td>
                        <select name="centrocosto_ids[]" class="form-control centrocosto" @if($soloLectura) disabled @endif>
                            <option value="">— Elija centro de costo —</option>
                            @foreach($centrocosto_query as $value)
                                <option value="{{ $value->id }}" @selected((int) $value->id === (int) old('centrocosto_id', $centrocosto->centrocosto_id ?? 0))>
                                    {{ $value->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        @if (! $soloLectura)
                            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_centrocosto tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    @include('contable.cuentacontable.template1')
    @if (! $soloLectura)
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_centrocosto" class="btn btn-outline-danger btn-sm float-right">+ Agregar renglón</button>
            </div>
        </div>
    @endif
</div>
