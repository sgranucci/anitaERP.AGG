<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: árbol de requisiciones por CC — nivel 1 firmantes de sala → EN COMPRAS.
 *
 * Sin nivel posterior: Compras con «Envía al árbol» cierra en APROBADA (circuito_completo).
 * Varios firmantes del mismo CC: primera firma gana (doble_aprobacion = N).
 */
return new class extends Migration
{
    private const EMPRESA_ID = 1;

    private const TIPO_ARBOL = 'Requisiciones';

    private const NOMBRE_ARBOL = 'Requisiciones salas Crown';

    private const ESTADO_ARBOL = 'Activo';

    private const ESTADO_EN_COMPRAS = 'EN COMPRAS';

    private const MONTO_HASTA_MAX = 999999999;

    private const DOBLE_APROBACION = 'N';

    /**
     * CC código → logins firmantes nivel 1.
     *
     * @var array<string, list<string>>
     */
    private const FIRMANTES_POR_CC = [
        '110' => ['priccobon'],
        '201' => ['gramos'],
        '207' => ['jortiz', 'jsandoval', 'gmorales', 'jlrea'],
        '307' => ['cbollo'],
        '310' => ['jcapdeville'],
        '401' => ['efernandez'],
        '407' => ['acamperi', 'arossi', 'gharvey'],
        '507' => ['raltieri', 'ppainecura', 'pescobar', 'galbano'],
        '510' => ['mlaffue'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion') || ! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $monedaPesos = (int) (DB::table('moneda')->where('nombre', 'PESOS')->value('id') ?? 0);
        if ($monedaPesos <= 0) {
            throw new \RuntimeException('No se encontró la moneda PESOS.');
        }

        $usuarioPorLogin = DB::table('usuario')->pluck('id', 'usuario');
        $ccPorCodigo = DB::table('centrocosto')->pluck('id', 'codigo');

        $faltantes = [];
        foreach (self::FIRMANTES_POR_CC as $codigoCc => $logins) {
            if (! isset($ccPorCodigo[$codigoCc])) {
                $faltantes[] = "CC {$codigoCc}: centro de costo inexistente";
            }
            foreach ($logins as $login) {
                if (! isset($usuarioPorLogin[$login])) {
                    $faltantes[] = "Usuario {$login} inexistente (CC {$codigoCc})";
                }
            }
        }
        if ($faltantes !== []) {
            throw new \RuntimeException(
                "No se puede cargar el árbol RE Crown:\n".implode("\n", array_unique($faltantes))
            );
        }

        $arbolId = $this->asegurarArbol();
        $this->reemplazarNiveles($arbolId, $monedaPesos, $usuarioPorLogin, $ccPorCodigo);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        $arbolIds = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('empresa_id', self::EMPRESA_ID)
            ->where('nombre', self::NOMBRE_ARBOL)
            ->pluck('id');

        if ($arbolIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('arbolaprobacion_nivel')) {
            DB::table('arbolaprobacion_nivel')->whereIn('arbolaprobacion_id', $arbolIds)->delete();
        }

        DB::table('arbolaprobacion')->whereIn('id', $arbolIds)->delete();
    }

    private function asegurarArbol(): int
    {
        $existente = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('empresa_id', self::EMPRESA_ID)
            ->orderBy('id')
            ->first();

        $now = now();

        if ($existente) {
            DB::table('arbolaprobacion')->where('id', $existente->id)->update([
                'nombre' => self::NOMBRE_ARBOL,
                'estado' => self::ESTADO_ARBOL,
                'updated_at' => $now,
            ]);

            return (int) $existente->id;
        }

        return (int) DB::table('arbolaprobacion')->insertGetId([
            'nombre' => self::NOMBRE_ARBOL,
            'tipoarbol' => self::TIPO_ARBOL,
            'empresa_id' => self::EMPRESA_ID,
            'recordatorio' => 'N',
            'diasinrespuesta' => 0,
            'diavencimientorecordatorio' => 0,
            'estado' => self::ESTADO_ARBOL,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int|string>  $usuarioPorLogin
     * @param  \Illuminate\Support\Collection<string, int|string>  $ccPorCodigo
     */
    private function reemplazarNiveles(int $arbolId, int $monedaPesos, $usuarioPorLogin, $ccPorCodigo): void
    {
        DB::table('arbolaprobacion_nivel')->where('arbolaprobacion_id', $arbolId)->delete();

        $insertados = 0;
        foreach (self::FIRMANTES_POR_CC as $codigoCc => $logins) {
            $centrocostoId = (int) $ccPorCodigo[$codigoCc];
            foreach ($logins as $login) {
                Arbolaprobacion_Nivel::create([
                    'arbolaprobacion_id' => $arbolId,
                    'centrocosto_id' => $centrocostoId,
                    'nivel' => 1,
                    'usuario_id' => (int) $usuarioPorLogin[$login],
                    'desdemonto' => 0,
                    'hastamonto' => self::MONTO_HASTA_MAX,
                    'moneda_id' => $monedaPesos,
                    'documento_estado_al_aprobar' => self::ESTADO_EN_COMPRAS,
                    'doble_aprobacion' => self::DOBLE_APROBACION,
                ]);
                $insertados++;
            }
        }

        if ($insertados <= 0) {
            throw new \RuntimeException('No se insertó ningún nivel en el árbol RE Crown.');
        }
    }
};
