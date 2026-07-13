<?php

namespace App\Models\Stock;

use App\ApiAnita;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Database\Eloquent\Model;

/**
 * Maestro Anita ubicacion (sistema ventas) — INTERFORMING.
 */
class Ubicacion extends Model
{
    public const ESTADO_ACTIVA = ' ';

    public const ESTADO_INACTIVA = 'I';

    protected $table = 'ubicacion';

    protected $tableAnita = 'ubicacion';

    protected $keyFieldAnita = 'ubi_ubicacion';

    protected $fillable = [
        'codigo', 'nombre', 'zona', 'area', 'nivel', 'estado',
    ];

    /**
     * @return array<int, array{id: string, valor: string, nombre: string}>
     */
    public static function enumEstado(): array
    {
        return [
            ['id' => '1', 'valor' => self::ESTADO_ACTIVA, 'nombre' => 'Activa'],
            ['id' => '2', 'valor' => self::ESTADO_INACTIVA, 'nombre' => 'Inactiva'],
        ];
    }

    public function etiquetaEstado(): string
    {
        $estado = (string) ($this->estado ?? self::ESTADO_ACTIVA);
        foreach (self::enumEstado() as $row) {
            if ($row['valor'] === $estado) {
                return $row['nombre'];
            }
        }

        return $estado === '' ? 'Activa' : $estado;
    }

    public function estaActiva(): bool
    {
        $estado = (string) ($this->estado ?? self::ESTADO_ACTIVA);

        return $estado !== self::ESTADO_INACTIVA;
    }

    /**
     * Importa / actualiza desde Anita (tabla ubicacion, sistema ventas).
     *
     * @return array{en_anita: int, importados: int, actualizados: int, errores: list<string>}
     */
    public function sincronizarConAnita(): array
    {
        InterformingSifabSupport::abortSiNoInterforming();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $payload = array_merge($this->parametrosBridge(), [
            'acc' => 'list',
            'tabla' => $this->tableAnita,
            'campos' => 'ubi_ubicacion, ubi_desc, ubi_zona, ubi_area, ubi_nivel, ubi_estado',
            'orderBy' => 'ubi_ubicacion',
        ]);

        $rows = json_decode($apiAnita->apiCall($payload));
        if (! is_array($rows)) {
            $ret['errores'][] = 'Respuesta Anita inválida al listar ubicacion';

            return $ret;
        }

        $ret['en_anita'] = count($rows);

        foreach ($rows as $row) {
            try {
                $codigo = trim((string) ($row->ubi_ubicacion ?? ''));
                if ($codigo === '') {
                    continue;
                }

                $nombre = trim((string) ($row->ubi_desc ?? ''));
                if ($nombre === '') {
                    $nombre = $codigo;
                }

                $attrs = [
                    'nombre' => $nombre,
                    'zona' => $this->nullableTrim($row->ubi_zona ?? null),
                    'area' => $this->nullableTrim($row->ubi_area ?? null),
                    'nivel' => $this->nullableTrim($row->ubi_nivel ?? null),
                    'estado' => $this->normalizarEstadoAnita($row->ubi_estado ?? null),
                ];

                $local = self::query()->where('codigo', $codigo)->first();
                if ($local) {
                    // No pisar descripción local con vacío: Anita ya normalizó a código arriba.
                    $local->fill($attrs);
                    if (trim((string) $local->nombre) === '') {
                        $local->nombre = $codigo;
                    }
                    if ($local->isDirty()) {
                        $local->save();
                        $ret['actualizados']++;
                    }
                } else {
                    self::create(array_merge(['codigo' => $codigo], $attrs));
                    $ret['importados']++;
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = trim((string) ($row->ubi_ubicacion ?? '?')).': '.$e->getMessage();
            }
        }

        return $ret;
    }

    public function guardarAnita(): void
    {
        if (! InterformingSifabSupport::esInterforming()) {
            return;
        }

        $apiAnita = new ApiAnita();
        $payload = array_merge($this->parametrosBridge(), [
            'tabla' => $this->tableAnita,
            'acc' => 'insert',
            'campos' => 'ubi_ubicacion, ubi_desc, ubi_zona, ubi_area, ubi_nivel, ubi_estado',
            'valores' => sprintf(
                " '%s', '%s', '%s', '%s', '%s', '%s' ",
                $this->esc($this->codigo),
                $this->esc($this->nombre),
                $this->esc($this->zona ?? ''),
                $this->esc($this->area ?? ''),
                $this->esc($this->nivel ?? ''),
                $this->esc($this->estadoParaAnita())
            ),
        ]);
        $apiAnita->apiCallEscritura($payload);
    }

    public function actualizarAnita(): void
    {
        if (! InterformingSifabSupport::esInterforming()) {
            return;
        }

        $apiAnita = new ApiAnita();
        $payload = array_merge($this->parametrosBridge(), [
            'acc' => 'update',
            'tabla' => $this->tableAnita,
            'valores' => sprintf(
                " ubi_desc = '%s', ubi_zona = '%s', ubi_area = '%s', ubi_nivel = '%s', ubi_estado = '%s' ",
                $this->esc($this->nombre),
                $this->esc($this->zona ?? ''),
                $this->esc($this->area ?? ''),
                $this->esc($this->nivel ?? ''),
                $this->esc($this->estadoParaAnita())
            ),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".$this->esc($this->codigo)."' ",
        ]);
        $apiAnita->apiCallEscritura($payload);
    }

    public function eliminarAnita(): void
    {
        if (! InterformingSifabSupport::esInterforming()) {
            return;
        }

        $apiAnita = new ApiAnita();
        $payload = array_merge($this->parametrosBridge(), [
            'acc' => 'delete',
            'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".$this->esc($this->codigo)."' ",
        ]);
        $apiAnita->apiCallEscritura($payload);
    }

    /**
     * @return array{sistema: string, servidor?: string, path_sistema?: string}
     */
    private function parametrosBridge(): array
    {
        $params = [
            'sistema' => (string) config('stock.anita_stkmov.sistema_ventas', 'ventas'),
        ];
        $servidor = trim((string) config('anita.ip', ''));
        if ($servidor !== '') {
            $params['servidor'] = $servidor;
        }
        $path = rtrim((string) config('anita.bdd_path', ''), '/');
        if ($path !== '') {
            $params['path_sistema'] = $path;
        }

        return $params;
    }

    private function estadoParaAnita(): string
    {
        $estado = (string) ($this->estado ?? self::ESTADO_ACTIVA);

        return $estado === self::ESTADO_INACTIVA ? self::ESTADO_INACTIVA : ' ';
    }

    private function normalizarEstadoAnita($raw): string
    {
        $estado = (string) ($raw ?? ' ');
        if ($estado === self::ESTADO_INACTIVA) {
            return self::ESTADO_INACTIVA;
        }

        return self::ESTADO_ACTIVA;
    }

    private function nullableTrim($raw): ?string
    {
        $v = trim((string) ($raw ?? ''));

        return $v === '' ? null : $v;
    }

    private function esc(?string $value): string
    {
        return str_replace("'", "''", (string) ($value ?? ''));
    }
}
