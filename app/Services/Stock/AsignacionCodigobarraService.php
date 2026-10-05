<?php

namespace App\Services\Stock;

use App\Models\Compras\Proveedor;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Models\Stock\Depmae;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use App\Support\Stock\TransferenciaMercaderiaPickeoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AsignacionCodigobarraService
{
    /** Recepción COM confirmada que alimenta el buscador de la pantalla. */
    public const DEPOSITO_BUSQUEDA_COM = 1;

    private const COMPRAS_RECIENTES = 5;

    /**
     * Artículos con saldo > 0 en el depósito cuyo código de proveedor todavía no está cargado.
     * El proveedor sugerido sale de la última recepción confirmada sin código.
     *
     * @return list<array{
     *   articulo_id: int,
     *   sku: string,
     *   descripcion: string,
     *   saldo: float,
     *   necesita_proveedor: bool,
     *   proveedor_id: int|null,
     *   proveedor_nombre: string|null,
     *   recepcion_id: int|null,
     *   compras: list<array{
     *     recepcion_id: int,
     *     fecha: string,
     *     numerorecepcion: string,
     *     numerofactura: string,
     *     proveedor_id: int,
     *     proveedor_nombre: string,
     *     etiqueta: string,
     *     codigobarra: string,
     *     tiene_codigo: bool,
     *     seleccionada: bool
     *   }>
     * }>
     */
    public function pendientesDeposito(int $depositoId): array
    {
        $this->assertDepositoAutorizado($depositoId);

        $agg = DB::table('articulo_saldo_deposito as sd')
            ->join('articulo as a', 'a.id', '=', 'sd.articulo_id')
            ->where('sd.deposito_id', $depositoId)
            ->where('sd.cantidad', '>', 0)
            ->where('a.estado', ArticuloSeleccionOperativaSupport::ESTADO_ACTIVO)
            ->whereNotNull('a.sku')
            ->whereRaw("TRIM(a.sku) <> ''")
            // No tiene sentido etiquetar servicios / cuentas contables / impuestos.
            ->where(function ($q) {
                $q->whereNull('a.tipoarticulo_id')
                    ->orWhereNotIn('a.tipoarticulo_id', [10, 11, 12]); // SERVICIO, CONTABLE, IMPUESTO
            })
            ->groupBy('a.id', 'a.sku', 'a.descripcion')
            ->orderByDesc(DB::raw('SUM(sd.cantidad)'))
            ->orderBy('a.descripcion')
            ->select([
                'a.id as articulo_id',
                'a.sku',
                'a.descripcion',
                DB::raw('SUM(sd.cantidad) as saldo'),
            ])
            ->get();

        if ($agg->isEmpty()) {
            return [];
        }

        $porArticulo = [];
        foreach ($agg as $row) {
            $id = (int) $row->articulo_id;
            $porArticulo[$id] = [
                'articulo_id' => $id,
                'sku' => trim((string) $row->sku),
                'descripcion' => (string) ($row->descripcion ?? ''),
                'saldo' => (float) $row->saldo,
            ];
        }

        $ids = array_keys($porArticulo);
        $comprasPorArticulo = $this->comprasRecientesPorArticulo($ids);
        $vinculos = Articulo_Proveedor::query()
            ->with('proveedores:id,codigo,nombre')
            ->whereIn('articulo_id', $ids)
            ->orderByDesc('activo')
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get()
            ->groupBy('articulo_id');

        $out = [];
        foreach ($porArticulo as $id => $fila) {
            $armada = $this->armarPendiente(
                $fila,
                $comprasPorArticulo[$id] ?? [],
                $vinculos->get($id)
            );
            if ($armada !== null) {
                $out[] = $armada;
            }
        }

        return array_values($out);
    }

    /**
     * Artículos que entraron por una recepción COM confirmada al depósito 1.
     * La búsqueda de la pantalla usa esta lista, aparte de la cola de pickeo.
     *
     * @return array{deposito_id: int, deposito_nombre: string, filas: list<array<string, mixed>>}
     */
    public function catalogoComDeposito(): array
    {
        $depositoId = self::DEPOSITO_BUSQUEDA_COM;
        $this->assertDepositoAutorizado($depositoId);

        $deposito = Depmae::query()->find($depositoId, ['id', 'codigo', 'nombre']);
        $nombre = $deposito
            ? Depmae::etiquetaDesdePartes((string) ($deposito->codigo ?? ''), (string) ($deposito->nombre ?? ''), $depositoId)
            : 'Depósito '.$depositoId;

        $articulos = DB::table('recepcion_proveedor as rp')
            ->join('recepcion_proveedor_articulo as rpa', 'rpa.recepcion_proveedor_id', '=', 'rp.id')
            ->join('articulo as a', 'a.id', '=', 'rpa.articulo_id')
            ->where('rp.deposito_id', $depositoId)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->where('rp.anita_tipo', 'COM')
            ->where('a.estado', ArticuloSeleccionOperativaSupport::ESTADO_ACTIVO)
            ->whereNotNull('a.sku')
            ->whereRaw("TRIM(a.sku) <> ''")
            ->where(function ($q) {
                $q->whereNull('a.tipoarticulo_id')
                    ->orWhereNotIn('a.tipoarticulo_id', [10, 11, 12]);
            })
            ->groupBy('a.id', 'a.sku', 'a.descripcion')
            ->orderBy('a.descripcion')
            ->get([
                'a.id as articulo_id',
                'a.sku',
                'a.descripcion',
            ]);

        if ($articulos->isEmpty()) {
            return [
                'deposito_id' => $depositoId,
                'deposito_nombre' => $nombre,
                'filas' => [],
            ];
        }

        $ids = $articulos->pluck('articulo_id')->map(static fn ($id): int => (int) $id)->all();
        $saldos = DB::table('articulo_saldo_deposito')
            ->where('deposito_id', $depositoId)
            ->whereIn('articulo_id', $ids)
            ->groupBy('articulo_id')
            ->select('articulo_id', DB::raw('SUM(cantidad) as saldo'))
            ->pluck('saldo', 'articulo_id');
        $comprasPorArticulo = $this->comprasRecientesPorArticulo($ids);
        $vinculos = Articulo_Proveedor::query()
            ->with('proveedores:id,codigo,nombre')
            ->whereIn('articulo_id', $ids)
            ->orderByDesc('activo')
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get()
            ->groupBy('articulo_id');

        $filas = [];
        foreach ($articulos as $row) {
            $id = (int) $row->articulo_id;
            $armada = $this->armarPendiente(
                [
                    'articulo_id' => $id,
                    'sku' => trim((string) $row->sku),
                    'descripcion' => (string) ($row->descripcion ?? ''),
                    'saldo' => (float) ($saldos[$id] ?? 0),
                ],
                $comprasPorArticulo[$id] ?? [],
                $vinculos->get($id),
                true
            );
            if ($armada !== null) {
                $filas[] = $armada;
            }
        }

        return [
            'deposito_id' => $depositoId,
            'deposito_nombre' => $nombre,
            'filas' => $filas,
        ];
    }

    /**
     * Arma la ficha de pickeo de un artículo elegido en el ABM, aunque no tenga COM ni saldo.
     *
     * @return array<string, mixed>
     */
    public function filaDesdeArticulo(int $articuloId): array
    {
        if ($articuloId <= 0) {
            throw new \InvalidArgumentException('Artículo inválido.');
        }

        $articulo = Articulo::query()->find($articuloId);
        if ($articulo === null || ! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            throw new \InvalidArgumentException('Artículo no encontrado o inactivo.');
        }

        $sku = trim((string) ($articulo->sku ?? ''));
        if ($sku === '') {
            throw new \InvalidArgumentException('El artículo no tiene SKU.');
        }

        $saldo = (float) DB::table('articulo_saldo_deposito')
            ->where('articulo_id', $articuloId)
            ->sum('cantidad');
        $compras = $this->comprasRecientesPorArticulo([$articuloId]);
        $vinculos = Articulo_Proveedor::query()
            ->with('proveedores:id,codigo,nombre')
            ->where('articulo_id', $articuloId)
            ->orderByDesc('activo')
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get();

        $armada = $this->armarPendiente(
            [
                'articulo_id' => $articuloId,
                'sku' => $sku,
                'descripcion' => (string) ($articulo->descripcion ?? ''),
                'saldo' => $saldo,
            ],
            $compras[$articuloId] ?? [],
            $vinculos,
            true
        );
        if ($armada === null) {
            throw new \InvalidArgumentException('No se pudo preparar el artículo.');
        }

        return $armada;
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     */
    public function opcionesProveedor(?string $busqueda = null, int $limite = 80): array
    {
        $q = Proveedor::query()
            ->select(['id', 'codigo', 'nombre', 'estado'])
            ->whereIn('estado', ['0', 'Activo', '3', 'Regularizado'])
            ->orderBy('nombre');

        $busqueda = trim((string) $busqueda);
        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $q->where(function ($w) use ($like, $busqueda) {
                $w->where('nombre', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('fantasia', 'like', $like);
                if (ctype_digit($busqueda)) {
                    $w->orWhere('id', (int) $busqueda);
                }
            });
        }

        return $q->limit(max(1, min($limite, 200)))
            ->get()
            ->map(static function (Proveedor $p): array {
                $codigo = trim((string) ($p->codigo ?? ''));
                $nombre = trim((string) ($p->nombre ?? ''));

                return [
                    'id' => (int) $p->id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'etiqueta' => trim($codigo.' — '.$nombre),
                ];
            })
            ->all();
    }

    /**
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   necesita_proveedor?: bool,
     *   articulo_id?: int,
     *   sku?: string,
     *   codigobarra?: string,
     *   proveedor_id?: int|null
     * }
     */
    public function guardar(int $articuloId, string $codigobarra, ?int $proveedorId = null, bool $reemplazar = false): array
    {
        $codigo = self::normalizarCodigo($codigobarra);
        if ($codigo === null) {
            return ['ok' => false, 'mensaje' => 'Ingrese o lea un código de barras válido.'];
        }
        if ($articuloId <= 0) {
            return ['ok' => false, 'mensaje' => 'Artículo inválido.'];
        }

        $articulo = Articulo::query()->find($articuloId);
        if ($articulo === null || ! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            return ['ok' => false, 'mensaje' => 'Artículo no encontrado o inactivo.'];
        }

        $vinculo = $this->resolverVinculoProveedor($articuloId, $proveedorId);
        if ($vinculo['necesita_proveedor'] ?? false) {
            return [
                'ok' => false,
                'necesita_proveedor' => true,
                'mensaje' => 'Elegí un proveedor para vincular el código de barras.',
                'articulo_id' => $articuloId,
            ];
        }

        $existente = $vinculo['codigobarra_existente'] ?? null;
        if (is_string($existente) && $existente !== '' && strcasecmp($existente, $codigo) === 0) {
            return [
                'ok' => false,
                'ya_cargado' => true,
                'mensaje' => 'Ya está cargado el código '.$existente.' para este proveedor.',
                'articulo_id' => $articuloId,
                'sku' => (string) ($articulo->sku ?? ''),
                'codigobarra' => $existente,
                'proveedor_id' => (int) ($vinculo['fila']->proveedor_id ?? 0) ?: null,
            ];
        }

        $proveedorDestino = (int) ($vinculo['fila']->proveedor_id ?? 0);
        $conflicto = $this->conflictoCodigo($codigo, $articuloId, $proveedorDestino);
        if ($conflicto !== null) {
            return ['ok' => false, 'mensaje' => $conflicto];
        }

        $reemplaza = is_string($existente) && $existente !== '';
        if ($reemplaza && ! $reemplazar) {
            return [
                'ok' => false,
                'puede_reemplazar' => true,
                'mensaje' => 'Este proveedor ya tiene el código '.$existente.'.',
                'articulo_id' => $articuloId,
                'sku' => (string) ($articulo->sku ?? ''),
                'codigobarra_actual' => $existente,
                'codigobarra' => $codigo,
                'proveedor_id' => (int) ($vinculo['fila']->proveedor_id ?? 0) ?: null,
            ];
        }

        DB::transaction(function () use ($codigo, $vinculo) {
            /** @var Articulo_Proveedor $fila */
            $fila = $vinculo['fila'];
            if (! $fila->activo) {
                $fila->activo = true;
            }
            $fila->codigobarra = substr($codigo, 0, 50);
            $fila->save();
        });

        return [
            'ok' => true,
            'reemplazado' => $reemplaza,
            'mensaje' => $reemplaza
                ? 'Código reemplazado. Antes estaba '.$existente.'.'
                : 'Código de barras guardado.',
            'articulo_id' => $articuloId,
            'sku' => (string) ($articulo->sku ?? ''),
            'codigobarra' => $codigo,
            'codigobarra_anterior' => $reemplaza ? $existente : null,
            'proveedor_id' => (int) ($vinculo['fila']->proveedor_id ?? 0) ?: null,
        ];
    }

    public function assertDepositoAutorizado(int $depositoId): void
    {
        if ($depositoId <= 0) {
            throw new \InvalidArgumentException('Depósito inválido.');
        }
        if (! Depmae::query()->whereKey($depositoId)->exists()) {
            throw new \InvalidArgumentException('Depósito no encontrado.');
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            throw new \InvalidArgumentException('No está autorizado para operar el depósito seleccionado.');
        }
    }

    /**
     * @return array{necesita_proveedor?: bool, fila?: Articulo_Proveedor, codigobarra_existente?: string|null}
     */
    private function resolverVinculoProveedor(int $articuloId, ?int $proveedorId): array
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            if (! Proveedor::query()->whereKey($proveedorId)->exists()) {
                throw new \InvalidArgumentException('Proveedor inválido.');
            }

            $filas = Articulo_Proveedor::query()
                ->where('articulo_id', $articuloId)
                ->where('proveedor_id', $proveedorId)
                ->orderByDesc('activo')
                ->orderByDesc('preferido')
                ->orderBy('id')
                ->get();

            foreach ($filas as $fila) {
                $barra = self::normalizarCodigo((string) ($fila->codigobarra ?? ''));
                if ($barra !== null) {
                    return ['fila' => $fila, 'codigobarra_existente' => $barra];
                }
            }

            $fila = $filas->first();
            if ($fila === null) {
                $fila = Articulo_Proveedor::query()->create([
                    'articulo_id' => $articuloId,
                    'proveedor_id' => $proveedorId,
                    'activo' => true,
                    'preferido' => false,
                    'nombre_articulo_proveedor' => null,
                    'codigobarra' => null,
                    'codigo_articulo_proveedor' => null,
                ]);
            }

            return ['fila' => $fila, 'codigobarra_existente' => null];
        }

        $existentes = Articulo_Proveedor::query()
            ->where('articulo_id', $articuloId)
            ->where('activo', true)
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get();

        if ($existentes->isEmpty()) {
            return ['necesita_proveedor' => true];
        }

        $preferido = $existentes->first(static fn ($v) => (bool) $v->preferido);

        $elegida = $preferido ?? $existentes->first();
        $barra = self::normalizarCodigo((string) ($elegida->codigobarra ?? ''));

        return ['fila' => $elegida, 'codigobarra_existente' => $barra];
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, list<array{
     *   recepcion_id: int,
     *   fecha: string,
     *   numerorecepcion: string,
     *   numerofactura: string,
     *   proveedor_id: int,
     *   proveedor_codigo: string,
     *   proveedor_nombre: string
     * }>>
     */
    private function comprasRecientesPorArticulo(array $articuloIds): array
    {
        $articuloIds = array_values(array_unique(array_filter(
            array_map('intval', $articuloIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($articuloIds === []) {
            return [];
        }

        $filtroBorrado = Schema::hasColumn('recepcion_proveedor', 'deleted_at')
            ? ' AND rp.deleted_at IS NULL'
            : '';

        $out = [];
        foreach (array_chunk($articuloIds, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "
                SELECT articulo_id, recepcion_id, fecha, numerorecepcion, numerofactura,
                       proveedor_id, proveedor_codigo, proveedor_nombre
                FROM (
                    SELECT
                        base.articulo_id,
                        base.recepcion_id,
                        base.fecha,
                        base.numerorecepcion,
                        base.numerofactura,
                        base.proveedor_id,
                        base.proveedor_codigo,
                        base.proveedor_nombre,
                        ROW_NUMBER() OVER (
                            PARTITION BY base.articulo_id
                            ORDER BY base.fecha DESC, base.recepcion_id DESC
                        ) AS rn
                    FROM (
                        SELECT
                            rpa.articulo_id,
                            rp.id AS recepcion_id,
                            rp.fecha,
                            rp.numerorecepcion,
                            rp.numerofactura,
                            rp.proveedor_id,
                            p.codigo AS proveedor_codigo,
                            p.nombre AS proveedor_nombre
                        FROM recepcion_proveedor_articulo rpa
                        INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                        INNER JOIN proveedor p ON p.id = rp.proveedor_id
                        WHERE rpa.articulo_id IN ($placeholders)
                          AND rp.estado = ?
                          AND rp.tipo = ?
                          AND rp.proveedor_id IS NOT NULL
                          $filtroBorrado
                        GROUP BY rpa.articulo_id, rp.id, rp.fecha, rp.numerorecepcion, rp.numerofactura,
                                 rp.proveedor_id, p.codigo, p.nombre
                    ) base
                ) ranked
                WHERE rn <= ?
                ORDER BY articulo_id, rn
            ";

            $bindings = array_merge(
                $chunk,
                [Recepcion_Proveedor::ESTADO_CONFIRMADA, Recepcion_Proveedor::TIPO_RECEPCION, self::COMPRAS_RECIENTES]
            );
            foreach (DB::select($sql, $bindings) as $row) {
                $articuloId = (int) $row->articulo_id;
                $out[$articuloId][] = [
                    'recepcion_id' => (int) $row->recepcion_id,
                    'fecha' => (string) ($row->fecha ?? ''),
                    'numerorecepcion' => trim((string) ($row->numerorecepcion ?? '')),
                    'numerofactura' => trim((string) ($row->numerofactura ?? '')),
                    'proveedor_id' => (int) $row->proveedor_id,
                    'proveedor_codigo' => trim((string) ($row->proveedor_codigo ?? '')),
                    'proveedor_nombre' => trim((string) ($row->proveedor_nombre ?? '')),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array{articulo_id: int, sku: string, descripcion: string, saldo: float}  $fila
     * @param  list<array{
     *   recepcion_id: int,
     *   fecha: string,
     *   numerorecepcion: string,
     *   numerofactura: string,
     *   proveedor_id: int,
     *   proveedor_codigo: string,
     *   proveedor_nombre: string
     * }>  $compras
     * @return array<string, mixed>|null
     */
    private function armarPendiente(array $fila, array $compras, ?Collection $vinculos, bool $conservarConCodigo = false): ?array
    {
        $barraPorProveedor = $this->barraPorProveedor($vinculos);
        $comprasOut = [];
        $elegida = null;

        foreach ($compras as $compra) {
            $proveedorId = (int) $compra['proveedor_id'];
            if ($proveedorId <= 0) {
                continue;
            }
            $barra = $barraPorProveedor[$proveedorId] ?? null;
            $item = [
                'recepcion_id' => (int) $compra['recepcion_id'],
                'fecha' => $this->formatearFecha((string) $compra['fecha']),
                'numerorecepcion' => (string) $compra['numerorecepcion'],
                'numerofactura' => (string) $compra['numerofactura'],
                'proveedor_id' => $proveedorId,
                'proveedor_nombre' => $this->etiquetaProveedor(
                    (string) $compra['proveedor_codigo'],
                    (string) $compra['proveedor_nombre']
                ),
                'codigobarra' => $barra ?? '',
                'tiene_codigo' => $barra !== null,
                'seleccionada' => false,
            ];
            $item['etiqueta'] = $this->etiquetaCompra($item);
            if ($elegida === null && $barra === null) {
                $item['seleccionada'] = true;
                $elegida = $item;
            }
            $comprasOut[] = $item;
        }

        if ($comprasOut !== []) {
            if ($elegida === null) {
                if (! $conservarConCodigo) {
                    return null;
                }
                $comprasOut[0]['seleccionada'] = true;
                $elegida = $comprasOut[0];
            }

            $fila['compras'] = $comprasOut;
            $fila['recepcion_id'] = (int) $elegida['recepcion_id'];
            $fila['proveedor_id'] = (int) $elegida['proveedor_id'];
            $fila['proveedor_nombre'] = (string) $elegida['proveedor_nombre'];
            $fila['necesita_proveedor'] = false;

            return $fila;
        }

        $activos = $vinculos?->filter(static fn ($v) => (bool) $v->activo) ?? collect();
        $sinCodigo = $activos->filter(function ($v): bool {
            return self::normalizarCodigo((string) ($v->codigobarra ?? '')) === null;
        });

        if ($activos->isNotEmpty() && $sinCodigo->isEmpty()) {
            if (! $conservarConCodigo) {
                return null;
            }
            $sinCodigo = $activos;
        }

        $elegido = $sinCodigo->first(static fn ($v) => (bool) $v->preferido) ?? $sinCodigo->first();
        $fila['compras'] = [];
        $fila['recepcion_id'] = null;
        $fila['necesita_proveedor'] = $elegido === null;
        $fila['proveedor_id'] = $elegido ? (int) $elegido->proveedor_id : null;
        $fila['proveedor_nombre'] = $elegido
            ? $this->etiquetaProveedor(
                (string) ($elegido->proveedores->codigo ?? ''),
                (string) ($elegido->proveedores->nombre ?? '')
            )
            : null;

        return $fila;
    }

    /**
     * @return array<int, string|null>
     */
    private function barraPorProveedor(?Collection $vinculos): array
    {
        $map = [];
        if ($vinculos === null) {
            return $map;
        }

        foreach ($vinculos as $vinculo) {
            $proveedorId = (int) $vinculo->proveedor_id;
            if ($proveedorId <= 0) {
                continue;
            }
            $barra = self::normalizarCodigo((string) ($vinculo->codigobarra ?? ''));
            if ($barra !== null) {
                $map[$proveedorId] = $barra;
            } elseif (! array_key_exists($proveedorId, $map)) {
                $map[$proveedorId] = null;
            }
        }

        return $map;
    }

    /**
     * @param  array{
     *   fecha: string,
     *   numerorecepcion: string,
     *   numerofactura: string,
     *   proveedor_nombre: string
     * }  $compra
     */
    private function etiquetaCompra(array $compra): string
    {
        $partes = [];
        if ($compra['fecha'] !== '') {
            $partes[] = $compra['fecha'];
        }
        if ($compra['numerorecepcion'] !== '') {
            $partes[] = 'Recepción '.$compra['numerorecepcion'];
        }
        if ($compra['numerofactura'] !== '') {
            $partes[] = 'Fact. '.$compra['numerofactura'];
        }
        if ($compra['proveedor_nombre'] !== '') {
            $partes[] = $compra['proveedor_nombre'];
        }

        return $partes !== [] ? implode(' · ', $partes) : 'Compra';
    }

    private function etiquetaProveedor(string $codigo, string $nombre): string
    {
        return trim(trim($codigo).' '.trim($nombre));
    }

    private function formatearFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }
        $ts = strtotime($fecha);

        return $ts ? date('d/m/Y', $ts) : $fecha;
    }

    private function conflictoCodigo(string $codigo, int $articuloId, int $proveedorId): ?string
    {
        $variantes = TransferenciaMercaderiaPickeoSupport::variantesCodigo($codigo);
        if ($variantes === []) {
            return 'Código de barras inválido.';
        }

        if ($proveedorId <= 0) {
            return null;
        }

        // El EAN del envase se repite entre proveedores. Solo choca si el mismo
        // proveedor ya lo tiene en otro artículo: la recepción busca por proveedor + código.
        $otroProv = Articulo_Proveedor::query()
            ->with('articulos:id,sku,descripcion')
            ->where('activo', true)
            ->where('proveedor_id', $proveedorId)
            ->where('articulo_id', '<>', $articuloId)
            ->where(function ($q) use ($variantes) {
                $q->whereIn('codigobarra', $variantes);
                foreach ($variantes as $v) {
                    $q->orWhereRaw('UPPER(TRIM(codigobarra)) = ?', [strtoupper($v)]);
                }
            })
            ->first();

        if ($otroProv !== null) {
            $sku = (string) ($otroProv->articulos->sku ?? '#'.$otroProv->articulo_id);

            return 'Este proveedor ya tiene el código en el artículo '.$sku.'.';
        }

        return null;
    }

    public static function normalizarCodigo(?string $codigo): ?string
    {
        $codigo = preg_replace('/\s+/', '', trim((string) $codigo)) ?? '';
        if ($codigo === '') {
            return null;
        }

        return substr($codigo, 0, 50);
    }
}
