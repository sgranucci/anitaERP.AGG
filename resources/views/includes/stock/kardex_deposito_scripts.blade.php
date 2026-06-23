{{-- Scripts depósito para modal kardex (consulta.js + F1). Incluir en @section('scripts'), después de jQuery. --}}
@once('anita-kardex-deposito-scripts')
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/atajos-consulta.js') }}" type="text/javascript"></script>
@endonce
