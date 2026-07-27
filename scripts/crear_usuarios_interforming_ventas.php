<?php

/**
 * Alta / actualización usuarios Interforming (Ventas + Facturación).
 * Ejecutar: php scripts/crear_usuarios_interforming_ventas.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;

$empresaId = (int) DB::table('empresa')->where('nombre', 'like', '%Interforming%')->value('id');
if ($empresaId <= 0) {
    throw new RuntimeException('No se encontró empresa Interforming');
}

$rolVentasId = (int) DB::table('rol')->where('nombre', 'Ventas')->value('id');
$rolFacturacionId = (int) DB::table('rol')->where('nombre', 'Facturacion')->value('id');
if ($rolVentasId <= 0 || $rolFacturacionId <= 0) {
    throw new RuntimeException('Faltan roles Ventas y/o Facturacion');
}

$password = '12345';

$ventas = [
    ['usuario' => 'bcorvalan', 'nombre' => 'Belén Corvalan'],
    ['usuario' => 'ngaleano', 'nombre' => 'Natalia Galeano'],
    ['usuario' => 'mgomez', 'nombre' => 'Margarita Gomez'],
    ['usuario' => 'omarin', 'nombre' => 'Omar Marín'],
    ['usuario' => 'jlaporta', 'nombre' => 'Jorge La Porta'],
    ['usuario' => 'ndecarvalho', 'nombre' => 'Noelia De Carvalho'],
    ['usuario' => 'gbravo', 'nombre' => 'Guillermo Bravo'],
    ['usuario' => 'javolio', 'nombre' => 'Juan Avolio'],
    ['usuario' => 'mmauriz', 'nombre' => 'Mabel Mauriz'],
];

$facturacion = [
    ['usuario' => 'fruiz', 'nombre' => 'Fernando Ruiz'],
    ['usuario' => 'sgardini', 'nombre' => 'Sandra Gardini'],
];

// Facturación: poder entrar a pedidos para facturar
$slugsPedidosFacturacion = [
    'listar-pedidos',
    'editar-pedidos',
    'actualizar-pedidos',
];
$permisosAgregados = [];
foreach ($slugsPedidosFacturacion as $slug) {
    $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
    if (!$permisoId) {
        echo "AVISO: no existe permiso {$slug}\n";
        continue;
    }
    $existe = DB::table('permiso_rol')
        ->where('rol_id', $rolFacturacionId)
        ->where('permiso_id', $permisoId)
        ->exists();
    if (!$existe) {
        DB::table('permiso_rol')->insert([
            'rol_id' => $rolFacturacionId,
            'permiso_id' => $permisoId,
        ]);
        $permisosAgregados[] = $slug;
    }
}
if ($permisosAgregados !== []) {
    echo 'Rol Facturacion: agregados permisos ' . implode(', ', $permisosAgregados) . "\n";
} else {
    echo "Rol Facturacion: ya tenía permisos de pedidos necesarios\n";
}

// Ventas: asegurar clientes + pedidos + lista factura
$slugsVentasMinimos = [
    'listar-clientes',
    'crear-clientes',
    'editar-clientes',
    'actualizar-clientes',
    'listar-pedidos',
    'crear-pedidos',
    'editar-pedidos',
    'actualizar-pedidos',
    'listar-factura',
];
$permisosVentasAgregados = [];
foreach ($slugsVentasMinimos as $slug) {
    $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
    if (!$permisoId) {
        echo "AVISO: no existe permiso {$slug}\n";
        continue;
    }
    $existe = DB::table('permiso_rol')
        ->where('rol_id', $rolVentasId)
        ->where('permiso_id', $permisoId)
        ->exists();
    if (!$existe) {
        DB::table('permiso_rol')->insert([
            'rol_id' => $rolVentasId,
            'permiso_id' => $permisoId,
        ]);
        $permisosVentasAgregados[] = $slug;
    }
}
if ($permisosVentasAgregados !== []) {
    echo 'Rol Ventas: agregados permisos ' . implode(', ', $permisosVentasAgregados) . "\n";
} else {
    echo "Rol Ventas: ya tenía permisos mínimos (clientes/pedidos/lista factura)\n";
}

$upsert = function (array $row, int $rolId) use ($empresaId, $password): void {
    $login = $row['usuario'];
    $email = $login . '@interforming.com.ar';
    $usuario = Usuario::query()->where('usuario', $login)->first();
    $accion = 'actualizado';

    if (!$usuario) {
        $usuario = new Usuario();
        $usuario->usuario = $login;
        $accion = 'creado';
    }

    $usuario->nombre = $row['nombre'];
    $usuario->email = $email;
    $usuario->password = $password; // mutator Hash::make
    $usuario->suspendido = false;
    $usuario->save();

    $usuario->roles()->sync([$rolId]);
    $usuario->usuario_empresas()->sync([$empresaId]);

    echo sprintf(
        "%s: %s | rol_id=%d | empresa_id=%d | %s\n",
        $accion,
        $login,
        $rolId,
        $empresaId,
        $row['nombre']
    );
};

DB::transaction(function () use ($ventas, $facturacion, $rolVentasId, $rolFacturacionId, $upsert) {
    foreach ($ventas as $row) {
        $upsert($row, $rolVentasId);
    }
    foreach ($facturacion as $row) {
        $upsert($row, $rolFacturacionId);
    }
});

echo "\n=== Verificación ===\n";
$logins = array_merge(
    array_column($ventas, 'usuario'),
    array_column($facturacion, 'usuario')
);
foreach ($logins as $login) {
    $u = Usuario::query()->where('usuario', $login)->with(['roles:id,nombre', 'usuario_empresas:id,nombre'])->first();
    $roles = $u->roles->pluck('nombre')->implode(',');
    $emps = $u->usuario_empresas->pluck('nombre')->implode(',');
    $okPass = \Illuminate\Support\Facades\Hash::check('12345', $u->password) ? 'OK' : 'FAIL';
    echo "{$u->usuario} | {$u->nombre} | roles=[{$roles}] empresas=[{$emps}] pass12345={$okPass}\n";
}

echo "\nListo.\n";
