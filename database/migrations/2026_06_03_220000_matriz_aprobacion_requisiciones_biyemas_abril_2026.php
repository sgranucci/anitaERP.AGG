<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EMPRESA_ID = 1;

    private const MONTO_HASTA_TM = 1000000;

    private const MONTO_DESDE_GTE = 1000001;

    private const MONTO_HASTA_GTE = 5000000;

    private const MONTO_DESDE_GF = 5000001;

    private const MONTO_HASTA_GF = 50000000;

    private const MONTO_DESDE_DIR = 50000001;

    private const MONTO_HASTA_MAX = 999999999;

    private const ESTADO_EN_COMPRAS = 'EN COMPRAS';

    private const ESTADO_APROBADA = 'APROBADA';

    /**
     * Matriz «Matriz Aprobacion Requi - Abril 2026.xlsx» — empresa BIYEMAS.
     *
     * Nivel 1 (EN COMPRAS): usuario en blanco salvo CC 91 (mbmendez).
     * Niveles 2–6: Team Manager, Teams Leader, Gte, GF, Dir según montos de la matriz.
     */
    private array $matrizPorCc = [
        '80' => ['dir' => ['MB Mendez'], 'gf' => ['Toscano', 'Bivas', 'Murua', 'Urruchua'], 'gte' => ['Toscano', 'Bivas', 'Murua', 'Urruchua']],
        '85' => ['dir' => ['MB Mendez'], 'gf' => ['Hernán Dattilo'], 'gte' => ['Dominguez', 'Chavez', 'Moskaluk'], 'tm' => ['Dominguez', 'Chavez', 'Moskaluk']],
        '86' => ['dir' => ['MB Mendez'], 'gf' => ['Toscano', 'Bivas', 'Murua'], 'gte' => ['Fernandez', 'Castellani', 'Acuña'], 'tm' => ['Fernandez', 'Castellani', 'Acuña']],
        '87' => ['dir' => ['MB Mendez'], 'gf' => ['Toscano', 'Bivas', 'Murua'], 'gte' => ['Gastón Bravo']],
        '88' => ['dir' => ['MB Mendez'], 'gf' => ['Toscano', 'Bivas', 'Murua'], 'gte' => ['Alejandra Barzola'], 'tm' => ['Alejandra Barzola']],
        '89' => ['dir' => ['MB Mendez'], 'gf' => ['Urruchua', 'Toscano', 'Bivas', 'Murua'], 'gte' => ['Urruchua', 'Toscano', 'Bivas', 'Murua']],
        '90' => ['dir' => ['MB Mendez'], 'gf' => ['Erika Perez'], 'gte' => ['Adriana Perdomo']],
        '91' => ['dir' => ['MB Mendez'], 'gf' => ['Belen Abeldaño'], 'gte' => ['Belen Abeldaño'], 'tm' => ['Belen Abeldaño']],
        '92' => ['dir' => ['MB Mendez'], 'gf' => ['Esteban Niello'], 'gte' => ['Esteban Niello'], 'tm' => ['Esteban Niello']],
        '93' => ['dir' => ['MB Mendez'], 'gf' => ['Angel Florentin'], 'gte' => ['Alejandro Macedo'], 'tm' => ['Alejandro Macedo']],
        '95' => ['dir' => ['MB Mendez'], 'gf' => ['Paula Trapani'], 'gte' => ['Jimena Gesualdo']],
        '96' => ['dir' => ['MB Mendez'], 'gf' => ['Florencia Fernandez'], 'gte' => ['Suarez'], 'tm' => ['Suarez']],
        '97' => ['dir' => ['MB Mendez'], 'gf' => ['Griselda Dominick'], 'gte' => ['Surace', 'Guevara', 'Blanco'], 'tm' => ['Surace', 'Guevara', 'Blanco']],
        '98' => ['dir' => ['MB Mendez'], 'gf' => ['MB Mendez', 'Falqui'], 'gte' => ['Magliolo', 'Iglesias'], 'tm' => ['Magliolo', 'Iglesias']],
        '99' => ['dir' => ['MB Mendez'], 'gf' => ['Urruchua', 'Toscano', 'Bivas', 'Murua'], 'gte' => ['Urruchua', 'Toscano', 'Bivas', 'Murua']],
        '100' => ['dir' => ['MB Mendez'], 'gf' => ['MB Mendez'], 'gte' => ['Fogliatti']],
        '101' => ['dir' => ['MB Mendez'], 'gf' => ['Valeria Urruchua'], 'gte' => ['Valeria Urruchua'], 'tm' => ['Natalia Perez']],
        '102' => ['dir' => ['MB Mendez'], 'gf' => ['Murua', 'Bivas', 'Toscano'], 'gte' => ['Melisa Rodriguez']],
        '103' => ['dir' => ['MB Mendez'], 'gf' => ['MB Mendez'], 'gte' => ['Gaston Bravo']],
        '104' => ['dir' => ['MB Mendez'], 'gf' => ['Florencia Fernandez'], 'gte' => ['Laura Suarez'], 'tm' => ['Laura Suarez']],
        '105' => ['dir' => ['MB Mendez'], 'gf' => ['MB Mendez'], 'gte' => ['MB Mendez']],
    ];

    /** Alias del Excel → login usuario.usuario */
    private array $lognamePorAlias = [
        'MB Mendez' => 'mbmendez',
        'Hernán Dattilo' => 'hdattilo',
        'Hernan Dattilo' => 'hdattilo',
        'Dominguez' => 'ddominguez',
        'Chavez' => 'wchavez',
        'Moskaluk' => 'mmoskaluc',
        'Toscano' => 'ftoscano',
        'Bivas' => 'lbivas',
        'Murua' => 'gmurua',
        'Urruchua' => 'vurruchua',
        'Valeria Urruchua' => 'vurruchua',
        'Fernandez' => 'afernandez',
        'Castellani' => 'acastellani',
        'Acuña' => 'aacuna',
        'Gastón Bravo' => 'gbravo',
        'Gaston Bravo' => 'gbravo',
        'Alejandra Barzola' => 'abarzola',
        'Erika Perez' => 'eperez',
        'Adriana Perdomo' => 'aperdomo',
        'Belen Abeldaño' => 'babeldano',
        'Esteban Niello' => 'eniello',
        'Angel Florentin' => 'aflorentin',
        'Alejandro Macedo' => 'amacedo',
        'Paula Trapani' => 'ptrapani',
        'Jimena Gesualdo' => 'jgesualdo',
        'Florencia Fernandez' => 'ffernandez',
        'Suarez' => 'lsuarez',
        'Laura Suarez' => 'lsuarez',
        'Griselda Dominick' => 'gdominick',
        'Surace' => 'gsurace',
        'Guevara' => 'eguevara',
        'Blanco' => 'ablanco',
        'Falqui' => 'ofalqui',
        'Magliolo' => 'gmagliolo',
        'Iglesias' => 'liglesias',
        'Fogliatti' => 'rfogliatti',
        'Natalia Perez' => 'nperez',
        'Melisa Rodriguez' => 'mrodriguez',
    ];

    /** Usuarios referenciados en la matriz que pueden no existir aún. */
    private array $usuariosAlta = [
        ['ftoscano', 'TOSCANO FERNANDO'],
        ['acastellani', 'CASTELLANI PAOLA'],
        ['aacuna', 'ACUNIA MAXIMILIANO'],
        ['mrodriguez', 'RODRIGUEZ MELISA'],
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Requisiciones')
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            throw new \RuntimeException('No se encontró el árbol de aprobación de Requisiciones para BIYEMAS (empresa '.self::EMPRESA_ID.').');
        }

        $monedaPesos = (int) DB::table('moneda')->where('nombre', 'PESOS')->value('id');
        if (! $monedaPesos) {
            throw new \RuntimeException('No se encontró la moneda PESOS.');
        }

        $empresaBiyemas = (int) DB::table('empresa')
            ->where('nombre', 'like', 'BIYEMAS%')
            ->value('id');

        $this->crearUsuariosFaltantes($empresaBiyemas);

        $usuarioPorLogname = DB::table('usuario')->pluck('id', 'usuario');
        $centroCostoPorCodigo = DB::table('centrocosto')->pluck('id', 'codigo');

        $now = now()->toDateTimeString();
        DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbol->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        $insertados = 0;
        $omitidos = [];

        foreach ($this->matrizPorCc as $codigoCc => $bandas) {
            $centroCostoId = $centroCostoPorCodigo[$codigoCc] ?? null;
            if (! $centroCostoId) {
                $omitidos[] = "CC {$codigoCc}: centro de costo inexistente";

                continue;
            }

            $usuarioEnCompras = null;
            if ($codigoCc === '91') {
                $usuarioEnCompras = (int) ($usuarioPorLogname->get('mbmendez') ?? 0) ?: null;
            }

            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => $arbol->id,
                'centrocosto_id' => $centroCostoId,
                'nivel' => 1,
                'usuario_id' => $usuarioEnCompras,
                'desdemonto' => 0,
                'hastamonto' => self::MONTO_HASTA_MAX,
                'moneda_id' => $monedaPesos,
                'documento_estado_al_aprobar' => self::ESTADO_EN_COMPRAS,
            ]);
            $insertados++;

            $nivel = 2;
            $nivel = $this->insertarBandaUsuarios(
                $arbol->id, $centroCostoId, $monedaPesos, $nivel,
                $bandas['tm'] ?? [], 0, self::MONTO_HASTA_TM,
                $usuarioPorLogname, $omitidos, $codigoCc, 'Team Manager', $insertados
            );
            $nivel = $this->insertarBandaUsuarios(
                $arbol->id, $centroCostoId, $monedaPesos, $nivel,
                $bandas['tl'] ?? [], 0, self::MONTO_HASTA_TM,
                $usuarioPorLogname, $omitidos, $codigoCc, 'Teams Leader', $insertados
            );
            $nivel = $this->insertarBandaUsuarios(
                $arbol->id, $centroCostoId, $monedaPesos, $nivel,
                $bandas['gte'] ?? [], self::MONTO_DESDE_GTE, self::MONTO_HASTA_GTE,
                $usuarioPorLogname, $omitidos, $codigoCc, 'Gte', $insertados
            );
            $nivel = $this->insertarBandaUsuarios(
                $arbol->id, $centroCostoId, $monedaPesos, $nivel,
                $bandas['gf'] ?? [], self::MONTO_DESDE_GF, self::MONTO_HASTA_GF,
                $usuarioPorLogname, $omitidos, $codigoCc, 'GF', $insertados
            );
            $this->insertarBandaUsuarios(
                $arbol->id, $centroCostoId, $monedaPesos, $nivel,
                $bandas['dir'] ?? [], self::MONTO_DESDE_DIR, self::MONTO_HASTA_MAX,
                $usuarioPorLogname, $omitidos, $codigoCc, 'Dir', $insertados
            );
        }

        if ($omitidos !== []) {
            throw new \RuntimeException(
                "Árbol cargado con {$insertados} niveles, pero faltan usuarios:\n".implode("\n", array_unique($omitidos))
            );
        }
    }

    public function down(): void
    {
        // Restauración manual: volver a ejecutar TblaprobArbolAprobacionRequisicionesSeeder si hiciera falta.
    }

    private function crearUsuariosFaltantes(int $empresaId): void
    {
        foreach ($this->usuariosAlta as [$logname, $nombre]) {
            if (DB::table('usuario')->where('usuario', $logname)->exists()) {
                continue;
            }

            $usuario = Usuario::create([
                'usuario' => $logname,
                'nombre' => $nombre,
                'email' => "{$logname}@grupoagg.com",
                'password' => '12345',
            ]);

            if ($empresaId > 0) {
                $yaAsignado = DB::table('usuario_empresa')
                    ->where('usuario_id', $usuario->id)
                    ->where('empresa_id', $empresaId)
                    ->exists();

                if (! $yaAsignado) {
                    DB::table('usuario_empresa')->insert([
                        'usuario_id' => $usuario->id,
                        'empresa_id' => $empresaId,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, int|string>  $usuarioPorLogname
     * @param  list<string>  $omitidos
     * @param  list<string>  $aliasUsuarios
     */
    private function insertarBandaUsuarios(
        int $arbolId,
        int $centroCostoId,
        int $monedaId,
        int $nivel,
        array $aliasUsuarios,
        float $desde,
        float $hasta,
        $usuarioPorLogname,
        array &$omitidos,
        string $codigoCc,
        string $etiquetaBanda,
        int &$insertados
    ): int {
        if ($aliasUsuarios === []) {
            return $nivel;
        }

        $uids = [];
        foreach ($aliasUsuarios as $alias) {
            $logname = $this->lognamePorAlias[$alias] ?? null;
            if ($logname === null) {
                $omitidos[] = "CC {$codigoCc} {$etiquetaBanda}: alias «{$alias}» sin mapeo";

                continue;
            }
            $uid = $usuarioPorLogname[$logname] ?? null;
            if (! $uid) {
                $omitidos[] = "CC {$codigoCc} {$etiquetaBanda}: usuario «{$logname}» inexistente";

                continue;
            }
            $uids[(int) $uid] = true;
        }

        if ($uids === []) {
            return $nivel;
        }

        foreach (array_keys($uids) as $usuarioId) {
            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $centroCostoId,
                'nivel' => $nivel,
                'usuario_id' => $usuarioId,
                'desdemonto' => $desde,
                'hastamonto' => $hasta,
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => self::ESTADO_APROBADA,
            ]);
            $insertados++;
        }

        return $nivel + 1;
    }
};
