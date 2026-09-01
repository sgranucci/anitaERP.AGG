@extends("theme.$theme.layout")
@section('titulo')
    Estado de transferencia de artículos y precios a Tienda Nube
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Estado de transferencia</h3>
                <div class="card-tools">
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">SKU</th>
                            <th class="width40">Variante</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($respuesta as $data)
                        <tr>
                            <td>{{$data['sku']}}</td>
                            <td>{{$data['variante']}}</td>
                            <td>{{$data['estado']}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
