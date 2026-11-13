r"""Importa o catalogo de marcas/modelos do JSON Qwen no banco local.

Uso:
  C:\xampp\php84\php.exe? (nao) - este arquivo e Python:
  py gen.py --json C:\Users\sores\Downloads\Qwen_json_20261112_utctdkjnr.json --sql catalog.sql

O script somente gera SQL idempotente; a aplicacao e feita pelo mysql.exe
explicitamente, evitando apagar o catalogo existente.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path


def quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", type=Path)
    parser.add_argument("--flat", type=Path)
    parser.add_argument("--nested", type=Path)
    parser.add_argument("--sql", required=True, type=Path)
    args = parser.parse_args()

    if not args.json and not args.flat and not args.nested:
        parser.error("informe --json, --flat ou --nested")

    def repair(value: str) -> str:
        # Corrige arquivos gerados com texto UTF-8 decodificado como Latin-1.
        try:
            return value.encode("latin1").decode("utf-8")
        except (UnicodeEncodeError, UnicodeDecodeError):
            return value

    entries: dict[str, set[str]] = {}
    source = args.json or args.flat or args.nested
    if args.flat:
        rows = json.loads(args.flat.read_text(encoding="utf-8"))
        for row in rows:
            brand = repair(str(row["fabricante"]).strip())
            # Alias historico usado no banco atual.
            if brand.casefold() == "caoa chery":
                brand = "Caoa Chery"
            entries.setdefault(brand, set()).add(repair(str(row["modelo"]).strip()))
    else:
        data = json.loads(source.read_text(encoding="utf-8"))
        if args.nested:
            for item in data["fabricantes"]:
                brand = repair(str(item["nome"]).strip())
                if brand.casefold() == "caoa chery":
                    brand = "Caoa Chery"
                for models in item.get("modelos", {}).values():
                    entries.setdefault(brand, set()).update(repair(str(model).strip()) for model in models)
        else:
            categories = data["mercado_automotivo_brasil"]["categorias"]
            for category in categories.values():
                for item in category:
                    brand = repair(str(item["fabricante"]).strip())
                    entries.setdefault(brand, set()).update(
                        repair(str(model).strip()) for model in item["modelos"] if str(model).strip()
                    )

    lines = [
        "-- Gerado por gen.py; fonte: " + str(source),
        "SET NAMES utf8mb4;",
        "START TRANSACTION;",
    ]
    for brand in sorted(entries, key=str.casefold):
        lines.append(
            "INSERT INTO vehicle_brands (name, active) VALUES "
            f"({quote(brand)}, 1) ON DUPLICATE KEY UPDATE active=1;"
        )
        for model in sorted(entries[brand], key=str.casefold):
            lines.append(
                "INSERT INTO vehicle_models (brand_id, name, active) "
                f"SELECT id, {quote(model)}, 1 FROM vehicle_brands WHERE name={quote(brand)} "
                "ON DUPLICATE KEY UPDATE active=1;"
            )
    lines += ["COMMIT;", ""]
    args.sql.write_text("\n".join(lines), encoding="utf-8")
    print(f"fabricantes={len(entries)} modelos={sum(len(v) for v in entries.values())}")


if __name__ == "__main__":
    main()
