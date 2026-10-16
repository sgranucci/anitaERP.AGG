<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20"
               value="{{ old('codigo', $data->codigo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Activo</label>
    <div class="col-lg-6">
        <div class="form-check">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" id="activo" value="1" class="form-check-input"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Disponible en la carga de tickets</label>
        </div>
    </div>
</div>
@if (($tipo ?? '') === 'area')
    <div class="card card-outline card-info mt-3">
        <div class="card-header">
            <h3 class="card-title">Correos de aviso por empresa</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Al generar un ingreso con esta área, se avisa a los correos de la empresa del ticket.
                Un correo por línea.
            </p>
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:220px">Empresa</th>
                        <th>Correos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($empresasCorreo ?? [] as $empresa)
                        @php
                            $textoCorreo = old('correos.'.$empresa->id, ($correosTexto ?? [])[$empresa->id] ?? '');
                        @endphp
                        <tr>
                            <td class="align-middle">{{ $empresa->nombre }}</td>
                            <td>
                                <textarea name="correos[{{ $empresa->id }}]" class="form-control" rows="4"
                                          placeholder="correo@grupoagg.com">{{ $textoCorreo }}</textarea>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-muted">No hay empresas operativas para cargar correos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
