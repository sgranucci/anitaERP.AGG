<?php

namespace App\Support\Ventas\Ferli;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lectura de datos L8 (MySQL mysql_l8 o bridge HTTP) para importar a L12.
 */
final class FerliL8ReaderSupport
{
    public static function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('La importación desde L8 solo aplica a Calzados Ferli.');
        }
    }

    /**
     * @return array{fuente: string, conexion: ConnectionInterface|null}
     */
    public function resolverFuente(): array
    {
        self::assertFerli();

        try {
            $conn = DB::connection('mysql_l8');
            $conn->select('select 1');

            return ['fuente' => 'mysql_l8', 'conexion' => $conn];
        } catch (\Throwable $e) {
            // Esperado si falta GRANT sobre anitaERP_l8: seguir con bridge HTTP.
        }

        $base = (string) config('ferli_l8.http_base_url');
        $token = (string) config('ferli_l8.http_token');
        if ($base === '' || $token === '') {
            throw new RuntimeException(
                'No hay acceso a L8. Configure DB_L8_* (p. ej. GRANT SELECT sobre anitaERP_l8) '
                .'o FERLI_L8_HTTP_BASE_URL + FERLI_L8_HTTP_TOKEN con el bridge en el servidor L8.'
            );
        }

        return ['fuente' => 'http', 'conexion' => null];
    }

    /**
     * @param  list<int|string>  $otCodigos
     * @return array{
     *   fuente: string,
     *   ordentrabajo: list<array<string, mixed>>,
     *   ordentrabajo_tarea: list<array<string, mixed>>,
     *   movimientoordentrabajo: list<array<string, mixed>>,
     *   ordentrabajo_combinacion_talle: list<array<string, mixed>>
     * }
     */
    public function payloadTareasPorOtCodigos(array $otCodigos): array
    {
        $otCodigos = array_values(array_unique(array_filter(array_map('intval', $otCodigos), static fn ($c) => $c > 0)));
        if ($otCodigos === []) {
            return [
                'fuente' => 'ninguna',
                'ordentrabajo' => [],
                'ordentrabajo_tarea' => [],
                'movimientoordentrabajo' => [],
                'ordentrabajo_combinacion_talle' => [],
            ];
        }

        $fuente = $this->resolverFuente();
        if ($fuente['fuente'] === 'http') {
            return $this->httpGet('api/l8-sync/tareas-ot', ['codigos' => implode(',', $otCodigos)]);
        }

        /** @var ConnectionInterface $db */
        $db = $fuente['conexion'];

        $ots = $db->table('ordentrabajo')
            ->whereIn('codigo', $otCodigos)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $otIds = array_values(array_unique(array_map(static fn ($r) => (int) $r['id'], $ots)));
        if ($otIds === []) {
            return [
                'fuente' => $fuente['fuente'],
                'ordentrabajo' => [],
                'ordentrabajo_tarea' => [],
                'movimientoordentrabajo' => [],
                'ordentrabajo_combinacion_talle' => [],
            ];
        }

        $tareas = $db->table('ordentrabajo_tarea')
            ->whereIn('ordentrabajo_id', $otIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $movimientos = $db->table('movimientoordentrabajo')
            ->whereIn('ordentrabajo_id', $otIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $oct = $db->table('ordentrabajo_combinacion_talle')
            ->whereIn('ordentrabajo_id', $otIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        return [
            'fuente' => $fuente['fuente'],
            'ordentrabajo' => $ots,
            'ordentrabajo_tarea' => $tareas,
            'movimientoordentrabajo' => $movimientos,
            'ordentrabajo_combinacion_talle' => $oct,
        ];
    }

    /**
     * Pedidos de L8 cuyo codigo (o id) no existe en L12.
     *
     * @return array{
     *   fuente: string,
     *   pedidos: list<array<string, mixed>>,
     *   pedido_combinacion: list<array<string, mixed>>,
     *   pedido_combinacion_talle: list<array<string, mixed>>,
     *   pedido_combinacion_estado: list<array<string, mixed>>,
     *   ordentrabajo: list<array<string, mixed>>,
     *   ordentrabajo_combinacion_talle: list<array<string, mixed>>,
     *   ordentrabajo_tarea: list<array<string, mixed>>,
     *   movimientoordentrabajo: list<array<string, mixed>>
     * }
     */
    public function payloadPedidosFaltantes(?string $fechaDesde = null, int $limite = 200): array
    {
        $fuente = $this->resolverFuente();
        if ($fuente['fuente'] === 'http') {
            $query = ['limite' => $limite];
            if ($fechaDesde) {
                $query['fecha_desde'] = $fechaDesde;
            }

            return $this->httpGet('api/l8-sync/pedidos-faltantes', $query);
        }

        /** @var ConnectionInterface $db */
        $db = $fuente['conexion'];

        $codigosL12 = DB::table('pedido')->pluck('codigo')->map(static fn ($c) => (string) $c)->all();
        $idsL12 = DB::table('pedido')->pluck('id')->map(static fn ($c) => (int) $c)->all();

        $q = $db->table('pedido')->orderByDesc('id');
        if ($fechaDesde) {
            $q->whereDate('fecha', '>=', $fechaDesde);
        }
        $candidatos = $q->limit(max(50, $limite * 5))->get();

        $faltantes = [];
        foreach ($candidatos as $row) {
            $codigo = (string) ($row->codigo ?? '');
            $id = (int) $row->id;
            if (in_array($codigo, $codigosL12, true) || in_array($id, $idsL12, true)) {
                continue;
            }
            $faltantes[] = (array) $row;
            if (count($faltantes) >= $limite) {
                break;
            }
        }

        if ($faltantes === []) {
            return [
                'fuente' => $fuente['fuente'],
                'pedidos' => [],
                'pedido_combinacion' => [],
                'pedido_combinacion_talle' => [],
                'pedido_combinacion_estado' => [],
                'ordentrabajo' => [],
                'ordentrabajo_combinacion_talle' => [],
                'ordentrabajo_tarea' => [],
                'movimientoordentrabajo' => [],
            ];
        }

        $pedidoIds = array_map(static fn ($r) => (int) $r['id'], $faltantes);

        $combinaciones = $db->table('pedido_combinacion')
            ->whereIn('pedido_id', $pedidoIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $pcIds = array_map(static fn ($r) => (int) $r['id'], $combinaciones);

        $talles = $pcIds === [] ? [] : $db->table('pedido_combinacion_talle')
            ->whereIn('pedido_combinacion_id', $pcIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $estados = [];
        if ($pcIds !== [] && $this->tablaExiste($db, 'pedido_combinacion_estado')) {
            $estados = $db->table('pedido_combinacion_estado')
                ->whereIn('pedido_combinacion_id', $pcIds)
                ->get()
                ->map(static fn ($r) => (array) $r)
                ->all();
        }

        $otCodigos = array_values(array_unique(array_filter(
            array_map(static fn ($r) => (int) ($r['ot_id'] ?? 0), $combinaciones),
            static fn ($c) => $c > 0
        )));

        $ots = $otCodigos === [] ? [] : $db->table('ordentrabajo')
            ->whereIn('codigo', $otCodigos)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $otIds = array_map(static fn ($r) => (int) $r['id'], $ots);

        $oct = $otIds === [] ? [] : $db->table('ordentrabajo_combinacion_talle')
            ->whereIn('ordentrabajo_id', $otIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $tareas = $otIds === [] ? [] : $db->table('ordentrabajo_tarea')
            ->whereIn('ordentrabajo_id', $otIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $movimientos = $otIds === [] ? [] : $db->table('movimientoordentrabajo')
            ->whereIn('ordentrabajo_id', $otIds)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        return [
            'fuente' => $fuente['fuente'],
            'pedidos' => $faltantes,
            'pedido_combinacion' => $combinaciones,
            'pedido_combinacion_talle' => $talles,
            'pedido_combinacion_estado' => $estados,
            'ordentrabajo' => $ots,
            'ordentrabajo_combinacion_talle' => $oct,
            'ordentrabajo_tarea' => $tareas,
            'movimientoordentrabajo' => $movimientos,
        ];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function httpGet(string $path, array $query): array
    {
        $base = (string) config('ferli_l8.http_base_url');
        $token = (string) config('ferli_l8.http_token');
        $timeout = (int) config('ferli_l8.http_timeout', 120);

        $url = $base.'/'.ltrim($path, '/');
        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-L8-Sync-Token' => $token])
            ->get($url, array_merge($query, ['token' => $token]));

        if (! $response->successful()) {
            throw new RuntimeException(
                'Bridge L8 HTTP falló (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 300)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Bridge L8 HTTP devolvió JSON inválido.');
        }

        $json['fuente'] = $json['fuente'] ?? 'http';

        return $json;
    }

    private function tablaExiste(ConnectionInterface $db, string $tabla): bool
    {
        try {
            $db->table($tabla)->limit(1)->get();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
