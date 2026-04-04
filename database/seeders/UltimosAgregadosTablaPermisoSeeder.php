<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UltimosAgregadosTablaPermisoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now()->toDateTimeString();
        $permiso = [
            array('id' => '986', 'nombre' => 'Ingresar retencion impositiva arca', 'slug' => 'crear-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '987', 'nombre' => 'Listar retencion impositiva arca', 'slug' => 'listar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '988', 'nombre' => 'Editar retencion impositiva arca', 'slug' => 'editar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '989', 'nombre' => 'Actualizar retencion impositiva arca', 'slug' => 'actualizar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '990', 'nombre' => 'Borrar retencion impositiva arca', 'slug' => 'borrar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '991', 'nombre' => 'Crear retencion impositiva arca', 'slug' => 'crear-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '992', 'nombre' => 'Importar retencion impositiva arca', 'slug' => 'importar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '993', 'nombre' => 'Conciliar retencion impositiva arca', 'slug' => 'conciliar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1001', 'nombre' => 'Ingresar feriados', 'slug' => 'crear-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1002', 'nombre' => 'Listar feriados', 'slug' => 'listar-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1003', 'nombre' => 'Editar feriados', 'slug' => 'editar-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1004', 'nombre' => 'Actualizar feriados', 'slug' => 'actualizar-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1005', 'nombre' => 'Borrar feriados', 'slug' => 'borrar-feriado', 'created_at' => $now, 'updated_at' => $now),            
            array('id' => '1006', 'nombre' => 'Listar cuenta corriente del proveedor', 'slug' => 'listar-cuentacorriente-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1007', 'nombre' => 'Listar encuestas del proveedor', 'slug' => 'listar-encuestra-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1008', 'nombre' => 'Listar requisiciones del proveedor', 'slug' => 'listar-requisicion-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1009', 'nombre' => 'Listar ordenes de compra del proveedor', 'slug' => 'listar-ordencompra-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1010', 'nombre' => 'Ingresar tipo de servicio de proveedores', 'slug' => 'crear-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1011', 'nombre' => 'Listar tipo de servicio de proveedores', 'slug' => 'listar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1012', 'nombre' => 'Editar tipo de servicio de proveedores', 'slug' => 'editar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1013', 'nombre' => 'Actualizar tipo de servicio de proveedores', 'slug' => 'actualizar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1014', 'nombre' => 'Borrar tipo de servicio de proveedores', 'slug' => 'borrar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1015', 'nombre' => 'Ingresar encuestas', 'slug' => 'crear-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1016', 'nombre' => 'Listar encuestas', 'slug' => 'listar-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1017', 'nombre' => 'Editar encuestas', 'slug' => 'editar-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1018', 'nombre' => 'Actualizar encuestas', 'slug' => 'actualizar-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1019', 'nombre' => 'Borrar encuestas', 'slug' => 'borrar-encuesta', 'created_at' => $now, 'updated_at' => $now),            
        ];
        DB::table('permiso')->insert($permiso);
    }
}
