@php
    $esReversa = $doc->revierte_id !== null;
    $fueRevertido = in_array((int) $doc->id, $idsRevertidos, true);
@endphp
<div class="tc-papel {{ $esReversa ? 'is-reversa' : '' }} {{ $fueRevertido ? 'is-revertido' : '' }}">
    <a class="tc-papel-nro text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $doc->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($doc->numero) }}</a>
    @if ($fueRevertido)
        <span class="tc-papel-marca is-revertido"><i class="fa fa-undo"></i> Reversada</span>
    @elseif ($esReversa)
        <span class="tc-papel-marca is-reversa"><i class="fa fa-undo"></i> Reversa</span>
        <span class="tc-papel-estado">{{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($doc->estado) }}</span>
    @else
        <span class="tc-papel-estado">{{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($doc->estado) }}</span>
    @endif
</div>
