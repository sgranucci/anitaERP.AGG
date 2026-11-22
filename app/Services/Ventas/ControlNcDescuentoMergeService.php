<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Vendedor;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\ControlNcDescuentoAnitaBridgeReader;
use App\Support\Ventas\ControlNcDescuentoMailSupport;
use Illuminate\Support\Facades\Log;

/**
 * Arma el control NC dto del día mergeando ERP + Anita (Anita manda; ERP completa/diffs).
 */
class ControlNcDescuentoMergeService
{
    private const TOLERANCIA_MONTO = 0.05;

    public function __construct(
        private readonly ControlNcDescuentoAnitaBridgeReader $anitaReader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(?string $fechaIso = null): array
    {
        $fecha = $fechaIso && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIso)
            ? $fechaIso
            : date('Y-m-d');

        $anitaOk = true;
        $anitaError = null;
        $mapAnita = [];
        try {
            $ret = $this->anitaReader->listarNcpDelDia($fecha);
            $anitaOk = (bool) ($ret['ok'] ?? false);
            $anitaError = $ret['error'] ?? null;
            foreach ($ret['filas'] ?? [] as $fila) {
                $mapAnita[(string) $fila['clave']] = $fila;
            }
        } catch (\Throwable $e) {
            $anitaOk = false;
            $anitaError = $e->getMessage();
            Log::warning('ControlNcDescuentoMergeService Anita', ['error' => $anitaError]);
        }

        $mapErp = $this->mapearErp($fecha);

        $claves = array_values(array_unique(array_merge(array_keys($mapAnita), array_keys($mapErp))));
        sort($claves);

        $filasMerge = [];
        $filasAnita = [];
        $filasErp = [];
        $diffs = [];
        $vendedores = $this->nombresVendedor(
            array_merge(
                array_column($mapAnita, 'vendedor_codigo'),
                array_column($mapErp, 'vendedor_codigo'),
            )
        );

        foreach ($claves as $clave) {
            $anita = $mapAnita[$clave] ?? null;
            $erp = $mapErp[$clave] ?? null;

            if ($anita !== null) {
                $filasAnita[] = $anita;
            }
            if ($erp !== null) {
                $filasErp[] = $erp;
            }

            $merge = $this->mergeFila($anita, $erp, $vendedores);
            if ($merge !== null) {
                $filasMerge[] = $merge;
            }

            $diff = $this->diffFila($clave, $anita, $erp);
            if ($diff !== null) {
                $diffs[] = $diff;
            }
        }

        $seccionLista = $this->filtrarSeccion($filasMerge, 'lista_especial');
        $seccionGeneral = $this->filtrarSeccion($filasMerge, 'general');

        $cuadran = $diffs === [] && $anitaOk;

        return [
            'fecha' => $fecha,
            'filas_anita' => $filasAnita,
            'filas_erp' => $filasErp,
            'filas_merge' => $filasMerge,
            'seccion_lista' => $seccionLista,
            'seccion_general' => $seccionGeneral,
            'diffs' => $diffs,
            'totales' => [
                'anita' => count($filasAnita),
                'erp' => count($filasErp),
                'merge' => count($filasMerge),
                'lista' => count($seccionLista),
                'general' => count($seccionGeneral),
                'monto_lista' => round(array_sum(array_column($seccionLista, 'monto')), 2),
                'monto_general' => round(array_sum(array_column($seccionGeneral, 'monto')), 2),
            ],
            'cuadran' => $cuadran,
            'anita_ok' => $anitaOk,
            'anita_error' => $anitaError,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function mapearErp(string $fechaIso): array
    {
        $ventas = Venta::query()
            ->with([
                'tipotransacciones:id,abreviatura',
                'puntoventas:id,codigo',
                'clientes:id,codigo,nombre,listaprecio_id',
                'clientes.listaprecios:id,codigo',
            ])
            ->whereDate('fecha', $fechaIso)
            ->whereHas('tipotransacciones', function ($q) {
                $q->where('abreviatura', ControlNcDescuentoMailSupport::TIPO_NCP);
            })
            ->get();

        $vendedorIds = $ventas->pluck('vendedor_id')->filter()->unique()->values()->all();
        $vendedoresPorId = $vendedorIds === []
            ? collect()
            : Vendedor::query()->whereIn('id', $vendedorIds)->get(['id', 'codigo', 'nombre'])->keyBy('id');

        $out = [];
        foreach ($ventas as $venta) {
            $tipo = ControlNcDescuentoMailSupport::TIPO_NCP;
            $claveDesdeCodigo = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta(
                (string) ($venta->codigo ?? '')
            );
            if ($claveDesdeCodigo !== null) {
                $clave = $claveDesdeCodigo;
                $partes = explode('|', $clave);
                $letra = (string) ($partes[1] ?? ' ');
                $sucursal = (int) ($partes[2] ?? 0);
                $numero = (int) ($partes[3] ?? 0);
            } else {
                $letra = ' ';
                $sucursal = (int) ($venta->puntoventas->codigo ?? 0);
                $numero = (int) ($venta->numerocomprobante ?? 0);
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipo,
                    $letra,
                    $sucursal,
                    $numero
                );
            }

            $lista = (int) ($venta->clientes->listaprecios->codigo ?? 0);
            $vendedor = $vendedoresPorId->get((int) ($venta->vendedor_id ?? 0));
            $vendCodigo = (int) ($vendedor->codigo ?? 0);
            $monto = abs((float) ($venta->total ?? 0));

            $out[$clave] = [
                'clave' => $clave,
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numero' => $numero,
                'cliente_codigo' => (string) ($venta->clientes->codigo ?? ''),
                'cliente_nombre' => (string) ($venta->clientes->nombre ?? $venta->nombre ?? ''),
                'monto' => round($monto, 2),
                'monto_fac' => 0.0,
                'porcentaje' => 0.0,
                'lista_precio' => $lista,
                'tipo_recibo' => '',
                'nro_recibo' => 0,
                'vendedor_codigo' => $vendCodigo,
                'vendedor_nombre' => (string) ($vendedor->nombre ?? ''),
                'fuente' => 'erp',
                'venta_id' => (int) $venta->id,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $anita
     * @param  array<string, mixed>|null  $erp
     * @param  array<int, string>  $vendedores
     * @return array<string, mixed>|null
     */
    private function mergeFila(?array $anita, ?array $erp, array $vendedores): ?array
    {
        if ($anita === null && $erp === null) {
            return null;
        }

        // Anita manda % / recibo / lista cuando existe; ERP completa nombre vendedor y venta_id.
        $base = $anita ?? $erp;
        $fuente = ($anita !== null && $erp !== null) ? 'ambos' : ($anita !== null ? 'anita' : 'erp');

        $vend = (int) ($base['vendedor_codigo'] ?? 0);
        $nombreVend = (string) ($erp['vendedor_nombre'] ?? '');
        if ($nombreVend === '') {
            $nombreVend = $vendedores[$vend] ?? 'Inexistente';
        }

        $lista = (int) ($anita['lista_precio'] ?? $erp['lista_precio'] ?? 0);
        $porc = (float) ($anita['porcentaje'] ?? $erp['porcentaje'] ?? 0);

        return [
            'clave' => (string) ($base['clave'] ?? ''),
            'tipo' => (string) ($base['tipo'] ?? ControlNcDescuentoMailSupport::TIPO_NCP),
            'letra' => (string) ($base['letra'] ?? ''),
            'sucursal' => (int) ($base['sucursal'] ?? 0),
            'numero' => (int) ($base['numero'] ?? 0),
            'cliente_codigo' => (string) ($anita['cliente_codigo'] ?? $erp['cliente_codigo'] ?? ''),
            'cliente_nombre' => (string) ($anita['cliente_nombre'] ?? $erp['cliente_nombre'] ?? ''),
            'monto' => round((float) ($anita['monto'] ?? $erp['monto'] ?? 0), 2),
            'monto_fac' => round((float) ($anita['monto_fac'] ?? 0), 2),
            'porcentaje' => $porc,
            'lista_precio' => $lista,
            'tipo_recibo' => (string) ($anita['tipo_recibo'] ?? ''),
            'nro_recibo' => (int) ($anita['nro_recibo'] ?? 0),
            'vendedor_codigo' => $vend,
            'vendedor_nombre' => $nombreVend,
            'fuente' => $fuente,
            'venta_id' => (int) ($erp['venta_id'] ?? 0),
            'en_seccion_lista' => $this->entraSeccionLista($lista, $porc),
            'en_seccion_general' => $porc > ControlNcDescuentoMailSupport::UMBRAL_GENERAL,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function filtrarSeccion(array $filas, string $tipo): array
    {
        $out = [];
        foreach ($filas as $fila) {
            if ($tipo === 'lista_especial' && ! empty($fila['en_seccion_lista'])) {
                $out[] = $fila;
            }
            if ($tipo === 'general' && ! empty($fila['en_seccion_general'])) {
                $out[] = $fila;
            }
        }

        usort($out, static function (array $a, array $b): int {
            $cmp = ((int) ($a['vendedor_codigo'] ?? 0)) <=> ((int) ($b['vendedor_codigo'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((float) ($b['porcentaje'] ?? 0)) <=> ((float) ($a['porcentaje'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['numero'] ?? 0)) <=> ((int) ($b['numero'] ?? 0));
        });

        return $out;
    }

    private function entraSeccionLista(int $lista, float $porc): bool
    {
        return in_array($lista, ControlNcDescuentoMailSupport::LISTAS_ESPECIALES, true)
            && $porc > ControlNcDescuentoMailSupport::UMBRAL_LISTA_ESPECIAL;
    }

    /**
     * @param  array<string, mixed>|null  $anita
     * @param  array<string, mixed>|null  $erp
     * @return array<string, mixed>|null
     */
    private function diffFila(string $clave, ?array $anita, ?array $erp): ?array
    {
        if ($anita === null && $erp === null) {
            return null;
        }
        if ($anita === null) {
            return [
                'clave' => $clave,
                'tipo' => 'solo_erp',
                'monto_anita' => null,
                'monto_erp' => (float) ($erp['monto'] ?? 0),
            ];
        }
        if ($erp === null) {
            // En Bierzo es normal que NCP exista solo en Anita: no cuenta como DIFF de control
            // salvo que el operador quiera ver el detalle. Marcamos como informativo.
            return null;
        }
        $diffMonto = abs((float) $anita['monto'] - (float) $erp['monto']);
        if ($diffMonto > self::TOLERANCIA_MONTO) {
            return [
                'clave' => $clave,
                'tipo' => 'monto',
                'monto_anita' => (float) $anita['monto'],
                'monto_erp' => (float) $erp['monto'],
            ];
        }

        return null;
    }

    /**
     * @param  list<int|string|null>  $codigos
     * @return array<int, string>
     */
    private function nombresVendedor(array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigos))));
        if ($codigos === []) {
            return [];
        }

        $out = [];
        foreach (
            Vendedor::query()
                ->whereIn('codigo', array_map('strval', $codigos))
                ->get(['codigo', 'nombre']) as $v
        ) {
            $out[(int) $v->codigo] = (string) $v->nombre;
        }

        return $out;
    }
}
