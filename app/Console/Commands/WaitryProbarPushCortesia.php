<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryHttpClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Prueba pushExternalOrder con ítems $0 + $0,01 (cortesía) contra Waitry real.
 */
class WaitryProbarPushCortesia extends Command
{
    protected $signature = 'waitry:probar-push-cortesia
                            {--empresa=1 : empresa_id (placeId + table)}
                            {--renovar : Renueva token OAuth antes del push}';

    protected $description = 'Envía a Waitry una orden de prueba: 2 ítems ($0 y $0,01), impaga';

    public function handle(WaitryHttpClient $http): int
    {
        if (! config('waitry.habilitado', false)) {
            $this->error('WAITRY_HABILITADO=false');

            return self::FAILURE;
        }

        if ($this->option('renovar')) {
            app(\App\Services\Ventas\Gastronomia\Waitry\WaitryAuthService::class)->invalidarToken();
            app(\App\Services\Ventas\Gastronomia\Waitry\WaitryAuthService::class)->renovarTokenForzado();
            $this->info('Token renovado.');
        }

        $empresaId = (int) $this->option('empresa');
        $placeMap = config('waitry.place_id_por_empresa', []);
        $placeId = is_array($placeMap) ? (int) ($placeMap[$empresaId] ?? 0) : 0;
        $tableMap = config('waitry.table_por_empresa', []);
        $table = is_array($tableMap) ? ($tableMap[$empresaId] ?? null) : null;

        if ($placeId <= 0 || ! is_array($table) || $table === []) {
            $this->error('Configuración placeId/table inválida para empresa '.$empresaId);

            return self::FAILURE;
        }

        $tsItem = [
            'date' => Carbon::now('UTC')->format('Y-m-d\TH:i:sP'),
            'timezone_type' => 0,
            'timezone' => '+00:00',
        ];

        $importeMin = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;
        $externalId = 'TEST-CORTESIA-'.now()->format('YmdHis');

        $orderItems = [
            $this->itemPrueba($tsItem, 'TEST-PRECIO-CERO', 'Prueba Waitry precio 0', 0., 1),
            $this->itemPrueba($tsItem, 'TEST-PRECIO-001', 'Prueba Waitry precio 0,01', $importeMin, 1),
        ];

        $payload = [
            'timestamp' => [
                'date' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                'timezone_type' => null,
                'timezone' => null,
            ],
            'placeId' => $placeId,
            'table' => $table,
            'paid' => false,
            'external_id' => $externalId,
            'totalAmount' => $importeMin,
            'notes' => 'PRUEBA AnitaERP cortesía $0+$0,01 — descartar',
            'orderItems' => $orderItems,
        ];

        $this->line('URL: '.config('waitry.push_order_url'));
        $this->line('placeId: '.$placeId.' | external_id: '.$externalId);
        $this->line('Payload ítems: [0.00, '.$importeMin.'] | totalAmount: '.$importeMin.' | paid: false');

        if (! $this->confirm('¿Enviar push de prueba a Waitry (producción)?', true)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $resultado = $http->postJson(
            (string) config('waitry.push_order_url'),
            $payload,
            'push_cortesia_prueba',
        );

        $this->newLine();
        $this->line('HTTP: '.($resultado['http_code'] ?? '?'));

        if (! empty($resultado['data'])) {
            $this->line('Respuesta: '.json_encode($resultado['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        if (! ($resultado['ok'] ?? false)) {
            $this->error('Fallo: '.($resultado['error'] ?? 'error desconocido'));

            return self::FAILURE;
        }

        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        $response = $data['response'] ?? null;
        $orderId = is_array($response)
            ? ($response['orderId'] ?? $response['order_id'] ?? null)
            : ($data['orderId'] ?? $data['order_id'] ?? null);

        $this->info('Push OK'.($orderId !== null ? ' — orderId: '.$orderId : '').'.');
        $this->comment('Waitry aceptó ítems con precio $0 y $0,01.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $tsItem
     * @return array<string, mixed>
     */
    private function itemPrueba(array $tsItem, string $sku, string $nombre, float $precio, int $count): array
    {
        return [
            'timestamp' => $tsItem,
            'count' => $count,
            'notes' => null,
            'price' => $precio,
            'tax' => 0.,
            'discount' => 0.,
            'discountPrice' => null,
            'subtotal' => round($precio * $count, 2),
            'paid' => false,
            'item' => [
                'name' => $nombre,
                'price' => $precio,
                'externalId' => $sku,
                'externalCode' => $sku,
            ],
            'orderItemVariations' => [],
        ];
    }
}
