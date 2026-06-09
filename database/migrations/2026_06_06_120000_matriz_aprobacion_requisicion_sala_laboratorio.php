<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EMPRESA_ID = 1;

    private const TIPO_ARBOL = 'Requisiciones de sala';

    private const MONTO_HASTA_TM = 100000;

    private const MONTO_DESDE_GTE = 100001;

    private const MONTO_HASTA_GTE = 500000;

    private const MONTO_DESDE_DIR = 500001;

    private const MONTO_HASTA_MAX = 999999999;

    private const ESTADO_A_COMPRAS = 'A COMPRAS';

    private const ESTADO_A_AUTORIZAR = 'A AUTORIZAR';

    private const ESTADO_APROBADA = 'APROBADA';

    /**
     * Matriz inicial de aprobación de requisiciones de sala — Laboratorio / Técnica.
     *
     * CC 93 Tecnica: circuito laboratorio (rvaldez, evillagra, mbmendez).
     * CC 91 Obras y Mantenimiento: babeldano + mbmendez.
     * CC 89 Maquinas: sala / máquinas (vurruchua, mbmendez).
     */
    private array $matrizPorCc = [
        '93' => [
            'enc' => ['rvaldez'],
            'sup' => ['evillagra'],
            'dir' => ['mbmendez'],
        ],
        '91' => [
            'enc' => ['babeldano'],
            'dir' => ['mbmendez'],
        ],
        '89' => [
            'enc' => ['vurruchua'],
            'dir' => ['mbmendez'],
        ],
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $monedaPesos = (int) DB::table('moneda')->where('nombre', 'PESOS')->value('id');
        if (! $monedaPesos) {
            throw new \RuntimeException('No se encontró la moneda PESOS.');
        }

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            $arbolId = (int) DB::table('arbolaprobacion')->insertGetId([
                'nombre' => 'Requisiciones de sala',
                'tipoarbol' => self::TIPO_ARBOL,
                'empresa_id' => self::EMPRESA_ID,
                'recordatorio' => 'S',
                'diasinrespuesta' => 5,
                'diavencimientorecordatorio' => 5,
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $arbolId = (int) $arbol->id;
        }

        $now = now()->toDateTimeString();
        DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbolId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        $centroCostoPorCodigo = DB::table('centrocosto')->pluck('id', 'codigo');
        $usuarioPorLogname = DB::table('usuario')->pluck('id', 'usuario');
        $insertados = 0;
        $omitidos = [];

        foreach ($this->matrizPorCc as $codigoCc => $bandas) {
            $centroCostoId = $centroCostoPorCodigo[$codigoCc] ?? null;
            if (! $centroCostoId) {
                $omitidos[] = "CC {$codigoCc}: centro de costo inexistente";

                continue;
            }

            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $centroCostoId,
                'nivel' => 1,
                'usuario_id' => null,
                'desdemonto' => 0,
                'hastamonto' => self::MONTO_HASTA_MAX,
                'moneda_id' => $monedaPesos,
                'documento_estado_al_aprobar' => self::ESTADO_A_COMPRAS,
            ]);
            $insertados++;

            $nivel = 2;
            $nivel = $this->insertarBanda(
                $arbolId, $centroCostoId, $monedaPesos, $nivel,
                $bandas['enc'] ?? [], 0, self::MONTO_HASTA_TM,
                $usuarioPorLogname, $omitidos, $codigoCc, 'Encargado', self::ESTADO_A_AUTORIZAR, $insertados
            );
            if (isset($bandas['sup'])) {
                $nivel = $this->insertarBanda(
                    $arbolId, $centroCostoId, $monedaPesos, $nivel,
                    $bandas['sup'], self::MONTO_DESDE_GTE, self::MONTO_HASTA_GTE,
                    $usuarioPorLogname, $omitidos, $codigoCc, 'Supervisor', self::ESTADO_A_AUTORIZAR, $insertados
                );
            }
            $this->insertarBanda(
                $arbolId, $centroCostoId, $monedaPesos, $nivel,
                $bandas['dir'] ?? [], self::MONTO_DESDE_DIR, self::MONTO_HASTA_MAX,
                $usuarioPorLogname, $omitidos, $codigoCc, 'Director', self::ESTADO_APROBADA, $insertados
            );
        }

        if ($omitidos !== []) {
            throw new \RuntimeException(
                "Árbol requisiciones de sala: {$insertados} niveles insertados, pero faltan usuarios:\n".implode("\n", array_unique($omitidos))
            );
        }
    }

    public function down(): void
    {
        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            return;
        }

        $now = now()->toDateTimeString();
        DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbol->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    private function insertarBanda(
        int $arbolId,
        int $centroCostoId,
        int $monedaId,
        int $nivel,
        array $lognames,
        float $desde,
        float $hasta,
        $usuarioPorLogname,
        array &$omitidos,
        string $codigoCc,
        string $rolLabel,
        string $estadoAlAprobar,
        int &$insertados
    ): int {
        foreach ($lognames as $logname) {
            $usuarioId = (int) ($usuarioPorLogname[$logname] ?? 0);
            if ($usuarioId <= 0) {
                $omitidos[] = "CC {$codigoCc} {$rolLabel}: usuario {$logname} inexistente";

                continue;
            }
            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $centroCostoId,
                'nivel' => $nivel,
                'usuario_id' => $usuarioId,
                'desdemonto' => $desde,
                'hastamonto' => $hasta,
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => $estadoAlAprobar,
            ]);
            $insertados++;
            $nivel++;
        }

        return $nivel;
    }
};
