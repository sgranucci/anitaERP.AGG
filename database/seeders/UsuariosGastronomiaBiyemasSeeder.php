<?php

namespace Database\Seeders;

use App\Models\Admin\Rol;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea los usuarios de Gastronomía Biyemas según planilla
 * "USUARIOS ANITA GASTRONOMIA BIYEMAS.xlsx".
 *
 * Convenciones:
 *  - usuario  = inicial(nombre) + apellido (minúsculas, sin acentos/ñ/espacios)
 *  - email    = {usuario}@grupoagg.com
 *  - password = 12345
 *  - centrocosto_id = id del centro con código 85 (Gastronomía)
 *  - empresa  = BIYEMAS S.A. (única empresa asignada)
 *
 * Mapeo de puestos → rol:
 *  - TEAM MANAGER                   → Enc-gastronomía
 *  - TEAM LEADER / TEAM LEADER SR   → Sup-Gastronomia
 *  - CAJERO/A                       → Op-Gastronomia
 *
 * Es idempotente: si el usuario ya existe se respeta y solo se asegura
 * que tenga el rol, la empresa y el centro de costo definidos.
 */
class UsuariosGastronomiaBiyemasSeeder extends Seeder
{
    public function run(): void
    {
        $centrocostoId = Centrocosto::where('codigo', 85)->value('id');
        if (! $centrocostoId) {
            throw new \RuntimeException('No se encontró el centro de costo con código 85.');
        }

        $empresaBiyemasId = Empresa::where('nombre', 'BIYEMAS S.A.')->value('id');
        if (! $empresaBiyemasId) {
            throw new \RuntimeException('No se encontró la empresa BIYEMAS S.A.');
        }

        $rolEnc = Rol::whereRaw('LOWER(nombre) = ?', ['enc-gastronomía'])->value('id')
            ?? Rol::whereRaw('LOWER(nombre) = ?', ['enc-gastronomia'])->value('id');
        $rolSup = Rol::where('nombre', 'Sup-Gastronomia')->value('id');
        $rolOp = Rol::where('nombre', 'Op-Gastronomia')->value('id');

        foreach (['Enc' => $rolEnc, 'Sup' => $rolSup, 'Op' => $rolOp] as $k => $v) {
            if (! $v) {
                throw new \RuntimeException("No se encontró el rol Gastronomía ($k).");
            }
        }

        $personas = [
            ['DOMINGUEZ',   'DIEGO',       'TEAM MANAGER'],
            ['RUIDIAS',     'RICARDO',     'TEAM LEADER SR'],
            ['SOSA',        'MARCOS',      'TEAM LEADER SR'],
            ['LESCANO',     'AILEN',       'TEAM LEADER'],
            ['IVANCHEVICH', 'GERMAN',      'TEAM LEADER'],
            ['ROBLEDO',     'MAXIMILIANO', 'TEAM LEADER'],
            ['LOVERA',      'ADRIAN',      'TEAM LEADER'],
            ['ROMERO',      'CRISTIAN',    'CAJERO/A'],
            ['AVALOS',      'ALEXIS',      'CAJERO/A'],
            ['CHAPARRO',    'DANIELA',     'CAJERO/A'],
            ['UMAÑO',       'GABRIEL',     'CAJERO/A'],
            ['MONTENEGRO',  'JONATHAN',    'CAJERO/A'],
            ['SOSA',        'VIVIANA',     'CAJERO/A'],
            ['VILLASMIL',   'EISLE',       'CAJERO/A'],
            ['HERNANDEZ',   'GISELE',      'CAJERO/A'],
            ['MALDONADO',   'FACUNDO',     'CAJERO/A'],
            ['GONZALEZ',    'LEONARDO',    'CAJERO/A'],
            ['AMARILLA',    'DAVID',       'CAJERO/A'],
            ['ZIMMERMAN',   'NICOLAS',     'CAJERO/A'],
        ];

        $mapaRolPorPuesto = [
            'TEAM MANAGER' => $rolEnc,
            'TEAM LEADER' => $rolSup,
            'TEAM LEADER SR' => $rolSup,
            'CAJERO/A' => $rolOp,
        ];

        $resumen = ['creados' => [], 'existentes' => [], 'errores' => []];

        DB::transaction(function () use ($personas, $mapaRolPorPuesto, $centrocostoId, $empresaBiyemasId, &$resumen) {
            foreach ($personas as [$apellido, $nombre, $puesto]) {
                $apellido = trim($apellido);
                $nombre = trim($nombre);
                $puesto = trim($puesto);

                $login = $this->normalizarLogin(mb_substr($nombre, 0, 1).$apellido);
                $email = $login.'@grupoagg.com';
                $nombreCompleto = $this->capitalizar($nombre).' '.$this->capitalizar($apellido);
                $rolId = $mapaRolPorPuesto[$puesto] ?? null;

                if (! $rolId) {
                    $resumen['errores'][] = "Puesto no mapeado para $nombreCompleto: $puesto";

                    continue;
                }

                $usuario = Usuario::where('usuario', $login)->orWhere('email', $email)->first();

                if ($usuario) {
                    $resumen['existentes'][] = "$login ($nombreCompleto) ya existe (id={$usuario->id}). Se asegura rol/empresa/centrocosto.";
                } else {
                    $usuario = Usuario::create([
                        'usuario' => $login,
                        'nombre' => $nombreCompleto,
                        'email' => $email,
                        'password' => '12345',
                        'centrocosto_id' => $centrocostoId,
                    ]);
                    $resumen['creados'][] = "$login ($nombreCompleto) creado (id={$usuario->id}).";
                }

                if ((int) $usuario->centrocosto_id !== (int) $centrocostoId) {
                    $usuario->centrocosto_id = $centrocostoId;
                    $usuario->save();
                }

                if (! $usuario->roles()->where('rol.id', $rolId)->exists()) {
                    $usuario->roles()->attach($rolId);
                }

                if (! $usuario->usuario_empresas()->where('empresa.id', $empresaBiyemasId)->exists()) {
                    $usuario->usuario_empresas()->attach($empresaBiyemasId);
                }
            }
        });

        $this->command?->info('--- Creados ---');
        foreach ($resumen['creados'] as $l) {
            $this->command?->info($l);
        }
        $this->command?->info('--- Existentes ---');
        foreach ($resumen['existentes'] as $l) {
            $this->command?->warn($l);
        }
        if ($resumen['errores']) {
            $this->command?->info('--- Errores ---');
            foreach ($resumen['errores'] as $l) {
                $this->command?->error($l);
            }
        }
    }

    private function normalizarLogin(string $valor): string
    {
        $valor = mb_strtolower(trim($valor), 'UTF-8');
        $valor = strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $valor);
    }

    private function capitalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor), 'UTF-8');

        return mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
    }
}
