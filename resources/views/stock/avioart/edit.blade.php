@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">Editar Avios</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="errors">
                            <p><strong>Por favor corrige los siguientes errores<strong></p>
                            <ul class="alert alert-danger" style="list-style-type: none">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="" class="" method="POST" action="{{ route('avioart.update') }}">
                        @csrf
                        <input type="hidden" name="id" class="form-control" value="{{ $id }}" />
                        @include('avioart.partials.form', ['edit' => true])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection