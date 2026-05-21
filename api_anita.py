"""
Cliente Python del bridge Anita (equivalente a App\\ApiAnita de anitaERP).

Configuración por variables de entorno (mismas que config/anita.php):
  ANITA_IP, LOCAL_IP, ANITA_BRIDGE_TYPE, ANITA_API_SCRIPT,
  ANITA_BDD, ANITA_BDD_PATH, IFX_SERVER, IFX_SERVER_LOCAL,
  ANITA_PUERTO_SSH

Uso:
    from api_anita import ApiAnita

    api = ApiAnita()
    resp = api.api_call({
        "acc": "list",
        "tabla": "clientes",
        "campos": "id, nombre",
        "whereArmado": "WHERE id = 1",
    })
"""

from __future__ import annotations

import json
import os
import random
import re
import subprocess
from dataclasses import dataclass, field
from datetime import datetime
from typing import Any, Mapping, MutableMapping, Optional
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

try:
    from dotenv import load_dotenv

    load_dotenv()
except ImportError:
    pass


def _env(key: str, default: str = "") -> str:
    return (os.environ.get(key) or default).strip()


@dataclass
class AnitaConfig:
    """Valores equivalentes a config/anita.php."""

    ip: str = field(default_factory=lambda: _env("ANITA_IP"))
    local_ip: str = field(default_factory=lambda: _env("LOCAL_IP"))
    bridge_type: str = field(
        default_factory=lambda: _env("ANITA_BRIDGE_TYPE", "HTTP") or "HTTP"
    )
    api_script: str = field(
        default_factory=lambda: _env("ANITA_API_SCRIPT", "apiERP.php") or "apiERP.php"
    )
    bdd: str = field(default_factory=lambda: _env("ANITA_BDD", "ventas") or "ventas")
    bdd_path: str = field(default_factory=lambda: _env("ANITA_BDD_PATH"))
    ifx_server: str = field(default_factory=lambda: _env("IFX_SERVER"))
    ifx_server_local: str = field(default_factory=lambda: _env("IFX_SERVER_LOCAL"))
    puerto_ssh: Optional[str] = field(default_factory=lambda: _env("ANITA_PUERTO_SSH") or None)
    servidores: dict[str, str] = field(default_factory=dict)
    ifx_servers: dict[str, str] = field(default_factory=dict)
    logs_dir: str = field(default_factory=lambda: os.path.join(os.getcwd(), "logs"))
    ssh_user: str = "sergio"

    def __post_init__(self) -> None:
        if not self.servidores:
            self.servidores = {
                "ANITA_IP": self.ip,
                "LOCAL_IP": self.local_ip,
            }
        if not self.ifx_servers:
            self.ifx_servers = {
                "IFX_SERVER": self.ifx_server,
                "IFX_SERVER_LOCAL": self.ifx_server_local,
            }


class ApiAnita:
    """Bridge HTTP (por defecto) o legacy SSH+SCP hacia Informix vía apiERP.php."""

    _HOST_PATTERN = re.compile(r"^[\w.\-]+(:\d+)?$", re.ASCII)

    def __init__(
        self,
        config: Optional[AnitaConfig] = None,
        *,
        servidor: Optional[str] = None,
    ) -> None:
        self._config = config or AnitaConfig()
        micro = datetime.now().strftime("%Y%m%d%H%M%S%f")[:-3]
        self.fecha = f"{micro}_{random.randint(0, 9999)}"
        self.servidor_anita = servidor if servidor is not None else self._config.ip

    @staticmethod
    def resolver_host(
        clave_servidor: Optional[str] = None,
        config: Optional[AnitaConfig] = None,
    ) -> str:
        cfg = config or AnitaConfig()
        if clave_servidor is not None and clave_servidor.strip():
            clave = clave_servidor.strip().upper()
            if clave in cfg.servidores and cfg.servidores[clave].strip():
                return cfg.servidores[clave].strip()
            if ApiAnita._HOST_PATTERN.match(clave_servidor.strip()):
                return clave_servidor.strip()
        return cfg.ip.strip()

    @staticmethod
    def url_bridge(host: Optional[str] = None, config: Optional[AnitaConfig] = None) -> str:
        cfg = config or AnitaConfig()
        resolved = (host or ApiAnita.resolver_host(config=cfg)).strip()
        if not resolved:
            raise RuntimeError(
                "ANITA_IP no está configurado. Defínalo en .env o en AnitaConfig.ip."
            )

        script = cfg.api_script.lstrip("/")
        if re.match(r"^https?://", resolved, re.IGNORECASE):
            base = resolved.rstrip("/")
            if re.search(r"\.php(\?|$)", base, re.IGNORECASE):
                return base
            return f"{base}/{script}"
        return f"http://{resolved}/{script}"

    @staticmethod
    def _resolver_ifx_server(
        clave_ifx: Optional[str] = None,
        config: Optional[AnitaConfig] = None,
    ) -> str:
        cfg = config or AnitaConfig()
        if clave_ifx is not None and clave_ifx.strip():
            clave = clave_ifx.strip().upper()
            if clave in cfg.ifx_servers and cfg.ifx_servers[clave].strip():
                return cfg.ifx_servers[clave].strip()
        return cfg.ifx_server.strip()

    def api_call_http(self, data: Mapping[str, Any]) -> str:
        payload: dict[str, Any] = dict(data)
        acc = str(payload.get("acc", ""))

        if "servidor" in payload:
            self.servidor_anita = self.resolver_host(
                str(payload["servidor"]), self._config
            )

        if "ifx_server" in payload:
            payload["IFX_SERVER"] = self._resolver_ifx_server(
                str(payload["ifx_server"]), self._config
            )
        else:
            payload["IFX_SERVER"] = self._resolver_ifx_server(config=self._config)

        bdd = self._config.bdd
        bdd_path = self._config.bdd_path.rstrip("/")
        payload["DB_NAME"] = bdd
        payload["IFX_DB_PATH"] = f"{bdd_path}/{bdd}" if bdd_path else bdd
        payload["sistema"] = bdd
        if bdd_path:
            payload["path_sistema"] = bdd_path

        try:
            url = self.url_bridge(self.servidor_anita, self._config)
        except RuntimeError as exc:
            return json.dumps({"Error": f"Bridge HTTP Anita: {exc}"})

        body = json.dumps(payload).encode("utf-8")
        request = Request(
            url,
            data=body,
            method="POST",
            headers={
                "Accept": "application/json",
                "Content-Type": "application/json",
            },
        )

        try:
            with urlopen(request, timeout=120) as response:
                raw = response.read()
        except HTTPError as exc:
            try:
                detail = exc.read().decode("utf-8", errors="replace")
            except OSError:
                detail = str(exc)
            return json.dumps({"Error": f"Bridge HTTP Anita: {detail or exc}"})
        except URLError as exc:
            return json.dumps({"Error": f"Bridge HTTP Anita: {exc.reason}"})

        text = raw.decode("utf-8", errors="replace")
        trim = text.strip()
        if not trim:
            if acc == "list":
                return "[]"
            if acc in ("insert", "update", "delete"):
                return "[]"
            return json.dumps({"Error": "Bridge HTTP Anita: respuesta vacía"})
        return text

    @staticmethod
    def _limpiar_respuesta_bridge_escritura(respuesta: str) -> str:
        lineas = []
        for linea in respuesta.splitlines():
            if re.match(r"^\s*(?:Warning|Notice|Deprecated|Fatal error)\b", linea, re.I):
                continue
            lineas.append(linea)
        return "\n".join(lineas).strip()

    @staticmethod
    def _respuesta_bridge_escritura_exitosa(respuesta: str) -> bool:
        limpia = ApiAnita._limpiar_respuesta_bridge_escritura(respuesta)
        return bool(
            limpia
            and re.search(r"\d+\s+row\(s\)\s+(?:inserted|updated|deleted)\b", limpia, re.I)
        )

    @staticmethod
    def extraer_mensaje_error(respuesta: Optional[str]) -> Optional[str]:
        if respuesta is None:
            return "Sin respuesta del bridge Anita"

        trim = respuesta.strip()
        if not trim:
            return "Respuesta vacía del bridge Anita"
        if trim in ("[]", "{}"):
            return None

        try:
            decoded = json.loads(trim)
        except json.JSONDecodeError:
            limpia = ApiAnita._limpiar_respuesta_bridge_escritura(trim)
            if ApiAnita._respuesta_bridge_escritura_exitosa(trim):
                return None
            if limpia.lower() == "error":
                return "Error en ejecución SQL Informix (revise el archivo .ret en el servidor Anita)"
            lower = trim.lower()
            if "<b>warning</b>" in lower or "<b>fatal error</b>" in lower:
                return re.sub(r"<[^>]+>", "", trim)
            if limpia == "" and re.search(r"\b(?:warning|notice|fatal error)\b", trim, re.I):
                return "Advertencia PHP en bridge Anita (actualice apiERP.php en el servidor)"
            if "error" in limpia.lower():
                return limpia
            return None

        if isinstance(decoded, dict):
            err = decoded.get("Error") or decoded.get("error")
            if err is not None and str(err).strip():
                return str(err)
        return None

    @staticmethod
    def decodificar_lista_filas(respuesta: Optional[str]) -> list[dict[str, Any]]:
        if respuesta is None:
            return []

        trim = respuesta.strip()
        if not trim or trim == "[]":
            return []

        try:
            decoded = json.loads(trim)
        except json.JSONDecodeError:
            return []

        if isinstance(decoded, list):
            return [row for row in decoded if isinstance(row, dict)]
        if isinstance(decoded, dict):
            if decoded.get("Error") or decoded.get("error"):
                return []
            return [decoded]
        return []

    @staticmethod
    def primera_fila_lista(respuesta: Optional[str]) -> Optional[dict[str, Any]]:
        filas = ApiAnita.decodificar_lista_filas(respuesta)
        return filas[0] if filas else None

    def _usa_bridge_http(self) -> bool:
        return self._config.bridge_type.strip().upper() == "HTTP"

    def api_call(self, data: Mapping[str, Any]) -> str:
        if self._usa_bridge_http():
            return self.api_call_http(data)
        return self._api_call_ssh_legacy(dict(data))

    def _api_call_ssh_legacy(self, data: MutableMapping[str, Any]) -> str:
        puerto = self._config.puerto_ssh
        port_ssh = f"-p {puerto}" if puerto else ""
        port_scp = f"-P {puerto}" if puerto else ""
        host = self.servidor_anita
        user = self._config.ssh_user

        sql = self.armar_sql(data)
        nom_arch = f"{self.fecha}.sql"
        logs_dir = self._config.logs_dir
        os.makedirs(logs_dir, exist_ok=True)
        path_arch = os.path.join(logs_dir, nom_arch)

        with open(path_arch, "w", encoding="utf-8") as fh:
            fh.write(sql)

        remote_tmp = f"/home/{user}/tmp/{nom_arch}"
        subprocess.run(
            ["scp", *([port_scp] if port_scp else []), path_arch, f"{user}@{host}:{remote_tmp}"],
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        subprocess.run(
            [
                "ssh",
                *([port_ssh] if port_ssh else []),
                f"{user}@{host}",
                (
                    f"cd /usr2/www/htdocs; ./apiERP.php {self._config.bdd} "
                    f"{remote_tmp} {self.fecha} > /dev/null"
                ),
            ],
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        subprocess.run(
            [
                "ssh",
                *([port_ssh] if port_ssh else []),
                f"{user}@{host}",
                f"rm {remote_tmp} > /dev/null",
            ],
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

        acc = data.get("acc")
        if acc in ("list", "customSql"):
            csv_path = os.path.join(logs_dir, f"{self.fecha}.csv")
            bdd_path = self._config.bdd_path.rstrip("/")
            bdd = self._config.bdd
            remote_csv = f"{bdd_path}/{bdd}/{self.fecha}.csv"
            subprocess.run(
                [
                    "scp",
                    *([port_scp] if port_scp else []),
                    f"{user}@{host}:{remote_csv}",
                    csv_path,
                ],
                check=False,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
            subprocess.run(
                [
                    "ssh",
                    *([port_ssh] if port_ssh else []),
                    f"{user}@{host}",
                    f"cd {bdd_path}/{bdd}; rm {self.fecha}.csv > /dev/null",
                ],
                check=False,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )

            if not os.path.isfile(csv_path):
                sql_file = os.path.join(logs_dir, f"{self.fecha}.sql")
                if os.path.isfile(sql_file):
                    os.remove(sql_file)
                return json.dumps([])

            campos = str(data.get("campos", ""))
            campos_arr = [c.strip() for c in campos.split(",") if c.strip()]
            data_arr: list[dict[str, str]] = []

            with open(csv_path, encoding="utf-8", errors="replace") as archivo:
                for linea in archivo:
                    linea_arr = linea.rstrip("\n").split("|")
                    registro: dict[str, str] = {}
                    for key, value in enumerate(campos_arr):
                        nombre = value
                        for sep in (" as ", " AS "):
                            partes = value.split(sep)
                            if len(partes) == 2:
                                nombre = partes[1].strip()
                                break
                        if key < len(linea_arr):
                            registro[nombre.strip()] = linea_arr[key]
                    data_arr.append(registro)

            for path in (csv_path, path_arch):
                if os.path.isfile(path):
                    os.remove(path)
            return json.dumps(data_arr)

        return json.dumps([])

    def armar_sql(self, data: Mapping[str, Any]) -> str:
        where = str(data.get("where", ""))
        campos = str(data.get("campos", ""))
        tabla = str(data.get("tabla", ""))
        where_armado = str(data.get("whereArmado", ""))
        order_by = (
            f" ORDER BY {data['orderBy']}" if data.get("orderBy") else ""
        )
        group_by = (
            f" GROUP BY {data['groupBy']}" if data.get("groupBy") else ""
        )
        valores = str(data.get("valores", ""))
        acc = data.get("acc")

        if acc == "list":
            sql = (
                f"UNLOAD TO '{self.fecha}.csv' DELIMITER '|' SELECT {campos} "
                f"FROM {tabla} {where_armado} {group_by} {order_by}"
            )
        elif acc == "insert":
            sql = f"INSERT INTO {tabla} ({campos}) VALUES ({valores})"
        elif acc == "update":
            sql = f"UPDATE {tabla} SET {valores} {where_armado}"
        elif acc == "delete":
            sql = f"DELETE FROM {tabla} {where_armado}"
        elif acc == "customSql":
            sql = (
                f"UNLOAD TO '{self.fecha}.csv' DELIMITER '|' "
                f"{data.get('customSql', '')}"
            )
        else:
            sql = ""

        return re.sub(r"\s\s+", " ", sql.strip())

    def obtener_siguiente_numerador(
        self, tabla: str, campo_id: str = "id"
    ) -> int:
        payload = {
            "acc": "list",
            "campos": f"MAX({campo_id}) AS id",
            "tabla": tabla,
        }
        fila = self.primera_fila_lista(self.api_call(payload))
        if fila is not None:
            valor = fila.get("id")
            if valor is not None and str(valor).strip() != "":
                return int(valor) + 1
        return 1


# Alias compatible con nombre PHP
ApiAnita.resolverHost = staticmethod(ApiAnita.resolver_host)  # type: ignore[method-assign]
ApiAnita.urlBridge = staticmethod(ApiAnita.url_bridge)  # type: ignore[method-assign]
ApiAnita.extraerMensajeError = staticmethod(ApiAnita.extraer_mensaje_error)  # type: ignore[method-assign]
ApiAnita.decodificarListaFilas = staticmethod(ApiAnita.decodificar_lista_filas)  # type: ignore[method-assign]
ApiAnita.primeraFilaLista = staticmethod(ApiAnita.primera_fila_lista)  # type: ignore[method-assign]


if __name__ == "__main__":
    import sys

    if len(sys.argv) < 2:
        print("Uso: python api_anita.py <json_payload>")
        print('Ej.: python api_anita.py \'{"acc":"list","tabla":"x","campos":"id"}\'')
        sys.exit(1)

    api = ApiAnita()
    payload = json.loads(sys.argv[1])
    print(api.api_call(payload))
