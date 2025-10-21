@extends("theme.$theme.layout")
@section("titulo")
Permiso - Rol
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/permiso-rol/index.js")}}" type="text/javascript"></script>
<script src="https://unpkg.com/sticky-table-headers"></script>
<script>
    $('table').stickyTableHeaders();
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Permiso - Rol</h3>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('permiso_rol') }}" method="GET">
						<div class="btn-group">
							<input type="text" name="permiso" class="form-control" placeholder="Filtra Permiso ..."> 
						</div>
						<div class="btn-group">
							<input type="text" name="centrocosto" class="form-control" placeholder="Filtra C.Costo ..."> 
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                </div>                
            </div>
            <div class="card-body table-responsive p-0">
                @csrf
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th>Permiso</th>
                            @foreach ($rols as $id => $nombre)
                            <th class="text-center">{{$nombre}}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permisos as $key => $permiso)
                            <tr>
                                <td class="font-weight-bold">{{$permiso["nombre"]}}</td>
                                @foreach($rols as $id => $nombre)
                                    <td class="text-center">
                                        <input
                                        type="checkbox"
                                        class="permiso_rol"
                                        name="permiso_rol[]"
                                        data-permisoid={{$permiso[ "id"]}}
                                        value="{{$id}}" {{in_array($id, array_column($permisosRols[$permiso["id"]], "id"))? "checked" : ""}}>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
