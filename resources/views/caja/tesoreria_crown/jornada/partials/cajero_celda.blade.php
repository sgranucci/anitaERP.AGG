@php
    $paramsCierre = ['jornada' => $jornada->id, 'cajero' => $fila->cajero_crown_id];
@endphp
@switch ($key)
    @case('turno')
        —
        @break
    @case('caja')
        —
        @break
    @case('cajero')
        {{ $fila->cajero->codigo ?? '' }} — {{ $fila->cajero->nombre ?? '' }}
        @break
    @case('tarea')
        {{ \App\Support\Caja\TesoreriaCrownTareaCatalogo::tarea($fila->tipo_tarea) }}
        @if ($fila->permite_rendir_sin_habilitacion)
            <span class="badge badge-secondary ml-1" title="Permite rendir sin habilitación">sin HAB</span>
        @endif
        @break
    @case('estado')
        @if ($cajaCerrada)
            <span class="tc-papel-marca is-cerrada">Cerrado</span>
            <span class="tc-papel-estado d-block">{{ $fila->cierre->estadoTexto() }}</span>
        @else
            <span class="tc-papel-marca is-abierta">Abierto</span>
        @endif
        @break
    @case('habilitacion')
        @foreach ($habilitaciones as $doc)
            @include('caja.tesoreria_crown.jornada.partials.papel_grilla', ['doc' => $doc, 'idsRevertidos' => $idsRevertidos])
        @endforeach
        @if ($jornada->estaAbierta() && ! $cajaCerrada && can('crear-tesoreria-crown-movimiento', false))
            <form method="POST" action="{{ route('crear_tesoreria_crown_movimiento') }}" class="d-inline">
                @csrf
                <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                <input type="hidden" name="cajero_crown_id" value="{{ $fila->cajero_crown_id }}">
                <input type="hidden" name="tipo" value="H">
                <button type="submit" class="btn btn-link btn-sm p-0"><i class="fa fa-plus-circle"></i> {{ $habilitaciones->isEmpty() ? 'Habilitar' : 'Otra habilitación' }}</button>
            </form>
        @endif
        @break
    @case('rendicion')
        @foreach ($rendiciones as $doc)
            @include('caja.tesoreria_crown.jornada.partials.papel_grilla', ['doc' => $doc, 'idsRevertidos' => $idsRevertidos])
        @endforeach
        @if ($jornada->estaAbierta() && ! $cajaCerrada && ($hayHabilitacionConforme || $fila->permite_rendir_sin_habilitacion) && can('crear-tesoreria-crown-movimiento', false))
            <form method="POST" action="{{ route('crear_tesoreria_crown_movimiento') }}" class="d-inline">
                @csrf
                <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                <input type="hidden" name="cajero_crown_id" value="{{ $fila->cajero_crown_id }}">
                <input type="hidden" name="tipo" value="F">
                <button type="submit" class="btn btn-link btn-sm p-0"><i class="fa fa-plus-circle"></i> {{ $rendiciones->isEmpty() ? 'Rendir' : 'Otra rendición' }}</button>
            </form>
        @endif
        @break
    @case('cierre')
        @php
            $cierresReabiertos = $fila->cierres->filter(fn ($cierreFila) => $cierreFila->reabierta_at !== null);
        @endphp
        @if ($cajaCerrada && $cierresReabiertos->isEmpty())
            <div class="tc-papel">
                <a class="tc-papel-nro text-primary" href="{{ route('ver_tesoreria_crown_cierre', $paramsCierre) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($fila->cierre->numero) }}</a>
                <span class="tc-papel-estado">{{ $fila->cierre->estadoTexto() }}</span>
            </div>
        @elseif ($cajaCerrada)
            <div class="tc-papel">
                <a class="tc-papel-nro text-primary" href="{{ route('ver_tesoreria_crown_cierre', $paramsCierre) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($fila->cierre->numero) }}</a>
                <span class="tc-papel-marca is-vigente">Vigente</span>
                <span class="tc-papel-estado">{{ $fila->cierre->estadoTexto() }}</span>
            </div>
        @elseif ($jornada->estaAbierta())
            <a class="text-primary" href="{{ route('ver_tesoreria_crown_cierre', $paramsCierre) }}">Cerrar cajero</a>
        @elseif ($cierresReabiertos->isEmpty())
            —
        @endif
        @foreach ($cierresReabiertos as $cierreAnterior)
            <div class="tc-papel is-reabierto">
                <a class="tc-papel-nro" href="{{ route('ver_tesoreria_crown_cierre', $paramsCierre) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($cierreAnterior->numero) }}</a>
                <span class="tc-papel-marca is-reabierto"><i class="fa fa-unlock-alt"></i> Reabierto</span>
            </div>
        @endforeach
        @break
@endswitch
