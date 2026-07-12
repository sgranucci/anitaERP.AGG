{{-- Conserva filtros del index en POST de acciones (informar, actualizar, Anita, etc.). --}}
@foreach (($filtrosQuery ?? []) as $nombre => $valor)
    @if ($valor !== null && $valor !== '')
        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
    @endif
@endforeach
