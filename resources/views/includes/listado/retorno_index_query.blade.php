{{--
  No usar @include: las variables @php del partial no llegan al scope del index.
  En cada index.blade.php, antes de los links editar/nuevo:

  @php
      $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
  @endphp
--}}
