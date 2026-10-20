@php
    $bloqueado = ! empty($flashBloqueado);
    $vigente = $habilitacionVigente ?? null;
    $pendiente = $solicitudPendiente ?? null;
    $nombres = [];
    foreach ($destinatariosModificacion ?? [] as $destinatario) {
        $nombre = trim((string) ($destinatario->nombre ?: $destinatario->usuario ?: ''));
        if ($nombre !== '') {
            $nombres[] = $nombre;
        }
    }
@endphp
@if ($vigente)
    <div class="card-body pb-0">
        <div class="alert alert-info py-2 mb-0">
            Contaduría habilitó la modificación
            @if ($vigente->habilitada_en)
                el {{ $vigente->habilitada_en->format('d/m/Y H:i') }}
            @endif
            . Al guardar se quita el tilde verde.
        </div>
    </div>
@elseif ($bloqueado)
    <div class="card-body pb-0">
        <div class="alert alert-warning mb-0">
            <p class="mb-2">
                Este flash tiene tilde verde y la jornada ya tiene cierre contable de máquinas.
                No se puede modificar hasta que contaduría habilite el cambio.
            </p>
            @if ($pendiente)
                <p class="mb-2">
                    Pedido enviado
                    @if ($pendiente->solicitada_en)
                        el {{ $pendiente->solicitada_en->format('d/m/Y H:i') }}
                    @endif
                    . Sigue bloqueado hasta que usen el enlace del mail.
                </p>
            @endif
            @if ($nombres !== [])
                <p class="mb-2">El mail va a {{ implode(' y ', $nombres) }}.</p>
            @endif
            @if (can('actualizar-flash-caja', false))
                <form action="{{ route('solicitar_modificacion_flash_caja', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" method="POST">
                    @csrf
                    <label for="motivo-modificacion-flash" class="mb-1">Motivo del cambio</label>
                    <textarea name="motivo" id="motivo-modificacion-flash" class="form-control mb-2" rows="3" required minlength="10" maxlength="2000">{{ old('motivo', $pendiente->motivo ?? '') }}</textarea>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-envelope"></i>
                        {{ $pendiente ? 'Reenviar mail a contaduría' : 'Pedir habilitación a contaduría' }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
