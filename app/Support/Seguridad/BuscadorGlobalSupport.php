<?php

namespace App\Support\Seguridad;

use App\Support\Ai\AiConsultaOperativaRouterSupport;
use App\Support\Ai\AiConsultaOperativaSupport;
use App\Support\AyudaManuales;

/**
 * Buscador del encabezado: pantallas del rol, manuales, maestros,
 * comprobantes por número y altas que el rol puede crear.
 */
class BuscadorGlobalSupport
{
    public const MIN_CARACTERES = 2;

    public const LIMITE_PANTALLAS = 8;

    public const LIMITE_AYUDA = 4;

    /**
     * El término corto solo dispara el grupo si la consulta es exactamente esa.
     *
     * @var array<string, list<string>>
     */
    private const ALIAS_EXACTOS = [
        'oc' => ['orden de compra', 'ordenes de compra', 'orden compra'],
        'op' => ['orden de pago'],
        'nc' => ['nota de credito', 'nota credito'],
        'nd' => ['nota de debito'],
    ];

    /**
     * @var list<list<string>>
     */
    private const GRUPOS_ALIAS = [
        ['precio', 'precios', 'lista de precio', 'listas de precio', 'tarifario', 'tarifa'],
        ['cliente', 'clientes', 'cuenta corriente'],
        ['proveedor', 'proveedores'],
        ['factura', 'facturas', 'facturacion', 'comprobante'],
        ['stock', 'inventario', 'existencia', 'existencias'],
        ['deposito', 'depositos'],
        ['empleado', 'empleados', 'sueldo', 'sueldos', 'legajo', 'jornal'],
        ['cheque', 'cheques', 'echeq', 'e-cheq'],
        ['pedido', 'pedidos'],
        ['remito', 'remitos'],
        ['articulo', 'articulos', 'producto', 'productos', 'sku'],
        ['caja', 'tesoreria', 'rendicion'],
        ['asiento', 'asientos', 'mayor'],
        ['ticket', 'tickets'],
        ['aprobacion', 'aprobaciones'],
        ['cobranza', 'cobranzas', 'recibo'],
    ];

    /** @var list<string> */
    private const PALABRAS_VACIAS = ['de', 'del', 'la', 'el', 'los', 'las', 'por', 'una', 'uno', 'en', 'al'];

    public function __construct(
        private BarraTareasSupport $barraTareas,
        private BuscadorGlobalRegistrosSupport $registrosSupport,
        private BuscadorGlobalComprobantesSupport $comprobantesSupport,
        private BuscadorGlobalAccionesSupport $accionesSupport,
        private BuscadorEleccionSupport $elecciones
    ) {}

    /**
     * @return array{
     *     pantallas: list<array<string, mixed>>,
     *     ayuda: list<array<string, mixed>>,
     *     registros: list<array<string, mixed>>,
     *     comprobantes: list<array<string, mixed>>,
     *     acciones: list<array<string, mixed>>,
     *     frase: array<string, mixed>|null,
     *     sugerencias: list<array{texto: string, tipo: string}>
     * }
     */
    public function buscar(string $consulta): array
    {
        $q = $this->normalizar($consulta);
        if (mb_strlen($q) < self::MIN_CARACTERES) {
            return [
                'pantallas' => [],
                'ayuda' => [],
                'registros' => [],
                'comprobantes' => [],
                'acciones' => [],
                'frase' => null,
                'sugerencias' => [],
            ];
        }

        $frecuencias = [];
        $hojas = $this->barraTareas->hojasMenuRol();
        foreach ($hojas as $hoja) {
            BuscadorDiccionarioSupport::sumar($frecuencias, (string) ($hoja['nombre'] ?? ''), 8);
            BuscadorDiccionarioSupport::sumar($frecuencias, (string) ($hoja['nombre_ruta'] ?? ''), 2);
        }

        $manuales = AyudaManuales::catalogo();
        foreach ($manuales as $manual) {
            if (empty($manual['disponible'])) {
                continue;
            }
            BuscadorDiccionarioSupport::sumar($frecuencias, (string) ($manual['modulo'] ?? ''), 5);
            BuscadorDiccionarioSupport::sumar($frecuencias, (string) ($manual['bajada'] ?? ''), 1);
        }

        $sugerencias = BuscadorDiccionarioSupport::sugerir($q, $frecuencias);
        $esCorreccion = ($sugerencias[0]['tipo'] ?? '') === 'correccion';
        if (! $esCorreccion) {
            $sugerencias = $this->mezclarSugerencias(
                $sugerencias,
                $this->registrosSupport->sugerir($consulta)
            );
        }
        $alias = $this->terminosAlias($q);
        foreach ($sugerencias as $sugerencia) {
            if (($sugerencia['tipo'] ?? '') === 'correccion') {
                $alias[] = (string) $sugerencia['texto'];
            }
        }

        $anclados = [];
        foreach ($this->barraTareas->ancladosResueltos() as $anclado) {
            $anclados[ltrim((string) ($anclado['url'] ?? ''), '/')] = true;
        }

        $pantallas = [];
        foreach ($hojas as $hoja) {
            $urlMenu = trim((string) ($hoja['url'] ?? ''));
            if ($urlMenu === '' || $urlMenu === '#') {
                continue;
            }

            $titulo = (string) $hoja['nombre'];
            $ruta = (string) $hoja['nombre_ruta'];
            $puntaje = $this->puntaje($q, $alias, $titulo, $ruta);
            if ($puntaje <= 0) {
                continue;
            }

            $claveMenu = ltrim($urlMenu, '/');
            $pantallas[] = [
                'titulo' => $titulo,
                'detalle' => $this->rutaPadre($ruta),
                'url' => url($urlMenu),
                'icono' => (string) $hoja['icono_clases'],
                'puntaje' => $puntaje,
                'tipo' => 'pantalla',
                'clave' => 'pantalla:'.$claveMenu,
                'anclado' => isset($anclados[$claveMenu]),
            ];
        }

        $ayuda = [];
        foreach ($manuales as $manual) {
            if (empty($manual['disponible'])) {
                continue;
            }

            $titulo = (string) ($manual['modulo'] ?? '');
            $bajada = (string) ($manual['bajada'] ?? '');
            $url = (string) ($manual['url'] ?? '');
            if ($titulo === '' || $url === '') {
                continue;
            }

            $puntaje = $this->puntaje($q, $alias, $titulo, $bajada);
            if ($puntaje <= 0) {
                continue;
            }

            $rutaAyuda = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
            $ayuda[] = [
                'titulo' => $titulo,
                'detalle' => $bajada,
                'url' => $url,
                'icono' => clasesIconoMenu((string) ($manual['icono'] ?? '')),
                'nueva_pestana' => true,
                'puntaje' => $puntaje,
                'tipo' => 'ayuda',
                'clave' => 'ayuda:'.ltrim($rutaAyuda, '/'),
            ];
        }

        $refuerzos = $this->elecciones->refuerzos($q);
        $pantallas = $this->mejores($this->reforzar($pantallas, $refuerzos), self::LIMITE_PANTALLAS);
        $comprobantes = $this->subirElegidos($this->comprobantesSupport->buscar($consulta), $refuerzos);
        $registros = $this->subirElegidos($this->registrosSupport->buscar($consulta), $refuerzos);
        $acciones = $this->subirElegidos($this->accionesSupport->buscar($q), $refuerzos);

        return [
            'pantallas' => $pantallas,
            'ayuda' => $this->mejores($this->reforzar($ayuda, $refuerzos), self::LIMITE_AYUDA),
            'comprobantes' => $comprobantes,
            'registros' => $registros,
            'acciones' => $acciones,
            'frase' => $this->fraseConsulta($consulta, $q, $pantallas, $comprobantes, $registros, $acciones),
            'sugerencias' => $sugerencias,
        ];
    }

    /**
     * Pregunta reconocida por reglas (aunque haya pantallas) o, si no hubo
     * ningún resultado, la frase libre. No llama al modelo en cada tecla.
     *
     * @param  list<array<string, mixed>>  $pantallas
     * @param  list<array<string, mixed>>  $comprobantes
     * @param  list<array<string, mixed>>  $registros
     * @param  list<array<string, mixed>>  $acciones
     * @return array<string, mixed>|null
     */
    private function fraseConsulta(
        string $consulta,
        string $q,
        array $pantallas,
        array $comprobantes,
        array $registros,
        array $acciones
    ): ?array {
        if (! $this->consultaIaDisponible()) {
            return null;
        }

        $reconocida = $this->preguntaReconocida($consulta, $q);
        if ($reconocida !== null) {
            return $reconocida;
        }

        if ($pantallas !== [] || $comprobantes !== [] || $registros !== [] || $acciones !== []) {
            return null;
        }
        if (mb_strlen($q) < 8 || ! str_contains($q, ' ')) {
            return null;
        }

        $texto = trim($consulta);

        return [
            'titulo' => 'Consultar: '.$texto,
            'detalle' => 'Abre la consulta operativa',
            'icono' => 'fa fa-comments',
            'tipo' => 'frase',
            'clave' => 'frase:consulta',
            'accion' => 'consulta_ia',
            'frase' => $texto,
            'enviar' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function preguntaReconocida(string $consulta, string $q): ?array
    {
        $texto = trim($consulta);
        if ($texto === '' || preg_match('/^(nueva|nuevo|nuevas|nuevos|crear|alta|altas)\b/u', $q) === 1) {
            return null;
        }

        $ruta = AiConsultaOperativaRouterSupport::interpretarPorReglas($texto);
        if (! ($ruta['ok'] ?? false)) {
            return null;
        }

        $intent = (string) ($ruta['intent'] ?? '');
        if ($intent === '' || ! AiConsultaOperativaSupport::usuarioPuedeIntent($intent)) {
            return null;
        }

        $lectura = (string) ($ruta['interpretacion'] ?? $intent);

        return [
            'titulo' => 'Preguntar: '.$lectura,
            'detalle' => 'Consulta de solo lectura',
            'icono' => 'fa fa-comments',
            'tipo' => 'frase',
            'clave' => 'frase:'.$intent,
            'accion' => 'consulta_ia',
            'frase' => $texto,
            'enviar' => true,
        ];
    }

    private function consultaIaDisponible(): bool
    {
        return can('ejecutar-consulta-ia', false)
            && filter_var(config('ai.habilitado', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('ai.skills.consultar_contexto_operativo.habilitada', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function registrarEleccion(string $consulta, string $tipo, string $clave): void
    {
        $this->elecciones->registrar($consulta, $tipo, $clave);
    }

    /**
     * Lo que este rol ya abrió para el mismo texto sube. Un anclado que coincide, también.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, int>  $refuerzos
     * @return list<array<string, mixed>>
     */
    private function reforzar(array $filas, array $refuerzos): array
    {
        foreach ($filas as $i => $fila) {
            $extra = 0;
            $veces = (int) ($refuerzos[(string) ($fila['clave'] ?? '')] ?? 0);
            if ($veces > 0) {
                $extra += min(40, 12 + $veces * 6);
            }
            if (! empty($fila['anclado'])) {
                $extra += 28;
            }
            $filas[$i]['puntaje'] = (int) ($fila['puntaje'] ?? 0) + $extra;
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, int>  $refuerzos
     * @return list<array<string, mixed>>
     */
    private function subirElegidos(array $filas, array $refuerzos): array
    {
        $hay = false;
        foreach ($filas as $indice => $fila) {
            $filas[$indice]['_orden'] = $indice;
            if ((int) ($refuerzos[(string) ($fila['clave'] ?? '')] ?? 0) > 0) {
                $hay = true;
            }
        }
        if (! $hay) {
            return array_map(function (array $fila): array {
                unset($fila['_orden']);

                return $fila;
            }, $filas);
        }

        usort($filas, function (array $a, array $b) use ($refuerzos): int {
            $va = (int) ($refuerzos[(string) ($a['clave'] ?? '')] ?? 0);
            $vb = (int) ($refuerzos[(string) ($b['clave'] ?? '')] ?? 0);
            if ($va !== $vb) {
                return $vb <=> $va;
            }

            return ($a['_orden'] ?? 0) <=> ($b['_orden'] ?? 0);
        });

        return array_map(function (array $fila): array {
            unset($fila['_orden']);

            return $fila;
        }, $filas);
    }

    /**
     * El menú ocupa hasta tres lugares. Si hay un maestro que empieza igual, entran dos.
     *
     * @param  list<array{texto: string, tipo: string}>  $diccionario
     * @param  list<array{texto: string, tipo: string}>  $maestros
     * @return list<array{texto: string, tipo: string}>
     */
    private function mezclarSugerencias(array $diccionario, array $maestros): array
    {
        $cupoMaestros = min(2, count($maestros));
        $diccionario = array_slice($diccionario, 0, BuscadorDiccionarioSupport::LIMITE_SUGERENCIAS - $cupoMaestros);
        $usados = [];
        $salida = [];
        foreach (array_merge($diccionario, $maestros) as $fila) {
            $clave = mb_strtolower((string) ($fila['texto'] ?? ''));
            if ($clave === '' || isset($usados[$clave])) {
                continue;
            }
            $usados[$clave] = true;
            $salida[] = $fila;
            if (count($salida) >= BuscadorDiccionarioSupport::LIMITE_SUGERENCIAS) {
                break;
            }
        }

        return $salida;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function mejores(array $filas, int $limite): array
    {
        usort($filas, function (array $a, array $b): int {
            $porPuntaje = ($b['puntaje'] <=> $a['puntaje']);
            if ($porPuntaje !== 0) {
                return $porPuntaje;
            }

            return strcasecmp((string) $a['titulo'], (string) $b['titulo']);
        });

        $filas = array_slice($filas, 0, $limite);

        return array_map(function (array $fila): array {
            unset($fila['puntaje']);

            return $fila;
        }, $filas);
    }

    /**
     * @param  list<string>  $alias
     */
    private function puntaje(string $q, array $alias, string $titulo, string $contexto): int
    {
        $tituloN = $this->normalizar($titulo);
        $contextoN = $this->normalizar($contexto);
        $hay = trim($tituloN.' '.$contextoN);
        if ($hay === '') {
            return 0;
        }

        $puntaje = 0;
        if ($tituloN === $q || $this->mismaRaiz($tituloN, $q)) {
            $puntaje = 96;
        } elseif (mb_strlen($q) >= 3 && str_starts_with($tituloN, $q)) {
            $puntaje = 84;
        } elseif ($this->empiezaPorRaiz($tituloN, $q)) {
            $puntaje = 80;
        } elseif ($this->aparece($tituloN, $q)) {
            $puntaje = 72;
        } elseif ($this->aparece($contextoN, $q)) {
            $puntaje = 52;
        } elseif ($this->palabrasPresentes($q, $hay)) {
            $puntaje = 58;
        }

        foreach ($alias as $termino) {
            if ($termino === '' || $termino === $q) {
                continue;
            }
            if ($tituloN === $termino) {
                $puntaje = max($puntaje, 90);
            } elseif (mb_strlen($termino) >= 4 && str_starts_with($tituloN, $termino)) {
                $puntaje = max($puntaje, 74);
            } elseif ($this->aparece($tituloN, $termino)) {
                $puntaje = max($puntaje, 68);
            } elseif ($this->aparece($contextoN, $termino)) {
                $puntaje = max($puntaje, 46);
            }
        }

        if ($puntaje < 50) {
            $puntaje = max($puntaje, BuscadorParecidoSupport::puntaje($q, $tituloN, $contextoN));
        }

        return $puntaje;
    }

    private function mismaRaiz(string $titulo, string $q): bool
    {
        if (str_contains($titulo, ' ') || str_contains($q, ' ')) {
            return false;
        }

        $raizTitulo = $this->raiz($titulo);
        $raizConsulta = $this->raiz($q);

        return $raizTitulo !== ''
            && $raizTitulo === $raizConsulta
            && ($titulo !== $q);
    }

    private function empiezaPorRaiz(string $titulo, string $q): bool
    {
        $raiz = $this->raiz($q);
        if ($raiz === $q || mb_strlen($raiz) < 4) {
            return false;
        }

        return str_starts_with($titulo, $raiz);
    }

    private function raiz(string $token): string
    {
        if (mb_strlen($token) > 4 && str_ends_with($token, 's')) {
            return mb_substr($token, 0, -1);
        }

        return $token;
    }

    private function palabrasPresentes(string $q, string $hay): bool
    {
        $tokens = [];
        foreach (preg_split('/\s+/', $q) ?: [] as $token) {
            if (mb_strlen($token) < 3 || in_array($token, self::PALABRAS_VACIAS, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        if (count($tokens) < 2) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! str_contains($hay, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Consultas cortas (oc, op, nc) solo valen como palabra entera.
     * A partir de 4 letras se acepta el texto adentro de otra palabra (factura → facturación).
     */
    private function aparece(string $hay, string $termino): bool
    {
        if ($termino === '' || $hay === '') {
            return false;
        }

        if (mb_strlen($termino) >= 4) {
            return str_contains($hay, $termino);
        }

        return preg_match('/(?:^|\s)'.preg_quote($termino, '/').'(?:$|\s)/u', $hay) === 1;
    }

    /**
     * @return list<string>
     */
    private function terminosAlias(string $q): array
    {
        $extra = [];

        if (isset(self::ALIAS_EXACTOS[$q])) {
            foreach (self::ALIAS_EXACTOS[$q] as $termino) {
                $extra[] = $this->normalizar($termino);
            }
        }

        foreach (self::GRUPOS_ALIAS as $grupo) {
            $normalizados = [];
            $dispara = false;
            foreach ($grupo as $termino) {
                $normalizado = $this->normalizar($termino);
                $normalizados[] = $normalizado;
                if ($normalizado === $q) {
                    $dispara = true;
                } elseif (
                    mb_strlen($q) >= 4
                    && mb_strlen($normalizado) >= 4
                    && (str_contains($q, $normalizado) || str_contains($normalizado, $q))
                ) {
                    $dispara = true;
                }
            }

            if (! $dispara) {
                continue;
            }

            foreach ($normalizados as $normalizado) {
                if ($normalizado !== $q) {
                    $extra[] = $normalizado;
                }
            }
        }

        return array_values(array_unique($extra));
    }

    private function rutaPadre(string $ruta): string
    {
        $partes = explode(' › ', $ruta);
        if (count($partes) <= 1) {
            return '';
        }

        array_pop($partes);

        return implode(' › ', $partes);
    }

    private function normalizar(string $texto): string
    {
        return BuscadorDiccionarioSupport::normalizar($texto);
    }
}
