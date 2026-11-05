@extends("theme.$theme.layout")
@section('titulo')
Acta CAEA {{ $registro->periodo }}/Q{{ $registro->orden }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Acta de presentación CAEA
                    — {{ $registro->empresa->nombre ?? '' }}
                    {{ $registro->periodo }}/Q{{ $registro->orden }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('arca_caea') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if ($texto === null || trim($texto) === '')
                    <p class="text-muted mb-0">Esta quincena todavía no tiene un acta de presentación.</p>
                @else
                    <pre class="mb-0" style="white-space:pre-wrap; font-size:13px;">{{ $texto }}</pre>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
