<p>
    Este mensaje se envía desde una casilla automática que no recibe respuestas.
    @if (! empty($emailConsulta))
        Para consultas sobre retenciones, comprobantes o este documento, escriba a
        <a href="mailto:{{ $emailConsulta }}">{{ $emailConsulta }}</a>.
    @endif
</p>
