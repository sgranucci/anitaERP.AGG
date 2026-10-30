<div class="tc-mesa-ops d-flex flex-wrap" style="gap:.35rem">
    @foreach (\App\Support\Caja\TesoreriaCrownMesaSupport::TIPOS as $codigoTipo => $nombreTipo)
        @php
            $claseOperacion = match ($codigoTipo) {
                'A' => 'btn-primary',
                'C' => 'btn-outline-warning',
                'F' => 'btn-outline-success',
                default => 'btn-outline-secondary',
            };
            $ayudaOperacion = \App\Support\Caja\TesoreriaCrownMesaSupport::ayuda($codigoTipo);
        @endphp
        <button type="submit" name="tipo" value="{{ $codigoTipo }}" class="btn btn-sm {{ $claseOperacion }}" data-ayuda="{{ $ayudaOperacion }}" title="{{ $ayudaOperacion }}">{{ $nombreTipo }}</button>
    @endforeach
</div>
<p class="text-muted small tc-mesa-ops-ayuda mb-0">{{ \App\Support\Caja\TesoreriaCrownMesaSupport::ayuda('A') }}</p>
<script>
(function () {
    document.querySelectorAll('.tc-mesa-ops').forEach(function (barra) {
        if (barra.getAttribute('data-listo') === '1') {
            return;
        }
        barra.setAttribute('data-listo', '1');
        var linea = barra.nextElementSibling;
        if (!linea) {
            return;
        }
        barra.querySelectorAll('button[data-ayuda]').forEach(function (boton) {
            function mostrar() {
                linea.textContent = boton.getAttribute('data-ayuda') || '';
            }
            boton.addEventListener('mouseenter', mostrar);
            boton.addEventListener('focus', mostrar);
        });
    });
})();
</script>
