"""Static, side-effect-free validation for the local Compose contract."""

from __future__ import annotations

import pathlib
import sys


def fail(message: str) -> None:
    raise SystemExit(message)


def main() -> None:
    try:
        import yaml
    except ImportError:
        print("PyYAML no disponible; se omite el analisis estructural local.")
        return

    path = pathlib.Path(sys.argv[1])
    document = yaml.safe_load(path.read_text(encoding="utf-8"))
    services = document.get("services", {})
    required = {"postgres", "redis", "api", "api-proxy", "patient-web", "staff-web"}
    missing = sorted(required.difference(services))

    if missing:
        fail(f"Servicios obligatorios ausentes: {', '.join(missing)}")

    for name, service in services.items():
        if "no-new-privileges:true" not in service.get("security_opt", []):
            fail(f"{name}: falta no-new-privileges")

        if "ALL" not in service.get("cap_drop", []):
            fail(f"{name}: falta cap_drop ALL")

        if service.get("read_only") is not True:
            fail(f"{name}: el filesystem raiz debe ser de solo lectura")

        for port in service.get("ports", []):
            if isinstance(port, str) and not port.startswith("127.0.0.1:"):
                fail(f"{name}: el puerto publicado no esta limitado a loopback: {port}")

    for name in ("postgres", "redis"):
        image = services[name].get("image", "")
        if "@sha256:" not in image:
            fail(f"{name}: la imagen debe estar fijada por digest")

    for name in ("postgres", "redis", "api"):
        if services[name].get("ports"):
            fail(f"{name}: no debe publicar puertos al host")

    networks = document.get("networks", {})
    if not networks.get("data", {}).get("internal", False):
        fail("La red de datos debe ser interna")

    if not networks.get("application", {}).get("internal", False):
        fail("La red de aplicacion debe ser interna")

    secrets = document.get("secrets", {})
    for name in ("app_key", "audit_integrity_key", "postgres_password", "redis_password"):
        source = secrets.get(name, {}).get("environment")
        if not source:
            fail(f"{name}: debe proceder de una variable de entorno en tiempo de ejecucion")

    print("Compose YAML y politicas estructurales: OK")


if __name__ == "__main__":
    main()
