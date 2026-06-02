<?php

use App\Models\Admin\Rol;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Usuarios gastronomía Kandiko (empresa_id = 2) según planilla operativa AGG.
 *
 * Convenciones (igual que UsuariosGastronomiaBiyemasSeeder):
 *  - usuario  = inicial(nombre) + apellido (minúsculas, sin acentos/ñ/espacios)
 *  - email    = {usuario}@grupoagg.com
 *  - password = 12345 (solo al crear)
 *  - centrocosto_id = centro con código 85
 *  - empresa  = KANDIKO S.A. (id 2)
 *
 * Puesto → rol:
 *  - CAJERO/A, CAMARERO     → Op-Gastronomia
 *  - LIDER                  → Sup-Gastronomia
 *  - Marcela Moskaluc       → Enc-gastronomía
 */
return new class extends Migration
{
    private const EMPRESA_ID = 2;

    /** @var list<array{0: string, 1: string, 2: string}> */
    private const PERSONAS = [
        ['FIGUEROA', 'JOSE', 'CAJERO/A'],
        ['CRESPO', 'CAMILA', 'CAJERO/A'],
        ['CASTILLO', 'MARINA', 'CAJERO/A'],
        ['SANCHEZ MOYA', 'CRISTIAN', 'CAJERO/A'],
        ['MARTINEZ', 'VALERIA', 'CAJERO/A'],
        ['RAMOS', 'PAULA', 'CAMARERO'],
        ['FERNANDEZ', 'MARIANO', 'CAMARERO'],
        ['AVILA', 'LEONARDO', 'CAMARERO'],
        ['MEDRUIN', 'JUAN MANUEL', 'CAMARERO'],
        ['MOSKALUC', 'MARCELA', 'LIDER_ENC'],
        ['MERCALI', 'DIEGO', 'LIDER'],
        ['ROJAS', 'ANDREA', 'LIDER'],
        ['CANCELA', 'LAURA', 'LIDER'],
        ['ROLON', 'SERGIO', 'LIDER'],
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $centrocostoId = (int) (Centrocosto::where('codigo', 85)->value('id') ?? 0);
        if ($centrocostoId === 0) {
            throw new \RuntimeException('No se encontró el centro de costo con código 85.');
        }

        if (! DB::table('empresa')->where('id', self::EMPRESA_ID)->exists()) {
            throw new \RuntimeException('No se encontró la empresa con id '.self::EMPRESA_ID.' (KANDIKO S.A.).');
        }

        $rolEnc = $this->resolverRolEncId();
        $rolSup = (int) (Rol::where('nombre', 'Sup-Gastronomia')->value('id') ?? 0);
        $rolOp = (int) (Rol::where('nombre', 'Op-Gastronomia')->value('id') ?? 0);

        foreach (['Enc' => $rolEnc, 'Sup' => $rolSup, 'Op' => $rolOp] as $etiqueta => $rolId) {
            if ($rolId === 0) {
                throw new \RuntimeException("No se encontró el rol Gastronomía ($etiqueta).");
            }
        }

        $mapaRolPorPuesto = [
            'CAJERO/A' => $rolOp,
            'CAMARERO' => $rolOp,
            'LIDER' => $rolSup,
            'LIDER_ENC' => $rolEnc,
        ];

        $rolesGastronomia = array_values(array_unique([$rolEnc, $rolSup, $rolOp]));

        DB::transaction(function () use ($mapaRolPorPuesto, $centrocostoId, $rolesGastronomia) {
            foreach (self::PERSONAS as [$apellido, $nombre, $puesto]) {
                $apellido = trim($apellido);
                $nombre = trim($nombre);
                $puesto = trim($puesto);

                $login = $this->normalizarLogin(mb_substr($nombre, 0, 1).$apellido);
                $email = $login.'@grupoagg.com';
                $nombreCompleto = $this->capitalizar($nombre).' '.$this->capitalizar($apellido);
                $rolId = $mapaRolPorPuesto[$puesto] ?? null;

                if (! $rolId) {
                    throw new \RuntimeException("Puesto no mapeado para $nombreCompleto: $puesto");
                }

                $usuario = Usuario::where('usuario', $login)->orWhere('email', $email)->first();

                if (! $usuario) {
                    $usuario = Usuario::create([
                        'usuario' => $login,
                        'nombre' => $nombreCompleto,
                        'email' => $email,
                        'password' => '12345',
                        'centrocosto_id' => $centrocostoId,
                    ]);
                } else {
                    if ((int) $usuario->centrocosto_id !== $centrocostoId) {
                        $usuario->centrocosto_id = $centrocostoId;
                        $usuario->save();
                    }
                }

                $rolIdsActualesGastro = $usuario->roles()
                    ->whereIn('rol.id', $rolesGastronomia)
                    ->pluck('rol.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($rolIdsActualesGastro !== [] && ! in_array($rolId, $rolIdsActualesGastro, true)) {
                    $usuario->roles()->detach($rolIdsActualesGastro);
                }

                if (! $usuario->roles()->where('rol.id', $rolId)->exists()) {
                    $usuario->roles()->attach($rolId);
                }

                if (! $usuario->usuario_empresas()->where('empresa.id', self::EMPRESA_ID)->exists()) {
                    $usuario->usuario_empresas()->attach(self::EMPRESA_ID);
                }
            }
        });
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $logins = array_map(function (array $persona): string {
            [$apellido, $nombre] = $persona;

            return $this->normalizarLogin(mb_substr(trim($nombre), 0, 1).trim($apellido));
        }, self::PERSONAS);

        $usuarioIds = DB::table('usuario')->whereIn('usuario', $logins)->pluck('id');

        if ($usuarioIds->isEmpty()) {
            return;
        }

        DB::table('usuario_rol')->whereIn('usuario_id', $usuarioIds)->delete();
        DB::table('usuario_empresa')->whereIn('usuario_id', $usuarioIds)->delete();
        DB::table('usuario')->whereIn('id', $usuarioIds)->delete();
    }

    private function resolverRolEncId(): int
    {
        return (int) (Rol::whereRaw('LOWER(nombre) = ?', ['enc-gastronomía'])->value('id')
            ?? Rol::whereRaw('LOWER(nombre) = ?', ['enc-gastronomia'])->value('id')
            ?? 0);
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
};
