<?php

namespace Database\Seeders;

use App\Models\Seguridad\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TblaprobRolesUsuariosSeeder extends Seeder
{
    /**
     * Códigos de centro de costo presentes en el archivo tblaprob.xlsx.
     */
    private array $codigosCentroCosto = [
        '80', '85', '86', '87', '88', '89', '90', '91', '92', '93',
        '95', '96', '97', '98', '99', '100', '101', '102', '103', '104', '105',
    ];

    /**
     * Usuarios extraídos del Excel: [logname, nombre].
     */
    private array $usuariosExcel = [
        ['mbmendez', 'MENDEZ MARIA BETANIA'],
        ['eperez', 'PEREZ ERIKA'],
        ['aperdomo', 'PERDOMO ADRIANA'],
        ['ddominguez', 'DOMINGUEZ DIEGO'],
        ['hdattilo', 'DATTILO HERNAN'],
        ['afernandez', 'FERNANDEZ ALBERTO'],
        ['gbravo', 'BRAVO GASTON'],
        ['lbivas', 'BIVAS LUCAS'],
        ['abarzola', 'BARZOLA ALEJANDRA'],
        ['vurruchua', 'URRUCHUA VALERIA'],
        ['babeldano', 'ABELDANO BELEN'],
        ['eniello', 'NIELLO ESTEBAN'],
        ['amacedo', 'MACEDO ALEJANDRO'],
        ['aflorentin', 'FLORENTIN ANGEL'],
        ['ptrapani', 'TRAPANI PAULA'],
        ['jgesualdo', 'GESUALDO JIMENA'],
        ['lsuarez', 'SUAREZ LAURA'],
        ['ffernandez', 'FERNANDEZ FLORENCIA'],
        ['gdominick', 'DOMINICK GRISELDA'],
        ['gsurace', 'Guillermo Surace'],
        ['ablanco', 'BLANCO ALEJANDRO'],
        ['eguevara', 'GUEVARA EGORIS'],
        ['gmagliolo', 'MAGLIOLO GUILLERMO'],
        ['ofalqui', 'FALQUI OSCAR'],
        ['liglesias', 'IGLESIAS LAURA'],
        ['rfogliatti', 'FOGLIATTI RUBEN'],
        ['nperez', 'PEREZ NATALIA'],
        ['gmurua', 'MURUA GABRIEL'],
    ];

    public function run(): void
    {
        $this->crearRolesPorCentroCosto();
        $this->crearUsuariosFaltantes();
    }

    /**
     * Crea un rol "enc-{nombre cc}" y "op-{nombre cc}" para cada centro de costo
     * del Excel que todavía no tenga uno con ese prefijo.
     */
    private function crearRolesPorCentroCosto(): void
    {
        $now = Carbon::now()->toDateTimeString();

        $centroCostos = DB::table('centrocosto')
            ->whereIn('codigo', $this->codigosCentroCosto)
            ->orderBy('id')
            ->get(['id', 'codigo', 'nombre']);

        foreach ($centroCostos as $cc) {
            $tieneEnc = DB::table('rol')
                ->where('centrocosto_id', $cc->id)
                ->where('nombre', 'like', 'enc-%')
                ->exists();

            if (! $tieneEnc) {
                $this->insertarRol("enc-{$cc->nombre}", $cc->id, $now);
            }

            $tieneOp = DB::table('rol')
                ->where('centrocosto_id', $cc->id)
                ->where('nombre', 'like', 'op-%')
                ->exists();

            if (! $tieneOp) {
                $this->insertarRol("op-{$cc->nombre}", $cc->id, $now);
            }
        }
    }

    /**
     * Inserta el rol verificando que no exista previamente
     * (la columna nombre tiene unique con collation utf8mb4_spanish_ci).
     */
    private function insertarRol(string $nombre, int $centroCostoId, string $now): void
    {
        $existe = DB::table('rol')->where('nombre', $nombre)->exists();
        if ($existe) {
            return;
        }

        DB::table('rol')->insert([
            'nombre' => $nombre,
            'centrocosto_id' => $centroCostoId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Crea los usuarios del Excel que no estén ya en la tabla usuario,
     * con password 12345 y los asigna a la empresa BIYEMAS.
     */
    private function crearUsuariosFaltantes(): void
    {
        $empresaBiyemas = DB::table('empresa')
            ->where('nombre', 'like', 'BIYEMAS%')
            ->value('id');

        if (! $empresaBiyemas) {
            throw new \RuntimeException('No se encontró la empresa BIYEMAS.');
        }

        foreach ($this->usuariosExcel as [$logname, $nombre]) {
            $yaExiste = DB::table('usuario')->where('usuario', $logname)->exists();
            if ($yaExiste) {
                continue;
            }

            $usuario = Usuario::create([
                'usuario' => $logname,
                'nombre' => $nombre,
                'email' => "{$logname}@grupoagg.com",
                'password' => '12345',
            ]);

            $yaAsignado = DB::table('usuario_empresa')
                ->where('usuario_id', $usuario->id)
                ->where('empresa_id', $empresaBiyemas)
                ->exists();

            if (! $yaAsignado) {
                DB::table('usuario_empresa')->insert([
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $empresaBiyemas,
                ]);
            }
        }
    }
}
