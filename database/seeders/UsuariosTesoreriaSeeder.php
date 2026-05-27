<?php

namespace Database\Seeders;

use App\Models\Admin\Rol;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea los usuarios de Tesorería según planilla
 * "Cajeros y supervisores actuales 3 empresas (2).xlsx" (solapas Biyemas, Kandiko, Rebisco).
 *
 * Convenciones:
 *  - usuario  = inicial(nombre) + apellido (minúsculas, sin acentos/ñ/espacios)
 *  - email    = {usuario}@grupoagg.com
 *  - password = 12345
 *  - centrocosto_id = id del centro con código 98 (Tesorería)
 *  - rol      = Op-tesoreria
 *  - empresa  = la de cada solapa (BIYEMAS S.A., KANDIKO S.A., REBISCO S.A.)
 *
 * Es idempotente: si el usuario ya existe se respeta y solo se asegura
 * rol, empresa(s) y centro de costo.
 */
class UsuariosTesoreriaSeeder extends Seeder
{
    public function run(): void
    {
        $centrocostoId = Centrocosto::where('codigo', 98)->value('id');
        if (! $centrocostoId) {
            throw new \RuntimeException('No se encontró el centro de costo con código 98.');
        }

        $rolOpId = Rol::where('nombre', 'Op-tesoreria')->value('id');
        if (! $rolOpId) {
            throw new \RuntimeException('No se encontró el rol Op-tesoreria.');
        }

        $empresaIds = [];
        foreach (['BIYEMAS S.A.', 'KANDIKO S.A.', 'REBISCO S.A.'] as $nombreEmpresa) {
            $id = Empresa::where('nombre', $nombreEmpresa)->value('id');
            if (! $id) {
                throw new \RuntimeException("No se encontró la empresa $nombreEmpresa.");
            }
            $empresaIds[$nombreEmpresa] = $id;
        }

        $personas = [
            ['BIYEMAS S.A.', 'URZI', 'DANIELA'],
            ['BIYEMAS S.A.', 'ARCE', 'CAMILA SOLANGE'],
            ['BIYEMAS S.A.', 'BAEZ', 'BIANCA MALENA'],
            ['BIYEMAS S.A.', 'BORDON', 'VERONICA EDITH'],
            ['BIYEMAS S.A.', 'CARBALLO', 'DAMIAN EZEQUIEL'],
            ['BIYEMAS S.A.', 'GONZALEZ BENITEZ', 'LOURDES'],
            ['BIYEMAS S.A.', 'GONZALEZ CARRANZA', 'SABRINA'],
            ['BIYEMAS S.A.', 'JIMENEZ', 'JULIANA STEPHANIE'],
            ['BIYEMAS S.A.', 'PADRON', 'ESTEFANIA YANET'],
            ['BIYEMAS S.A.', 'SERAFINI', 'MARIA ROSA'],
            ['BIYEMAS S.A.', 'SORIA ROSA', 'AYELEN'],
            ['BIYEMAS S.A.', 'SUCHETTI', 'ROMINA YAMILA'],
            ['BIYEMAS S.A.', 'SZMYR', 'CINTHIA BELEN'],
            ['BIYEMAS S.A.', 'VELA', 'LUISA ELEUTERIA'],
            ['BIYEMAS S.A.', 'BAUSTIAN', 'ALAN EZEQUIEL'],
            ['BIYEMAS S.A.', 'ASSELBORN', 'ADRIAN ALEJANDRO'],
            ['BIYEMAS S.A.', 'CARBALLO', 'CINTIA ANALIA'],
            ['BIYEMAS S.A.', 'CERQUEIRO', 'MARIA TERESA'],
            ['BIYEMAS S.A.', 'CORBETTA', 'GISELA IARA'],
            ['BIYEMAS S.A.', 'CURPAVICH', 'GIMENA NATALIA'],
            ['BIYEMAS S.A.', 'DOMINGUEZ', 'LEANDRO MARTIN'],
            ['BIYEMAS S.A.', 'PARDO', 'JESICA VANINA'],
            ['BIYEMAS S.A.', 'RODRIGUEZ', 'JUAN CRUZ'],
            ['BIYEMAS S.A.', 'CANOSA', 'BRENDA DANIELA'],
            ['BIYEMAS S.A.', 'MAGLIOLO', 'GUILLERMO ARIEL'],
            ['KANDIKO S.A.', 'BIASOTTI', 'MARTIN LUIS'],
            ['KANDIKO S.A.', 'CORTEZ', 'DORA SILVINA'],
            ['KANDIKO S.A.', 'CRUZ', 'CRISTIAN NAHUEL'],
            ['KANDIKO S.A.', 'DIAZ', 'JESICA ALEJANDRA'],
            ['KANDIKO S.A.', 'GARAY', 'HERNAN'],
            ['KANDIKO S.A.', 'PALAVECINO', 'ROMINA MARIBEL'],
            ['KANDIKO S.A.', 'IBARRA', 'ROCIO DAIANA ABRIL'],
            ['KANDIKO S.A.', 'FERNANDEZ', 'OSCAR MARCELO'],
            ['KANDIKO S.A.', 'CASTILLO CAPURRO', 'URIEL'],
            ['KANDIKO S.A.', 'HARGUINDEY', 'NAZARENA ALEJANDRA'],
            ['KANDIKO S.A.', 'SAUCEDO', 'MIGUEL ANTONIO'],
            ['KANDIKO S.A.', 'MAITAN', 'SILVINA ANABEL'],
            ['KANDIKO S.A.', 'MORATELLI', 'LORENA'],
            ['KANDIKO S.A.', 'SANTORO', 'FABIO ALEJANDRO'],
            ['KANDIKO S.A.', 'MAGLIOLO', 'GUILLERMO ARIEL'],
            ['REBISCO S.A.', 'GOMEZ', 'ROXANA GISELA'],
            ['REBISCO S.A.', 'LLANOS', 'MATIAS EZEQUIEL'],
            ['REBISCO S.A.', 'CARRIZO', 'JONATHAN JOSE LUIS'],
            ['REBISCO S.A.', 'DI BARTOLO', 'MARTIN EZEQUIEL'],
            ['REBISCO S.A.', 'ENRIQUE ROQUE', 'MIGUEL'],
            ['REBISCO S.A.', 'ESPINDOLA', 'CAMILA AYLEN'],
            ['REBISCO S.A.', 'GONZALEZ', 'ALEJANDRO NAHUEL'],
            ['REBISCO S.A.', 'MELGAR', 'ANGEL CLEMENTE'],
            ['REBISCO S.A.', 'NIZZO', 'GABRIEL ALEJANDRO'],
            ['REBISCO S.A.', 'ORTIZ', 'LUCAS RAUL'],
            ['REBISCO S.A.', 'RAGOSA', 'ARIEL GUSTAVO'],
            ['REBISCO S.A.', 'ROMANO ROSA', 'EVA'],
            ['REBISCO S.A.', 'ALFONSO', 'DAIANA DESIREE'],
            ['REBISCO S.A.', 'ALVAREZ', 'NOELIA SOLANGE'],
            ['REBISCO S.A.', 'BERON', 'NATALIO CLEMENTE'],
            ['REBISCO S.A.', 'CARRIZO', 'GONZALO ALBERTO'],
            ['REBISCO S.A.', 'ESPINDOLA', 'LUCAS EZEQUIEL'],
            ['REBISCO S.A.', 'GARCIA', 'PAULA ROSA'],
            ['REBISCO S.A.', 'VIERA', 'ALEJANDRA GLADYS'],
            ['REBISCO S.A.', 'ALEGRE', 'HECTOR ARIEL'],
            ['REBISCO S.A.', 'MAGLIOLO', 'GUILLERMO ARIEL'],
        ];

        $resumen = ['creados' => [], 'existentes' => [], 'errores' => []];

        DB::transaction(function () use ($personas, $rolOpId, $centrocostoId, $empresaIds, &$resumen) {
            foreach ($personas as [$nombreEmpresa, $apellido, $nombre]) {
                $apellido = trim($apellido);
                $nombre = trim($nombre);
                $empresaId = $empresaIds[$nombreEmpresa];

                $login = $this->normalizarLogin(mb_substr($nombre, 0, 1).$apellido);
                $email = $login.'@grupoagg.com';
                $nombreCompleto = $this->capitalizar($nombre).' '.$this->capitalizar($apellido);

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

                if (! $usuario->roles()->where('rol.id', $rolOpId)->exists()) {
                    $usuario->roles()->attach($rolOpId);
                }

                if (! $usuario->usuario_empresas()->where('empresa.id', $empresaId)->exists()) {
                    $usuario->usuario_empresas()->attach($empresaId);
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
