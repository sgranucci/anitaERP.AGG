<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\ArticuloWaitryEnvio;
use App\Services\Ventas\Gastronomia\Waitry\WaitryHttpClient;
use App\Support\Stock\ArticuloWaitryEstadoSupport;
use App\Support\Stock\ArticuloWaitrySupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use App\Support\Ventas\Waitry\WaitryArticuloMenuPayloadSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ArticuloWaitrySyncService
{
    /** @var array<int, string> */
    private const NOMBRES_SALA = [
        1 => 'Biyemas',
        2 => 'Kandiko',
        3 => 'Rebisco',
    ];

    public function __construct(
        private readonly WaitryHttpClient $httpClient,
    ) {}

    /**
     * @return array{sku:string,descripcion:string,marcas:array{dispara_comanda:bool,va_cocina:bool},salas:list<array<string,mixed>>,puede_enviar:bool}
     */
    public function panel(?Articulo $articulo, bool $puedeActualizar): array
    {
        $sku = trim((string) ($articulo->sku ?? ''));
        $id = (int) ($articulo->id ?? 0);

        return [
            'sku' => $sku,
            'descripcion' => trim((string) ($articulo->descripcion ?? '')),
            'marcas' => ArticuloWaitrySupport::marcasDe($articulo),
            'salas' => $this->salasConEstado($id),
            'puede_enviar' => $id > 0 && $puedeActualizar && GastronomiaSkuCatalogoSupport::skuPermitido($sku),
        ];
    }

    /**
     * @return array{ok:bool,mensaje:string,salas:list<array<string,mixed>>}
     */
    public function ejecutar(Articulo $articulo, string $operacion): array
    {
        $sku = mb_strtoupper(trim((string) $articulo->sku), 'UTF-8');
        if (! GastronomiaSkuCatalogoSupport::skuPermitido($sku)) {
            return [
                'ok' => false,
                'mensaje' => 'Este SKU no se envía a Waitry.',
                'salas' => $this->salasConEstado((int) $articulo->id),
            ];
        }

        if (! config('waitry.habilitado', false)) {
            return [
                'ok' => false,
                'mensaje' => 'La integración Waitry está apagada.',
                'salas' => $this->salasConEstado((int) $articulo->id),
            ];
        }

        $nombre = trim((string) $articulo->descripcion);
        $huboFallo = false;
        $huboEnvio = false;

        foreach ($this->salas() as $sala) {
            $decision = $this->decisionSala((int) $articulo->id, (int) $sala['empresa_id']);
            $permitido = $operacion === ArticuloWaitryEnvio::ALTA
                ? $decision['puede_alta']
                : $decision['puede_baja'];

            if (! $permitido) {
                continue;
            }

            $huboEnvio = true;
            $resultado = $this->enviarSala($articulo, $sku, $nombre, $operacion, $sala);
            if (! $resultado['ok']) {
                $huboFallo = true;
            }
        }

        $mensaje = match (true) {
            $huboFallo => 'Alguna sala no pudo completarse. Se puede reintentar solo esa.',
            ! $huboEnvio && $operacion === ArticuloWaitryEnvio::ALTA => 'Anita ya lo envió a las salas que correspondían.',
            ! $huboEnvio => 'No hay salas con un alta registrada para inactivar.',
            $operacion === ArticuloWaitryEnvio::ALTA => 'Alta enviada.',
            default => 'Inactivación enviada.',
        };

        return [
            'ok' => ! $huboFallo,
            'mensaje' => $mensaje,
            'salas' => $this->salasConEstado((int) $articulo->id),
        ];
    }

    /**
     * @param  array{empresa_id:int,place_id:int,nombre:string}  $sala
     * @return array{ok:bool}
     */
    private function enviarSala(Articulo $articulo, string $sku, string $nombre, string $operacion, array $sala): array
    {
        $payload = $operacion === ArticuloWaitryEnvio::ALTA
            ? WaitryArticuloMenuPayloadSupport::alta($sku, $nombre)
            : WaitryArticuloMenuPayloadSupport::baja($sku, $nombre);

        $base = $operacion === ArticuloWaitryEnvio::ALTA
            ? (string) config('waitry.sync_menu_pos_url')
            : (string) config('waitry.sync_items_pos_url');

        $url = $base.'?'.http_build_query(['placeId' => $sala['place_id']]);
        $http = $this->httpClient->postJson($url, $payload, 'articulo_'.$operacion);

        $interpretado = ($http['ok'] ?? false) && is_array($http['data'])
            ? WaitryArticuloMenuPayloadSupport::interpretar($http['data'])
            : ['ok' => false, 'mensaje' => (string) ($http['error'] ?? 'Waitry no respondió.')];

        $mensaje = $interpretado['ok']
            ? ($operacion === ArticuloWaitryEnvio::ALTA ? 'Alta aceptada.' : 'Inactivación aceptada.')
            : mb_substr((string) $interpretado['mensaje'], 0, 500);

        ArticuloWaitryEnvio::query()->create([
            'articulo_id' => (int) $articulo->id,
            'empresa_id' => $sala['empresa_id'],
            'place_id' => $sala['place_id'],
            'operacion' => $operacion,
            'ok' => (bool) $interpretado['ok'],
            'http_code' => (int) ($http['http_code'] ?? 0) ?: null,
            'mensaje' => $mensaje,
            'pedido_json' => $this->jsonCorto($payload),
            'respuesta_json' => $this->jsonCorto($http['data'] ?? $http['error'] ?? null),
            'usuario_id' => Auth::id(),
        ]);

        Log::info('waitry.articulo.'.$operacion, [
            'articulo_id' => (int) $articulo->id,
            'sku' => $sku,
            'empresa_id' => $sala['empresa_id'],
            'place_id' => $sala['place_id'],
            'ok' => (bool) $interpretado['ok'],
            'http_code' => $http['http_code'] ?? 0,
        ]);

        return ['ok' => (bool) $interpretado['ok']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salasConEstado(int $articuloId): array
    {
        $salas = [];
        foreach ($this->salas() as $sala) {
            $decision = $this->decisionSala($articuloId, (int) $sala['empresa_id']);
            $salas[] = array_merge($sala, $decision);
        }

        return $salas;
    }

    /**
     * @return array{vigente:?string,puede_alta:bool,puede_baja:bool,ultimo_error:?string,estado:string}
     */
    private function decisionSala(int $articuloId, int $empresaId): array
    {
        if ($articuloId <= 0) {
            return ArticuloWaitryEstadoSupport::decidir([]);
        }

        $intentos = ArticuloWaitryEnvio::query()
            ->where('articulo_id', $articuloId)
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->get(['operacion', 'ok', 'mensaje'])
            ->map(static fn (ArticuloWaitryEnvio $row): array => [
                'operacion' => (string) $row->operacion,
                'ok' => (bool) $row->ok,
                'mensaje' => (string) ($row->mensaje ?? ''),
            ])
            ->all();

        return ArticuloWaitryEstadoSupport::decidir($intentos);
    }

    /**
     * @return list<array{empresa_id:int,place_id:int,nombre:string}>
     */
    private function salas(): array
    {
        $map = config('waitry.place_id_por_empresa', []);
        if (! is_array($map)) {
            return [];
        }

        $salas = [];
        foreach (self::NOMBRES_SALA as $empresaId => $nombre) {
            $placeId = (int) ($map[$empresaId] ?? 0);
            if ($placeId <= 0) {
                continue;
            }
            $salas[] = [
                'empresa_id' => $empresaId,
                'place_id' => $placeId,
                'nombre' => $nombre,
            ];
        }

        return $salas;
    }

    private function jsonCorto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $json = json_encode($valor, JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            return null;
        }

        return mb_substr($json, 0, 20000);
    }
}
