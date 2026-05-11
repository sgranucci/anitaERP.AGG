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
            ['id' => '986', 'nombre' => 'Ingresar retencion impositiva arca', 'slug' => 'crear-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '987', 'nombre' => 'Listar retencion impositiva arca', 'slug' => 'listar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '988', 'nombre' => 'Editar retencion impositiva arca', 'slug' => 'editar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '989', 'nombre' => 'Actualizar retencion impositiva arca', 'slug' => 'actualizar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '990', 'nombre' => 'Borrar retencion impositiva arca', 'slug' => 'borrar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '991', 'nombre' => 'Crear retencion impositiva arca', 'slug' => 'crear-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '992', 'nombre' => 'Importar retencion impositiva arca', 'slug' => 'importar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '993', 'nombre' => 'Conciliar retencion impositiva arca', 'slug' => 'conciliar-retencion-impositiva-arca', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1001', 'nombre' => 'Ingresar feriados', 'slug' => 'crear-feriado', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1002', 'nombre' => 'Listar feriados', 'slug' => 'listar-feriado', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1003', 'nombre' => 'Editar feriados', 'slug' => 'editar-feriado', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1004', 'nombre' => 'Actualizar feriados', 'slug' => 'actualizar-feriado', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1005', 'nombre' => 'Borrar feriados', 'slug' => 'borrar-feriado', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1006', 'nombre' => 'Listar cuenta corriente del proveedor', 'slug' => 'listar-cuentacorriente-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1007', 'nombre' => 'Listar encuestas del proveedor', 'slug' => 'listar-encuesta-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1008', 'nombre' => 'Listar requisiciones del proveedor', 'slug' => 'listar-requisicion-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1020', 'nombre' => 'Listar requisiciones', 'slug' => 'listar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1021', 'nombre' => 'Ingresar requisiciones', 'slug' => 'crear-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1022', 'nombre' => 'Editar requisiciones', 'slug' => 'editar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1023', 'nombre' => 'Actualizar requisiciones', 'slug' => 'actualizar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1024', 'nombre' => 'Borrar requisiciones', 'slug' => 'borrar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1025', 'nombre' => 'Usuario requisiciones compras', 'slug' => 'usuario-requisicion-compras', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1026', 'nombre' => 'Usuario requisiciones resto sectores', 'slug' => 'usuario-requisicion-resto', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1009', 'nombre' => 'Listar ordenes de compra del proveedor', 'slug' => 'listar-ordencompra-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1010', 'nombre' => 'Ingresar tipo de servicio de proveedores', 'slug' => 'crear-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1011', 'nombre' => 'Listar tipo de servicio de proveedores', 'slug' => 'listar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1012', 'nombre' => 'Editar tipo de servicio de proveedores', 'slug' => 'editar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1013', 'nombre' => 'Actualizar tipo de servicio de proveedores', 'slug' => 'actualizar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1014', 'nombre' => 'Borrar tipo de servicio de proveedores', 'slug' => 'borrar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1015', 'nombre' => 'Ingresar encuestas', 'slug' => 'crear-encuesta', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1016', 'nombre' => 'Listar encuestas', 'slug' => 'listar-encuesta', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1017', 'nombre' => 'Editar encuestas', 'slug' => 'editar-encuesta', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1018', 'nombre' => 'Actualizar encuestas', 'slug' => 'actualizar-encuesta', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1019', 'nombre' => 'Borrar encuestas', 'slug' => 'borrar-encuesta', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2016', 'nombre' => 'Ingresar tipo de producto', 'slug' => 'crear-tipo-de-producto', 'menu_id' => 201, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2017', 'nombre' => 'Listar tipo de producto', 'slug' => 'listar-tipo-de-producto', 'menu_id' => 201, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2018', 'nombre' => 'Editar tipo de producto', 'slug' => 'editar-tipo-de-producto', 'menu_id' => 201, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2019', 'nombre' => 'Actualizar tipo de producto', 'slug' => 'actualizar-tipo-de-producto', 'menu_id' => 201, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2020', 'nombre' => 'Borrar tipo de producto', 'slug' => 'borrar-tipo-de-producto', 'menu_id' => 201, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2021', 'nombre' => 'Ingresar capacidad', 'slug' => 'crear-capacidad', 'menu_id' => 202, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2022', 'nombre' => 'Listar capacidad', 'slug' => 'listar-capacidad', 'menu_id' => 202, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2023', 'nombre' => 'Editar capacidad', 'slug' => 'editar-capacidad', 'menu_id' => 202, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2024', 'nombre' => 'Actualizar capacidad', 'slug' => 'actualizar-capacidad', 'menu_id' => 202, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2025', 'nombre' => 'Borrar capacidad', 'slug' => 'borrar-capacidad', 'menu_id' => 202, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2031', 'nombre' => 'Ingresar sector legajo compra', 'slug' => 'crear-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2032', 'nombre' => 'Listar sector legajo compra', 'slug' => 'listar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2033', 'nombre' => 'Editar sector legajo compra', 'slug' => 'editar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2034', 'nombre' => 'Actualizar sector legajo compra', 'slug' => 'actualizar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2035', 'nombre' => 'Borrar sector legajo compra', 'slug' => 'borrar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2026', 'nombre' => 'Ingresar tipo de liquido de freno', 'slug' => 'crear-tipo-de-liquido-de-freno', 'menu_id' => 203, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2027', 'nombre' => 'Listar tipo de liquido de freno', 'slug' => 'listar-tipo-de-liquido-de-freno', 'menu_id' => 203, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2028', 'nombre' => 'Editar tipo de liquido de freno', 'slug' => 'editar-tipo-de-liquido-de-freno', 'menu_id' => 203, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2029', 'nombre' => 'Actualizar tipo de liquido de freno', 'slug' => 'actualizar-tipo-de-liquido-de-freno', 'menu_id' => 203, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2030', 'nombre' => 'Borrar tipo de liquido de freno', 'slug' => 'borrar-tipo-de-liquido-de-freno', 'menu_id' => 203, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1006', 'nombre' => 'Editar articulos', 'slug' => 'editar-articulos', 'menu_id' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1007', 'nombre' => 'Actualizar articulos', 'slug' => 'actualizar-articulos', 'menu_id' => 10, 'created_at' => $now, 'updated_at' => $now],

        ];
        DB::table('permiso')->insert($permiso);
    }
}
