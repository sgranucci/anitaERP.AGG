<?php

namespace Database\Seeders;

use App\Models\Admin\Rol;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Sala\TecnicoLaboratorio;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * 1) Técnicos actuales en tecnico_laboratorio → usuarios Tecnico-sala (CC 93, clave 12345).
 * 2) Vacía tecnico_laboratorio.
 * 3) Importa planilla USUARIOS LABORATORIO.xlsx.
 * 4) Crea usuarios Op-Laboratorio para los técnicos importados.
 */
class MigracionTecnicosLaboratorioSalaSeeder extends Seeder
{
    private const EXCEL_PATH = '/home/sergio/tmp/USUARIOS LABORATORIO.xlsx';

    private const EMPRESAS_PRINCIPALES = ['BIYEMAS S.A.', 'KANDIKO S.A.', 'REBISCO S.A.'];

    public function run(): void
    {
        $centrocostoId = Centrocosto::where('codigo', 93)->value('id');
        if (! $centrocostoId) {
            throw new \RuntimeException('No se encontró el centro de costo con código 93.');
        }

        $rolTecnicoSalaId = Rol::where('nombre', 'Tecnico-sala')->value('id');
        if (! $rolTecnicoSalaId) {
            throw new \RuntimeException('No se encontró el rol Tecnico-sala.');
        }

        $rolOpLaboratorioId = Rol::where('nombre', 'Op-Laboratorio')->value('id');
        if (! $rolOpLaboratorioId) {
            throw new \RuntimeException('No se encontró el rol Op-Laboratorio.');
        }

        $empresaIds = [];
        foreach (self::EMPRESAS_PRINCIPALES as $nombreEmpresa) {
            $id = Empresa::where('nombre', $nombreEmpresa)->value('id');
            if (! $id) {
                throw new \RuntimeException("No se encontró la empresa $nombreEmpresa.");
            }
            $empresaIds[] = (int) $id;
        }

        $empresaDefaultLabId = $empresaIds[0];

        if (! is_readable(self::EXCEL_PATH)) {
            throw new \RuntimeException('No se puede leer el archivo: '.self::EXCEL_PATH);
        }

        $resumen = [
            'sala_creados' => [],
            'sala_existentes' => [],
            'lab_importados' => [],
            'lab_creados' => [],
            'lab_existentes' => [],
        ];

        DB::transaction(function () use (
            $centrocostoId,
            $rolTecnicoSalaId,
            $rolOpLaboratorioId,
            $empresaIds,
            $empresaDefaultLabId,
            &$resumen
        ) {
            $tecnicosSala = TecnicoLaboratorio::orderBy('id')->get();

            foreach ($tecnicosSala as $tecnico) {
                [$apellido, $nombre] = $this->parseNombreApellido($tecnico->nombre);
                $this->asegurarUsuario(
                    $apellido,
                    $nombre,
                    $centrocostoId,
                    $rolTecnicoSalaId,
                    [(int) $tecnico->empresa_id],
                    $resumen['sala_creados'],
                    $resumen['sala_existentes'],
                );
            }

            DB::table('tecnico_laboratorio')->delete();

            $filasExcel = $this->leerExcel();
            foreach ($filasExcel as [$nombreCompleto, $cuit]) {
                $tecnico = TecnicoLaboratorio::create([
                    'empresa_id' => $empresaDefaultLabId,
                    'nombre' => $this->normalizarNombreCompleto($nombreCompleto),
                    'legajo' => $this->legajoDesdeCuit($cuit),
                    'activo' => 'S',
                ]);
                $resumen['lab_importados'][] = "id={$tecnico->id} {$tecnico->nombre}".($cuit ? " (CUIT $cuit)" : '');

                [$apellido, $nombre] = $this->parseNombreApellido($tecnico->nombre);
                $this->asegurarUsuario(
                    $apellido,
                    $nombre,
                    $centrocostoId,
                    $rolOpLaboratorioId,
                    $empresaIds,
                    $resumen['lab_creados'],
                    $resumen['lab_existentes'],
                );
            }
        });

        $this->imprimirResumen($resumen);
    }

    /** @return list<array{0: string, 1: ?string}> */
    private function leerExcel(): array
    {
        $sheet = IOFactory::load(self::EXCEL_PATH)->getActiveSheet();
        $filas = [];

        foreach ($sheet->getRowIterator() as $row) {
            $nombre = trim((string) $sheet->getCell('A'.$row->getRowIndex())->getValue());
            $cuit = trim((string) $sheet->getCell('B'.$row->getRowIndex())->getValue());
            if ($nombre === '') {
                continue;
            }
            $filas[] = [$nombre, $cuit !== '' ? $cuit : null];
        }

        if ($filas === []) {
            throw new \RuntimeException('La planilla no contiene técnicos de laboratorio.');
        }

        return $filas;
    }

    /** @param list<int> $empresaIds */
    private function asegurarUsuario(
        string $apellido,
        string $nombre,
        int $centrocostoId,
        int $rolId,
        array $empresaIds,
        array &$creados,
        array &$existentes,
    ): void {
        $login = $this->resolverLogin($nombre, $apellido);
        $email = $login.'@grupoagg.com';
        $nombreCompleto = $this->capitalizar($nombre).' '.$this->capitalizar($apellido);

        $usuario = Usuario::where('usuario', $login)->orWhere('email', $email)->first();

        if ($usuario) {
            $existentes[] = "$login ($nombreCompleto) id={$usuario->id}";
        } else {
            $usuario = Usuario::create([
                'usuario' => $login,
                'nombre' => $nombreCompleto,
                'email' => $email,
                'password' => '12345',
                'centrocosto_id' => $centrocostoId,
            ]);
            $creados[] = "$login ($nombreCompleto) id={$usuario->id}";
        }

        if ((int) $usuario->centrocosto_id !== $centrocostoId) {
            $usuario->centrocosto_id = $centrocostoId;
            $usuario->save();
        }

        if (! $usuario->roles()->where('rol.id', $rolId)->exists()) {
            $usuario->roles()->attach($rolId);
        }

        foreach ($empresaIds as $empresaId) {
            if (! $usuario->usuario_empresas()->where('empresa.id', $empresaId)->exists()) {
                $usuario->usuario_empresas()->attach($empresaId);
            }
        }
    }

    /** @return array{0: string, 1: string} */
    private function parseNombreApellido(string $nombreCompleto): array
    {
        $partes = preg_split('/\s+/u', trim($nombreCompleto)) ?: [];
        $partes = array_values(array_filter($partes, static fn ($p) => $p !== ''));

        if ($partes === []) {
            return ['SIN_APELLIDO', 'SIN_NOMBRE'];
        }

        if (count($partes) === 1) {
            return [$partes[0], ''];
        }

        $apellido = array_shift($partes);

        return [$apellido, implode(' ', $partes)];
    }

    private function normalizarNombreCompleto(string $valor): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $valor) ?? ''), 'UTF-8');
    }

    private function legajoDesdeCuit(?string $cuit): ?int
    {
        if ($cuit === null || $cuit === '') {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $cuit);
        if ($digitos === null || $digitos === '') {
            return null;
        }

        $legajo = (int) substr($digitos, -8);

        return $legajo > 0 ? $legajo : null;
    }

    private function resolverLogin(string $nombre, string $apellido): string
    {
        $candidatos = [
            $this->normalizarLogin(mb_substr($nombre, 0, 1).$apellido),
            $this->normalizarLogin($apellido),
        ];

        foreach ($candidatos as $login) {
            if ($login === '') {
                continue;
            }
            $existente = Usuario::where('usuario', $login)->first();
            if (! $existente) {
                return $login;
            }
            $nombreEsperado = $this->capitalizar($nombre).' '.$this->capitalizar($apellido);
            if (mb_strtolower(trim($existente->nombre)) === mb_strtolower(trim($nombreEsperado))) {
                return $login;
            }
        }

        $base = $candidatos[1] !== '' ? $candidatos[1] : $candidatos[0];
        $sufijo = 2;
        while (Usuario::where('usuario', $base.$sufijo)->exists()) {
            $sufijo++;
        }

        return $base.$sufijo;
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

        return preg_replace('/[^a-z0-9]/', '', $valor) ?? '';
    }

    private function capitalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor), 'UTF-8');

        return mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
    }

    /** @param array<string, list<string>> $resumen */
    private function imprimirResumen(array $resumen): void
    {
        foreach ([
            'Usuarios Tecnico-sala creados' => $resumen['sala_creados'],
            'Usuarios Tecnico-sala existentes (actualizados)' => $resumen['sala_existentes'],
            'Técnicos laboratorio importados' => $resumen['lab_importados'],
            'Usuarios Op-Laboratorio creados' => $resumen['lab_creados'],
            'Usuarios Op-Laboratorio existentes (actualizados)' => $resumen['lab_existentes'],
        ] as $titulo => $lineas) {
            $this->command?->info("--- $titulo ---");
            foreach ($lineas as $linea) {
                $this->command?->line($linea);
            }
        }
    }
}
