@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">Crear Combinación</div>
                    @if (session('status'))
                    <div class="card-body">
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    </div>
                    @endif
                    @if ($errors->any())
                    <div class="card-body">
                        <div class="errors">
                            <p><strong>Por favor corrige los siguientes errores<strong></p>
                            <ul class="alert alert-danger" style="list-style-type: none">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
            </div>
            <br>
            <form id="" class="" method="POST" action="{{ route('combinacion.save') }}">
                @csrf
                @include('combinacion.tecnica.partials.form')
            </form>
        </div>
    </div>
</div>
@endsection