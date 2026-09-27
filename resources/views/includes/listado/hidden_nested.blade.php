{{-- Inputs hidden recursivos para filtrosQuery (qbe anidado, sort, etc.) --}}
@php
    $prefix = $prefix ?? '';
    $data = $data ?? [];
    $skipKeys = $skipKeys ?? [];
@endphp
@foreach ($data as $key => $value)
    @if (in_array((string) $key, $skipKeys, true))
        @continue
    @endif
    @php
        $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';
    @endphp
    @if (is_array($value))
        @include('includes.listado.hidden_nested', [
            'prefix' => $name,
            'data' => $value,
            'skipKeys' => [],
        ])
    @elseif ($value !== null && $value !== '')
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @elseif ($value === 0 || $value === '0' || $value === false)
        <input type="hidden" name="{{ $name }}" value="{{ $value === false ? '0' : $value }}">
    @endif
@endforeach
