<?php

namespace App\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\AnitaSync\RendicionGastronomiaRendvalorCodigoSupport;
use Illuminate\Support\Collection;

/**
 * Orden de medios en posición financiera.
 *
 * Las cuentas son distintas por empresa; el campo cuentacaja.orden es el
 * número compartido (10, 20, 30…) para que Gastronomía / Estacionamiento /
 * Vending / Máquinas salgan en el mismo lugar. Referencia: Biyemas.
 */
final class PosicionFinancieraOrdenConceptoSupport
{
    public const USO_GASTRONOMIA = 'Gastronomia';

    public const USO_ESTACIONAMIENTO = 'Estacionamiento';

    public const USO_MAQUINAS = 'Rendición de máquinas';

    /** @var array<string, int> */
    public const ORDEN_FAMILIA_GASTRO = [
        RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_FISERV => 10,
        RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_TOTALCOIN => 20,
        RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_EFECTIVO => 30,
        RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_CANJE_TARJETA => 40,
        RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_MERCADOPAGO => 50,
    ];

    /** @var array<string, int> */
    public const ORDEN_FAMILIA_MAQUINA = [
        'efectivo' => 10,
        'dolar' => 20,
        'euro' => 30,
        'cripto' => 40,
        'mep' => 50,
        'qr' => 60,
        'totalcoin_caja' => 70,
        'totalcoin_maq' => 80,
        'deposito_qr' => 90,
        'transferencia' => 100,
    ];

    /** @var list<string> */
    private const ETIQUETAS_FIJAS_GASTRO = [
        'Notas de credito',
        'Diferencia abandono de pago',
        'Redondeo',
        'Diferencia de caja',
    ];

    /**
     * @return array<string, string> uso BD => etiqueta pantalla
     */
    public static function usosHerramienta(): array
    {
        return [
            self::USO_GASTRONOMIA => 'Gastronomía / Vending',
            self::USO_ESTACIONAMIENTO => 'Estacionamiento',
            self::USO_MAQUINAS => 'Máquinas',
        ];
    }

    /**
     * Empresas que tienen cuenta propia en los usos del informe (Biyemas / Kandiko / Rebisco).
     *
     * @return Collection<int, Empresa>
     */
    public static function empresasParaPreview(): Collection
    {
        $ids = Cuentacaja::query()
            ->whereNotNull('empresa_id')
            ->whereHas(
                'usocuentacajas',
                fn ($query) => $query->whereIn('usocuentacaja.nombre', array_keys(self::usosHerramienta()))
            )
            ->pluck('empresa_id')
            ->unique()
            ->filter(fn ($id) => (int) $id > 0)
            ->values();

        $query = Empresa::query()->orderBy('id');
        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        return $query->get();
    }

    /**
     * Preview por uso: una fila = un concepto equivalente en las 3 empresas.
     *
     * @param  Collection<int, Empresa>|iterable<int, Empresa>  $empresas
     * @return list<array{
     *   uso: string,
     *   label: string,
     *   alineado: bool,
     *   filas: list<array<string, mixed>>
     * }>
     */
    public static function armarPreview(?iterable $empresas = null): array
    {
        $empresas = Collection::make($empresas ?? self::empresasParaPreview())->values();
        $bloques = [];

        foreach (self::usosHerramienta() as $uso => $label) {
            $filas = self::filasPreviewUso($uso, $empresas);
            $bloques[] = [
                'uso' => $uso,
                'label' => $label,
                'alineado' => self::filasAlineadas($filas, $empresas),
                'filas' => $filas,
            ];
        }

        return $bloques;
    }

    /**
     * @param  list<array{uso?: string, ids?: list<int|string>, orden?: int|string}>  $filas
     */
    public static function guardarFilas(array $filas): int
    {
        $actualizados = 0;
        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $uso = (string) ($fila['uso'] ?? '');
            $usoId = self::idUso($uso);
            $orden = max(0, (int) ($fila['orden'] ?? 0));
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) ($fila['ids'] ?? [])),
                static fn (int $id) => $id > 0
            )));
            if ($usoId <= 0 || $ids === []) {
                continue;
            }
            foreach (Cuentacaja::query()->whereIn('id', $ids)->get() as $cuenta) {
                $actual = (int) ($cuenta->usocuentacajas()
                    ->where('usocuentacaja.id', $usoId)
                    ->first()?->pivot?->orden ?? 0);
                if ($actual === $orden) {
                    continue;
                }
                $cuenta->usocuentacajas()->updateExistingPivot($usoId, ['orden' => $orden]);
                $actualizados++;
            }
        }

        return $actualizados;
    }

    public static function aplicarOrdenBiyemas(): int
    {
        $actualizados = 0;
        foreach (self::usosHerramienta() as $uso => $_) {
            foreach (self::cuentasDelUso($uso) as $cuenta) {
                if ($uso !== self::USO_MAQUINAS
                    && RendicionGastronomiaRendvalorCodigoSupport::omitirEnRendvalorAnita($cuenta)
                ) {
                    continue;
                }
                $familia = self::familiaDeCuenta($uso, $cuenta);
                $ordenCanonico = self::ordenCanonicoFamilia($uso, $familia);
                $usoId = self::idUso($uso);
                if ($usoId <= 0) {
                    continue;
                }
                $actual = (int) ($cuenta->usocuentacajas->firstWhere('nombre', $uso)?->pivot?->orden ?? 0);
                if ($actual === $ordenCanonico) {
                    continue;
                }
                $cuenta->usocuentacajas()->updateExistingPivot($usoId, ['orden' => $ordenCanonico]);
                $actualizados++;
            }
        }

        return $actualizados;
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>  $codigosPermitidos
     * @param  class-string  $mapperClass
     * @return array<int, array{desc: string, tipo: string}>
     */
    public static function ordenarValormaePermitidos(
        int $empresaId,
        array $valormae,
        array $codigosPermitidos,
        string $mapperClass,
    ): array {
        $items = [];
        foreach ($valormae as $codigo => $meta) {
            $codigo = (int) $codigo;
            if (! isset($codigosPermitidos[$codigo])) {
                continue;
            }
            $items[] = [
                'codigo' => $codigo,
                'meta' => $meta,
                'orden' => self::ordenParaCodigoValormae($empresaId, $codigo, $valormae, $mapperClass),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['orden'] !== $b['orden']) {
                return $a['orden'] <=> $b['orden'];
            }

            return $a['codigo'] <=> $b['codigo'];
        });

        $out = [];
        foreach ($items as $item) {
            $out[$item['codigo']] = $item['meta'];
        }

        return $out;
    }

    /**
     * Cabecera fija, después los medios ordenados, el Total al final del bloque.
     *
     * @param  array<string, array<int, float>>  $filas
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>  $codigosPermitidos
     * @param  class-string  $mapperClass
     * @return array<string, array<int, float>>
     */
    public static function reordenarBloqueGastro(
        array $filas,
        int $empresaId,
        array $valormae,
        array $codigosPermitidos,
        string $mapperClass,
        string $etiquetaZ,
        string $etiquetaTotal,
    ): array {
        $prefijo = [$etiquetaZ, ...self::ETIQUETAS_FIJAS_GASTRO];
        $fijas = [...$prefijo, $etiquetaTotal];
        $rankMedio = [];
        foreach (self::ordenarValormaePermitidos($empresaId, $valormae, $codigosPermitidos, $mapperClass) as $meta) {
            $desc = trim((string) ($meta['desc'] ?? ''));
            if ($desc !== '' && ! isset($rankMedio[$desc])) {
                $rankMedio[$desc] = count($rankMedio);
            }
        }

        $out = [];
        foreach ($prefijo as $etiqueta) {
            if (array_key_exists($etiqueta, $filas)) {
                $out[$etiqueta] = $filas[$etiqueta];
            }
        }

        $medios = [];
        foreach ($filas as $etiqueta => $porDia) {
            if (in_array($etiqueta, $fijas, true)) {
                continue;
            }
            $medios[$etiqueta] = $porDia;
        }
        uksort($medios, function (string $a, string $b) use ($rankMedio) {
            $ra = $rankMedio[$a] ?? 1000;
            $rb = $rankMedio[$b] ?? 1000;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcasecmp($a, $b);
        });

        foreach ($medios as $etiqueta => $porDia) {
            $out[$etiqueta] = $porDia;
        }

        if (array_key_exists($etiquetaTotal, $filas)) {
            $out[$etiquetaTotal] = $filas[$etiquetaTotal];
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, float>>  $medios
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, array<int, float>>
     */
    public static function reordenarMapaMedios(int $empresaId, array $medios, array $valormae): array
    {
        if ($medios === []) {
            return $medios;
        }

        $rank = self::rankEtiquetasMaquina($empresaId, $valormae);
        uksort($medios, function (string $a, string $b) use ($rank) {
            $ra = $rank[$a] ?? 1000;
            $rb = $rank[$b] ?? 1000;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcasecmp($a, $b);
        });

        return $medios;
    }

    /**
     * @param  Collection<int, Empresa>  $empresas
     * @return list<array<string, mixed>>
     */
    private static function filasPreviewUso(string $uso, Collection $empresas): array
    {
        $grupos = [];
        foreach (self::cuentasDelUso($uso) as $cuenta) {
            if ($uso !== self::USO_MAQUINAS
                && RendicionGastronomiaRendvalorCodigoSupport::omitirEnRendvalorAnita($cuenta)
            ) {
                continue;
            }
            $familia = self::familiaDeCuenta($uso, $cuenta);
            if (! isset($grupos[$familia])) {
                $grupos[$familia] = [
                    'clave' => $familia,
                    'concepto' => self::etiquetaFamilia($uso, $familia, $cuenta),
                    'orden' => self::ordenDeCuentaEnUso($cuenta, $uso),
                    'ids' => [],
                    'cuentas' => [],
                ];
            }
            $grupos[$familia]['ids'][] = (int) $cuenta->id;
            $grupos[$familia]['cuentas'][] = $cuenta;
            $orden = self::ordenDeCuentaEnUso($cuenta, $uso);
            if ($orden > 0 && ($grupos[$familia]['orden'] === 0 || $orden < $grupos[$familia]['orden'])) {
                $grupos[$familia]['orden'] = $orden;
            }
        }

        $filas = [];
        foreach ($grupos as $grupo) {
            $porEmpresa = [];
            foreach ($empresas as $empresa) {
                $empresaId = (int) $empresa->id;
                $elegida = self::cuentaParaEmpresa($grupo['cuentas'], $empresaId);
                $porEmpresa[$empresaId] = $elegida === null ? null : [
                    'id' => (int) $elegida->id,
                    'codigo' => (string) $elegida->codigo,
                    'nombre' => (string) $elegida->nombre,
                    'etiqueta' => $elegida->etiquetaOperaciones(),
                    'multiempresa' => $elegida->empresa_id === null,
                ];
            }
            $filas[] = [
                'clave' => $grupo['clave'],
                'concepto' => $grupo['concepto'],
                'orden' => $grupo['orden'],
                'ids' => array_values(array_unique($grupo['ids'])),
                'cuentas_por_empresa' => $porEmpresa,
            ];
        }

        usort($filas, function (array $a, array $b) {
            $oa = (int) $a['orden'];
            $ob = (int) $b['orden'];
            if ($oa === 0 && $ob !== 0) {
                return 1;
            }
            if ($ob === 0 && $oa !== 0) {
                return -1;
            }
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcasecmp((string) $a['concepto'], (string) $b['concepto']);
        });

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  Collection<int, Empresa>  $empresas
     */
    private static function filasAlineadas(array $filas, Collection $empresas): bool
    {
        if ($filas === [] || $empresas->count() < 2) {
            return true;
        }

        foreach ($filas as $fila) {
            $presentes = 0;
            foreach ($empresas as $empresa) {
                if (($fila['cuentas_por_empresa'][(int) $empresa->id] ?? null) !== null) {
                    $presentes++;
                }
            }
            if ($presentes === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, Cuentacaja>
     */
    private static function cuentasDelUso(string $uso): Collection
    {
        return Cuentacaja::query()
            ->with(['empresas:id,nombre', 'usocuentacajas'])
            ->whereHas('usocuentacajas', fn ($query) => $query->where('usocuentacaja.nombre', $uso))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @param  list<Cuentacaja>  $cuentas
     */
    private static function cuentaParaEmpresa(array $cuentas, int $empresaId): ?Cuentacaja
    {
        $propia = null;
        $compartida = null;
        foreach ($cuentas as $cuenta) {
            if ((int) $cuenta->empresa_id === $empresaId) {
                $propia = $cuenta;
                break;
            }
            if ($cuenta->empresa_id === null) {
                $compartida = $cuenta;
            }
        }

        return $propia ?? $compartida;
    }

    private static function familiaDeCuenta(string $uso, Cuentacaja $cuenta): string
    {
        if ($uso === self::USO_MAQUINAS) {
            return self::familiaMaquina($cuenta);
        }

        $familia = RendicionGastronomiaRendvalorCodigoSupport::familiaDesdeCuentacaja($cuenta);
        if ($familia !== null) {
            return $familia;
        }

        return 'cuenta_'.(int) $cuenta->id;
    }

    private static function familiaMaquina(Cuentacaja $cuenta): string
    {
        $texto = mb_strtoupper(trim((string) $cuenta->nombre).' '.trim((string) $cuenta->codigo).' '.trim((string) $cuenta->descripcion_operaciones));
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        if (str_contains($texto, 'CRIPTO') || str_contains($texto, 'USDT') || str_contains($texto, 'SATOSHI')) {
            return 'cripto';
        }
        if (str_contains($texto, 'DOLAR') || str_contains($texto, 'DÓLAR')) {
            return 'dolar';
        }
        if (str_contains($texto, 'EURO')) {
            return 'euro';
        }
        if (str_contains($texto, 'MEP')) {
            return 'mep';
        }
        if (str_contains($texto, 'DEPOSITO') && str_contains($texto, 'QR')) {
            return 'deposito_qr';
        }
        if (str_contains($texto, 'TOTALCOIN') || str_contains($texto, 'TOTAL COIN')) {
            return str_contains($texto, 'CAJA') ? 'totalcoin_caja' : 'totalcoin_maq';
        }
        if (preg_match('/\bQR\b/', $texto)) {
            return 'qr';
        }
        if (
            str_contains($texto, 'MACRO')
            || str_contains($texto, 'ITAU')
            || str_contains($texto, 'TRANSF')
            || str_contains($texto, 'CHECK MS')
        ) {
            return 'transferencia';
        }
        if (str_contains($texto, 'EFECTIVO') || str_contains($texto, 'CAJA PESOS')) {
            return 'efectivo';
        }

        return 'cuenta_'.(int) $cuenta->id;
    }

    private static function etiquetaFamilia(string $uso, string $familia, Cuentacaja $cuenta): string
    {
        $nombresGastro = [
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_FISERV => 'FISERV',
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_TOTALCOIN => 'Totalcoin QR Caja',
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_EFECTIVO => 'Efectivo pesos',
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_CANJE_TARJETA => 'Canje / ticket',
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_MERCADOPAGO => 'Mercado Pago',
        ];
        $nombresMaquina = [
            'efectivo' => 'Efectivo pesos',
            'dolar' => 'Efectivo dólares',
            'euro' => 'Efectivo euros',
            'cripto' => 'Efectivo cripto USDT',
            'mep' => 'MEP Máquinas',
            'qr' => 'QR Máquinas',
            'totalcoin_caja' => 'Totalcoin QR Caja',
            'totalcoin_maq' => 'Totalcoin QR Máquina',
            'deposito_qr' => 'Depósito efectivo pago QR',
            'transferencia' => 'Transferencia / Check MS',
        ];

        if ($uso === self::USO_MAQUINAS) {
            return $nombresMaquina[$familia] ?? $cuenta->etiquetaOperaciones();
        }

        return $nombresGastro[$familia] ?? $cuenta->etiquetaOperaciones();
    }

    private static function ordenCanonicoFamilia(string $uso, string $familia): int
    {
        $mapa = $uso === self::USO_MAQUINAS
            ? self::ORDEN_FAMILIA_MAQUINA
            : self::ORDEN_FAMILIA_GASTRO;

        return (int) ($mapa[$familia] ?? 900);
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  class-string  $mapperClass
     */
    private static function ordenParaCodigoValormae(
        int $empresaId,
        int $codigo,
        array $valormae,
        string $mapperClass,
    ): int {
        $familia = self::familiaDesdeCodigoValormae($empresaId, $codigo, $mapperClass);
        if ($familia !== null) {
            $usos = str_contains($mapperClass, 'Estacionamiento')
                ? [self::USO_ESTACIONAMIENTO]
                : [self::USO_GASTRONOMIA];
            $cuenta = self::cuentaPorFamilia($empresaId, $familia, $usos);
            if ($cuenta !== null) {
                $orden = self::ordenDeCuentaEnUso($cuenta, $usos[0]);
                if ($orden > 0) {
                    return $orden;
                }
            }

            return (int) (self::ORDEN_FAMILIA_GASTRO[$familia] ?? 900);
        }

        $desc = trim((string) ($valormae[$codigo]['desc'] ?? ''));
        if ($desc !== '') {
            $cuenta = self::cuentaPorEtiqueta($empresaId, $desc);
            if ($cuenta !== null) {
                $orden = (int) ($cuenta->orden ?? 0);
                if ($orden > 0) {
                    return $orden;
                }
            }
        }

        return 900 + $codigo;
    }

    /**
     * @param  class-string  $mapperClass
     */
    private static function familiaDesdeCodigoValormae(int $empresaId, int $codigo, string $mapperClass): ?string
    {
        $configKey = str_contains($mapperClass, 'Estacionamiento')
            ? 'rendicion_estacionamiento_anita.codigos_rendvalor'
            : 'rendicion_gastronomia_anita.codigos_rendvalor';
        $mapa = config($configKey, []);
        $empresa = $mapa[$empresaId] ?? [];
        if (! is_array($empresa)) {
            return null;
        }
        foreach ($empresa as $familia => $cod) {
            if ((int) $cod === $codigo) {
                return (string) $familia;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $usos
     */
    private static function cuentaPorFamilia(int $empresaId, string $familia, array $usos): ?Cuentacaja
    {
        $candidatas = Cuentacaja::query()
            ->with('usocuentacajas')
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($query) => $query->whereIn('usocuentacaja.nombre', $usos))
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Cuentacaja $cuenta) => $cuenta->empresa_id === null ? 1 : 0)
            ->values();

        foreach ($candidatas as $cuenta) {
            if (RendicionGastronomiaRendvalorCodigoSupport::familiaDesdeCuentacaja($cuenta) === $familia) {
                return $cuenta;
            }
        }

        return null;
    }

    private static function cuentaPorEtiqueta(int $empresaId, string $etiqueta): ?Cuentacaja
    {
        $needle = self::normalizarTexto($etiqueta);
        if ($needle === '') {
            return null;
        }

        $candidatas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'nombre', 'descripcion_operaciones', 'codigo', 'empresa_id', 'orden'])
            ->sortBy(fn (Cuentacaja $cuenta) => $cuenta->empresa_id === null ? 1 : 0)
            ->values();

        foreach ($candidatas as $cuenta) {
            $op = self::normalizarTexto($cuenta->etiquetaOperaciones());
            $nom = self::normalizarTexto((string) $cuenta->nombre);
            if ($op === $needle || $nom === $needle) {
                return $cuenta;
            }
        }
        foreach ($candidatas as $cuenta) {
            $op = self::normalizarTexto($cuenta->etiquetaOperaciones());
            if ($op !== '' && (str_contains($op, $needle) || str_contains($needle, $op))) {
                return $cuenta;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, int>
     */
    private static function rankEtiquetasMaquina(int $empresaId, array $valormae): array
    {
        $rank = [];
        $cuentas = Cuentacaja::query()
            ->with('usocuentacajas')
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($query) => $query->where('usocuentacaja.nombre', self::USO_MAQUINAS))
            ->orderBy('id')
            ->get();

        foreach ($cuentas as $cuenta) {
            $orden = self::ordenDeCuentaEnUso($cuenta, self::USO_MAQUINAS);
            if ($orden <= 0) {
                $orden = self::ordenCanonicoFamilia(self::USO_MAQUINAS, self::familiaMaquina($cuenta));
            }
            $etiqueta = $cuenta->etiquetaOperaciones();
            if ($etiqueta !== '') {
                $rank[$etiqueta] = $orden;
            }
            $nombre = trim((string) $cuenta->nombre);
            if ($nombre !== '') {
                $rank[$nombre] = $orden;
            }
        }

        foreach ($valormae as $codigo => $meta) {
            $desc = trim((string) ($meta['desc'] ?? ''));
            if ($desc === '' || isset($rank[$desc])) {
                continue;
            }
            $cuenta = self::cuentaPorEtiqueta($empresaId, $desc);
            if ($cuenta !== null) {
                $cuenta->loadMissing('usocuentacajas');
                $orden = self::ordenDeCuentaEnUso($cuenta, self::USO_MAQUINAS);
                $rank[$desc] = $orden > 0
                    ? $orden
                    : self::ordenCanonicoFamilia(self::USO_MAQUINAS, self::familiaMaquina($cuenta));
            }
        }

        return $rank;
    }

    private static function ordenDeCuentaEnUso(Cuentacaja $cuenta, string $uso): int
    {
        $cuenta->loadMissing('usocuentacajas');
        $pivot = (int) ($cuenta->usocuentacajas->firstWhere('nombre', $uso)?->pivot?->orden ?? 0);
        if ($pivot > 0) {
            return $pivot;
        }

        return (int) ($cuenta->orden ?? 0);
    }

    private static function idUso(string $uso): int
    {
        static $ids = null;
        if ($ids === null) {
            $ids = Usocuentacaja::query()->pluck('id', 'nombre');
        }

        return (int) ($ids[$uso] ?? 0);
    }

    private static function normalizarTexto(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', '.'], ['A', 'E', 'I', 'O', 'U', ''], $texto);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }
}
