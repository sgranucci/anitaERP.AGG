<?php

namespace Database\Seeders;

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TblaprobArbolAprobacionRequisicionesSeeder extends Seeder
{
    /**
     * Datos del archivo /home/sergio/tmp/tblaprob.xlsx para la empresa 1 (BIYEMAS).
     * Cada elemento: cc = código del centro de costo, logname = usuario.usuario,
     * desde = monto desde, hasta = monto hasta.
     *
     * El nivel se asigna automáticamente en run() de 1 a N por centro de costo,
     * respetando el orden en que aparecen las filas en el Excel.
     */
    private array $filas = [
        ['cc' => '80',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '80',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 50000000],
        ['cc' => '85',  'logname' => 'aperdomo',   'desde' => 0,       'hasta' => 1000],
        ['cc' => '85',  'logname' => 'ddominguez', 'desde' => 0,       'hasta' => 1000],
        ['cc' => '85',  'logname' => 'mbmendez',   'desde' => 0,       'hasta' => 99999999],
        ['cc' => '85',  'logname' => 'aperdomo',   'desde' => 0,       'hasta' => 1000],
        ['cc' => '85',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 5000000],
        ['cc' => '85',  'logname' => 'hdattilo',   'desde' => 0,       'hasta' => 5000],
        ['cc' => '86',  'logname' => 'afernandez', 'desde' => 0,       'hasta' => 1000],
        ['cc' => '86',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 999999],
        ['cc' => '86',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '87',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 1000000],
        ['cc' => '87',  'logname' => 'gbravo',     'desde' => 0,       'hasta' => 1000],
        ['cc' => '87',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 20000000],
        ['cc' => '87',  'logname' => 'aperdomo',   'desde' => 0,       'hasta' => 5000],
        ['cc' => '87',  'logname' => 'lbivas',     'desde' => 1000000, 'hasta' => 5000000],
        ['cc' => '88',  'logname' => 'abarzola',   'desde' => 0,       'hasta' => 1000],
        ['cc' => '88',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 5000],
        ['cc' => '88',  'logname' => 'vurruchua',  'desde' => 1000,    'hasta' => 5000],
        ['cc' => '88',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '89',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 999999999],
        ['cc' => '89',  'logname' => 'vurruchua',  'desde' => 0,       'hasta' => 5000],
        ['cc' => '89',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 5000],
        ['cc' => '89',  'logname' => 'lbivas',     'desde' => 0,       'hasta' => 99999999],
        ['cc' => '90',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 99999999],
        ['cc' => '90',  'logname' => 'aperdomo',   'desde' => 0,       'hasta' => 1000],
        ['cc' => '90',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 999999],
        ['cc' => '91',  'logname' => 'babeldano',  'desde' => 1,       'hasta' => 5000],
        ['cc' => '91',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 5000],
        ['cc' => '91',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 999999],
        ['cc' => '92',  'logname' => 'eniello',    'desde' => 0,       'hasta' => 5000],
        ['cc' => '92',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 5000],
        ['cc' => '92',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 99999999],
        ['cc' => '93',  'logname' => 'amacedo',    'desde' => 0,       'hasta' => 800],
        ['cc' => '93',  'logname' => 'eperez',     'desde' => 0,       'hasta' => 5000],
        ['cc' => '93',  'logname' => 'aflorentin', 'desde' => 714,     'hasta' => 4000],
        ['cc' => '93',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 99999999],
        ['cc' => '95',  'logname' => 'ptrapani',   'desde' => 0,       'hasta' => 5000],
        ['cc' => '95',  'logname' => 'jgesualdo',  'desde' => 0,       'hasta' => 1000],
        ['cc' => '95',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '96',  'logname' => 'lsuarez',    'desde' => 0,       'hasta' => 800],
        ['cc' => '96',  'logname' => 'vurruchua',  'desde' => 0,       'hasta' => 5000],
        ['cc' => '96',  'logname' => 'mbmendez',   'desde' => 3500,    'hasta' => 100000000],
        ['cc' => '96',  'logname' => 'ffernandez', 'desde' => 800,     'hasta' => 3500],
        ['cc' => '97',  'logname' => 'gdominick',  'desde' => 0,       'hasta' => 5000],
        ['cc' => '97',  'logname' => 'mbmendez',   'desde' => 0,       'hasta' => 5000000],
        ['cc' => '97',  'logname' => 'gsurace',    'desde' => 0,       'hasta' => 1000],
        ['cc' => '97',  'logname' => 'ablanco',    'desde' => 0,       'hasta' => 1000],
        ['cc' => '97',  'logname' => 'eguevara',   'desde' => 0,       'hasta' => 1000],
        ['cc' => '98',  'logname' => 'gmagliolo',  'desde' => 0,       'hasta' => 1000],
        ['cc' => '98',  'logname' => 'ofalqui',    'desde' => 0,       'hasta' => 99999999],
        ['cc' => '98',  'logname' => 'liglesias',  'desde' => 0,       'hasta' => 1000],
        ['cc' => '98',  'logname' => 'mbmendez',   'desde' => 0,       'hasta' => 99999999],
        ['cc' => '98',  'logname' => 'gdominick',  'desde' => 0,       'hasta' => 9999],
        ['cc' => '99',  'logname' => 'vurruchua',  'desde' => 0,       'hasta' => 5000],
        ['cc' => '99',  'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '100', 'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '100', 'logname' => 'mbmendez',   'desde' => 1000,    'hasta' => 5000],
        ['cc' => '100', 'logname' => 'rfogliatti', 'desde' => 0,       'hasta' => 1000],
        ['cc' => '101', 'logname' => 'vurruchua',  'desde' => 0,       'hasta' => 5000],
        ['cc' => '101', 'logname' => 'nperez',     'desde' => 0,       'hasta' => 1000],
        ['cc' => '101', 'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
        ['cc' => '101', 'logname' => 'lsuarez',    'desde' => 0,       'hasta' => 50000],
        ['cc' => '102', 'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 999999],
        ['cc' => '102', 'logname' => 'gmurua',     'desde' => 0,       'hasta' => 99999],
        ['cc' => '103', 'logname' => 'mbmendez',   'desde' => 1000,    'hasta' => 9999],
        ['cc' => '103', 'logname' => 'gbravo',     'desde' => 0,       'hasta' => 5000],
        ['cc' => '104', 'logname' => 'lsuarez',    'desde' => 0,       'hasta' => 5000],
        ['cc' => '104', 'logname' => 'mbmendez',   'desde' => 0,       'hasta' => 9999],
        ['cc' => '104', 'logname' => 'gdominick',  'desde' => 0,       'hasta' => 999999],
        ['cc' => '105', 'logname' => 'mbmendez',   'desde' => 0,       'hasta' => 50000000],
        ['cc' => '105', 'logname' => 'mbmendez',   'desde' => 5000,    'hasta' => 9999],
    ];

    public function run(): void
    {
        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Requisiciones')
            ->where('empresa_id', 1)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            throw new \RuntimeException('No se encontró el árbol de aprobación de Requisiciones para la empresa 1.');
        }

        $monedaPesos = (int) DB::table('moneda')->where('nombre', 'PESOS')->value('id');
        if (! $monedaPesos) {
            throw new \RuntimeException('No se encontró la moneda PESOS.');
        }

        $centroCostoPorCodigo = DB::table('centrocosto')->pluck('id', 'codigo');
        $usuarioPorLogname = DB::table('usuario')->pluck('id', 'usuario');

        $insertados = 0;
        $actualizados = 0;
        $omitidosFaltantes = 0;
        $contadorNivelPorCc = [];
        $vistos = [];

        foreach ($this->filas as $fila) {
            $centroCostoId = $centroCostoPorCodigo[$fila['cc']] ?? null;
            $usuarioId = $usuarioPorLogname[$fila['logname']] ?? null;

            if (! $centroCostoId || ! $usuarioId) {
                $omitidosFaltantes++;
                $this->command->warn(
                    "Saltando fila: cc={$fila['cc']} logname={$fila['logname']} (centrocosto o usuario no existen)"
                );

                continue;
            }

            // Deduplicar dentro del Excel: misma combinación cc + usuario + desde + hasta no se reasigna nivel.
            $claveDedup = $centroCostoId.'|'.$usuarioId.'|'.$fila['desde'].'|'.$fila['hasta'];
            if (isset($vistos[$claveDedup])) {
                continue;
            }
            $vistos[$claveDedup] = true;

            $contadorNivelPorCc[$centroCostoId] = ($contadorNivelPorCc[$centroCostoId] ?? 0) + 1;
            $nivel = $contadorNivelPorCc[$centroCostoId];

            $registro = Arbolaprobacion_Nivel::where('arbolaprobacion_id', $arbol->id)
                ->where('centrocosto_id', $centroCostoId)
                ->where('usuario_id', $usuarioId)
                ->where('desdemonto', $fila['desde'])
                ->where('hastamonto', $fila['hasta'])
                ->where('moneda_id', $monedaPesos)
                ->first();

            if ($registro) {
                if ((int) $registro->nivel !== $nivel) {
                    $registro->update(['nivel' => $nivel]);
                    $actualizados++;
                }

                continue;
            }

            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => $arbol->id,
                'centrocosto_id' => $centroCostoId,
                'nivel' => $nivel,
                'usuario_id' => $usuarioId,
                'desdemonto' => $fila['desde'],
                'hastamonto' => $fila['hasta'],
                'moneda_id' => $monedaPesos,
                'requisicion_estado_al_aprobar' => null,
            ]);
            $insertados++;
        }

        $this->command->info("Niveles insertados: {$insertados}");
        $this->command->info("Niveles actualizados (renumerados): {$actualizados}");
        $this->command->info("Niveles omitidos por datos faltantes: {$omitidosFaltantes}");
    }
}
