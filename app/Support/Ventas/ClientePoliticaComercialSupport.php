<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Tiposuspensioncliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Tope de circuito comercial del cliente (pedido → boleta/OT/remito → factura).
 *
 * La categoría (MOROSO / PROFORMA / BLOQUEADO) es la política.
 * tiposuspensioncliente.nombre / leyenda son el motivo, no el efecto.
 */
final class ClientePoliticaComercialSupport
{
    public const NORMAL = 'NORMAL';

    public const MOROSO = 'MOROSO';

    public const PROFORMA = 'PROFORMA';

    public const BLOQUEADO = 'BLOQUEADO';

    public const OP_CONSULTAR = 'consultar';

    public const OP_PEDIDO = 'pedido';

    public const OP_BOLETA = 'boleta';

    public const OP_FACTURA = 'factura';

    public const OP_COBRANZA = 'cobranza';

    /** @var array<string, string> */
    private const ALIAS_CODIGO = [
        'NORMAL' => self::NORMAL,
        'MOROSO' => self::MOROSO,
        'MOROSOS' => self::MOROSO,
        'PROFORMA' => self::PROFORMA,
        'NO_FACTURAR' => self::PROFORMA,
        'NOFACTURAR' => self::PROFORMA,
        'BLOQUEADO' => self::BLOQUEADO,
        'SUSPENDIDO' => self::BLOQUEADO,
        'SUSPENDIDOS' => self::BLOQUEADO,
    ];

    /** @var array<string, array<string, bool>> */
    private const MATRIZ = [
        self::NORMAL => [
            self::OP_CONSULTAR => true,
            self::OP_COBRANZA => true,
            self::OP_PEDIDO => true,
            self::OP_BOLETA => true,
            self::OP_FACTURA => true,
        ],
        self::MOROSO => [
            self::OP_CONSULTAR => true,
            self::OP_COBRANZA => true,
            self::OP_PEDIDO => true,
            self::OP_BOLETA => false,
            self::OP_FACTURA => false,
        ],
        self::PROFORMA => [
            self::OP_CONSULTAR => true,
            self::OP_COBRANZA => true,
            self::OP_PEDIDO => true,
            self::OP_BOLETA => true,
            self::OP_FACTURA => false,
        ],
        self::BLOQUEADO => [
            self::OP_CONSULTAR => true,
            self::OP_COBRANZA => true,
            self::OP_PEDIDO => false,
            self::OP_BOLETA => false,
            self::OP_FACTURA => false,
        ],
    ];

    /** @var array<string, string> */
    private const ETIQUETAS = [
        self::NORMAL => 'Normal',
        self::MOROSO => 'Moroso',
        self::PROFORMA => 'Proforma',
        self::BLOQUEADO => 'Suspendido',
    ];

    /** @var Collection<int, Tiposuspensioncliente>|null */
    private static ?Collection $tiposCache = null;

    public static function normalizarOperacion(mixed $valor): string
    {
        $op = strtolower(trim((string) ($valor ?? '')));

        return match ($op) {
            self::OP_PEDIDO, 'carga_pedido', 'ordenventa' => self::OP_PEDIDO,
            self::OP_BOLETA, 'ot', 'ordentrabajo', 'remito', 'despacho' => self::OP_BOLETA,
            self::OP_FACTURA, 'facturacion', 'comprobante' => self::OP_FACTURA,
            self::OP_COBRANZA, 'cobro', 'recibo' => self::OP_COBRANZA,
            default => self::OP_CONSULTAR,
        };
    }

    public static function politica(?Cliente $cliente): string
    {
        if ($cliente === null) {
            return self::BLOQUEADO;
        }

        $tipo = self::tipoDe($cliente);
        $politicaTipo = $tipo !== null ? self::politicaDesdeTipo($tipo) : null;
        if ($politicaTipo !== null) {
            return $politicaTipo;
        }

        if ((string) ($cliente->estado ?? '') === Cliente::ESTADO_SUSPENDIDO) {
            return self::BLOQUEADO;
        }

        return self::NORMAL;
    }

    public static function permite(?Cliente $cliente, string $operacion): bool
    {
        $op = self::normalizarOperacion($operacion);
        $politica = self::politica($cliente);

        return (bool) (self::MATRIZ[$politica][$op] ?? false);
    }

    /**
     * @return array{error: string}|null
     */
    public static function errorSiNoPermite(?Cliente $cliente, string $operacion): ?array
    {
        if ($cliente === null) {
            return ['error' => 'Cliente inexistente'];
        }

        if (self::permite($cliente, $operacion)) {
            return null;
        }

        return ['error' => self::mensaje($cliente, $operacion)];
    }

    /**
     * @return array{error: string}|null
     */
    public static function errorSiNoPermiteFactura(?Cliente $cliente, mixed $tipotransaccion = null): ?array
    {
        if ($tipotransaccion && method_exists($tipotransaccion, 'esNotaCredito') && $tipotransaccion->esNotaCredito()) {
            return null;
        }

        return self::errorSiNoPermite($cliente, self::OP_FACTURA);
    }

    public static function mensaje(?Cliente $cliente, string $operacion): string
    {
        if ($cliente === null) {
            return 'Cliente inexistente.';
        }

        $op = self::normalizarOperacion($operacion);
        $politica = self::politica($cliente);
        $nombre = trim((string) ($cliente->nombre ?? ''));
        $quien = $nombre !== '' ? 'El cliente «'.$nombre.'»' : 'El cliente';
        $motivo = self::motivoDe($cliente);
        $leyenda = self::leyendaCorta($cliente);

        $texto = match ([$politica, $op]) {
            [self::BLOQUEADO, self::OP_PEDIDO] => $quien.' está suspendido y no puede cargarse en pedidos.',
            [self::BLOQUEADO, self::OP_BOLETA] => $quien.' está suspendido: no se pueden generar boletas, OT ni remitos.',
            [self::BLOQUEADO, self::OP_FACTURA] => $quien.' está suspendido: no se puede facturar.',
            [self::MOROSO, self::OP_BOLETA] => $quien.' es moroso: se puede cargar el pedido, no se pueden generar boletas/OT.',
            [self::MOROSO, self::OP_FACTURA] => $quien.' es moroso: no se puede facturar.',
            [self::PROFORMA, self::OP_FACTURA] => $quien.' es proforma: se puede pedir y boletar, no se puede facturar hasta el cobro.',
            default => $quien.' no puede realizar esta operación ('.(self::ETIQUETAS[$politica] ?? $politica).').',
        };

        if ($motivo !== '') {
            $texto .= ' Motivo: '.$motivo.'.';
        }
        if ($leyenda !== '') {
            $texto .= ' '.$leyenda;
        }

        return $texto;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(?Cliente $cliente): array
    {
        $politica = self::politica($cliente);
        $permite = self::MATRIZ[$politica] ?? self::MATRIZ[self::BLOQUEADO];
        $etiqueta = self::ETIQUETAS[$politica] ?? $politica;
        $motivo = $cliente !== null ? self::motivoDe($cliente) : '';
        $leyenda = $cliente !== null ? trim((string) ($cliente->leyenda ?? '')) : '';
        $banner = $politica === self::NORMAL
            ? ''
            : $etiqueta.($motivo !== '' ? ' — '.$motivo : '');

        return [
            'politica' => $politica,
            'etiqueta' => $etiqueta,
            'motivo' => $motivo,
            'leyenda' => $leyenda,
            'banner' => $banner,
            'permite' => $permite,
            'mensajes' => [
                self::OP_PEDIDO => $permite[self::OP_PEDIDO] ? null : self::mensaje($cliente, self::OP_PEDIDO),
                self::OP_BOLETA => $permite[self::OP_BOLETA] ? null : self::mensaje($cliente, self::OP_BOLETA),
                self::OP_FACTURA => $permite[self::OP_FACTURA] ? null : self::mensaje($cliente, self::OP_FACTURA),
            ],
        ];
    }

    public static function anexarPayloadAlCliente(?Cliente $cliente): ?Cliente
    {
        if ($cliente === null) {
            return null;
        }

        $cliente->setAttribute('politica_comercial', self::payload($cliente));

        return $cliente;
    }

    public static function aplicarFiltroQuery(Builder $query, string $contexto): Builder
    {
        $op = self::normalizarOperacion($contexto);
        $tabla = $query->getModel()->getTable();

        $query->where($tabla.'.nombre', '!=', ' ');

        if ($op === self::OP_CONSULTAR || $op === self::OP_COBRANZA) {
            return $query;
        }

        $idsBloquean = self::idsTipoQueNoPermiten($op);
        $idsPermitenHold = self::idsTipoQuePermiten($op);

        return $query->where(function (Builder $outer) use ($tabla, $idsBloquean, $idsPermitenHold) {
            $outer->where(function (Builder $q) use ($tabla, $idsBloquean) {
                $q->whereNull($tabla.'.tiposuspension_id')
                    ->orWhere($tabla.'.tiposuspension_id', 0);
                if ($idsBloquean !== []) {
                    $q->orWhereNotIn($tabla.'.tiposuspension_id', $idsBloquean);
                }
            })->where(function (Builder $q) use ($tabla, $idsPermitenHold) {
                $q->where($tabla.'.estado', '!=', Cliente::ESTADO_SUSPENDIDO);
                if ($idsPermitenHold !== []) {
                    $q->orWhereIn($tabla.'.tiposuspension_id', $idsPermitenHold);
                }
            });
        });
    }

    /**
     * @param  list<string>  $campos
     */
    public static function queryParaContexto(array $campos, string $contexto): Builder
    {
        return self::aplicarFiltroQuery(
            Cliente::query()->select($campos),
            $contexto
        );
    }

    private static function tipoDe(Cliente $cliente): ?Tiposuspensioncliente
    {
        if ($cliente->relationLoaded('tipossuspensioncliente') && $cliente->tipossuspensioncliente) {
            return $cliente->tipossuspensioncliente;
        }

        $id = (int) ($cliente->tiposuspension_id ?? 0);
        if ($id <= 0) {
            return null;
        }

        return self::tipos()->get($id);
    }

    private static function politicaDesdeTipo(Tiposuspensioncliente $tipo): ?string
    {
        $codigo = strtoupper(trim((string) ($tipo->codigo ?? '')));
        if ($codigo !== '' && isset(self::ALIAS_CODIGO[$codigo])) {
            return self::ALIAS_CODIGO[$codigo];
        }

        $nombre = strtoupper(self::sinAcentos((string) ($tipo->nombre ?? '')));
        if ($nombre === '') {
            return null;
        }
        if (str_contains($nombre, 'MOROSO')) {
            return self::MOROSO;
        }
        if (str_contains($nombre, 'PROFORMA')) {
            return self::PROFORMA;
        }
        if (str_contains($nombre, 'NO FACTURAR') || str_contains($nombre, 'NO_FACTURAR')) {
            return self::PROFORMA;
        }
        if (str_contains($nombre, 'SUSPENDIDO') || str_contains($nombre, 'BLOQUEADO')) {
            return self::BLOQUEADO;
        }
        if (str_contains($nombre, 'APOCRIF') || str_contains($nombre, 'APOC')) {
            return self::BLOQUEADO;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private static function idsTipoQueNoPermiten(string $operacion): array
    {
        $ids = [];
        foreach (self::tipos() as $tipo) {
            $politica = self::politicaDesdeTipo($tipo);
            if ($politica === null) {
                continue;
            }
            if (! (self::MATRIZ[$politica][$operacion] ?? false)) {
                $ids[] = (int) $tipo->id;
            }
        }

        return $ids;
    }

    /**
     * Tipos con política de hold que igual permiten la operación (ej. moroso en pedido).
     *
     * @return list<int>
     */
    private static function idsTipoQuePermiten(string $operacion): array
    {
        $ids = [];
        foreach (self::tipos() as $tipo) {
            $politica = self::politicaDesdeTipo($tipo);
            if ($politica === null || $politica === self::NORMAL) {
                continue;
            }
            if (self::MATRIZ[$politica][$operacion] ?? false) {
                $ids[] = (int) $tipo->id;
            }
        }

        return $ids;
    }

    /**
     * @return Collection<int, Tiposuspensioncliente>
     */
    private static function tipos(): Collection
    {
        if (self::$tiposCache !== null) {
            return self::$tiposCache;
        }

        $query = Tiposuspensioncliente::query();
        $columnas = ['id', 'nombre'];
        try {
            if (Schema::hasColumn('tiposuspensioncliente', 'codigo')) {
                $columnas[] = 'codigo';
            }
        } catch (\Throwable) {
            // sin columna aún (migración pendiente): se resuelve por nombre
        }

        self::$tiposCache = $query->get($columnas)->keyBy('id');

        return self::$tiposCache;
    }

    private static function motivoDe(Cliente $cliente): string
    {
        $tipo = self::tipoDe($cliente);

        return $tipo !== null ? trim((string) ($tipo->nombre ?? '')) : '';
    }

    private static function leyendaCorta(Cliente $cliente): string
    {
        $leyenda = trim((string) ($cliente->leyenda ?? ''));
        if ($leyenda === '') {
            return '';
        }
        $unaLinea = preg_replace('/\s+/', ' ', $leyenda) ?? $leyenda;
        if (mb_strlen($unaLinea) > 160) {
            return 'Leyenda: '.mb_substr($unaLinea, 0, 157).'…';
        }

        return 'Leyenda: '.$unaLinea;
    }

    private static function sinAcentos(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return $convertido !== false ? $convertido : $texto;
    }

    public static function idTipoPorPolitica(string $politica): ?int
    {
        foreach (self::tipos() as $tipo) {
            if (self::politicaDesdeTipo($tipo) === $politica) {
                return (int) $tipo->id;
            }
        }

        return null;
    }

    /**
     * Hoja Ferli: al cobrar un moroso pasa a proforma (pide y boleta; no factura).
     * No toca BLOQUEADO. Audita vía Eloquent.
     */
    public static function pasarMorosoAProformaPorCobranza(?Cliente $cliente): ?string
    {
        if ($cliente === null || self::politica($cliente) !== self::MOROSO) {
            return null;
        }

        $idProforma = self::idTipoPorPolitica(self::PROFORMA);
        if ($idProforma === null) {
            return null;
        }

        $cliente->tiposuspension_id = $idProforma;
        $cliente->save();

        $nombre = trim((string) ($cliente->nombre ?? ''));
        $quien = $nombre !== '' ? 'El cliente «'.$nombre.'»' : 'El cliente';

        return $quien.' pasó de moroso a proforma por esta cobranza: puede pedir y boletar; no se factura hasta regularizar.';
    }
}
