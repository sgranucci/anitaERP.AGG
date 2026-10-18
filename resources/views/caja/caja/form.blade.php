@php
    use App\Support\Configuracion\EntornoEmpresaSupport;
    $esCrown = EntornoEmpresaSupport::esCrown();
    $salasSelector = $sala_query ?? collect();
    $salaIdActual = old('sala_id', $data->sala_id ?? '');
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2">C&oacute;digo</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control"
            value="{{ old('codigo', $data->codigo ?? '') }}" maxlength="30"
            placeholder="N&uacute;mero Anita / interno">
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-4">
        <input type="text" name="nombre" id="nombre" class="form-control"
            value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255">
    </div>
</div>
@if ($esCrown)
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Sala</label>
    <div class="col-lg-6">
        <p class="text-muted small mb-2">Vac&iacute;o = sin sala (no filtra por casino).</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-caja-sala">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:4rem;" class="text-center">Elegir</th>
                        <th style="width:6rem;">C&oacute;digo</th>
                        <th>Sala</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center align-middle">
                            <input type="radio" name="sala_id" value="" class="caja-sala-radio"
                                @checked($salaIdActual === '' || $salaIdActual === null)>
                        </td>
                        <td class="align-middle text-muted" colspan="2">Sin sala</td>
                    </tr>
                    @forelse ($salasSelector as $sala)
                        <tr>
                            <td class="text-center align-middle">
                                <input type="radio" name="sala_id" value="{{ $sala->id }}" class="caja-sala-radio"
                                    @checked((string) $salaIdActual === (string) $sala->id)>
                            </td>
                            <td class="align-middle">{{ $sala->codigo }}</td>
                            <td class="align-middle">{{ $sala->nombre }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted text-center">No hay salas cargadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
