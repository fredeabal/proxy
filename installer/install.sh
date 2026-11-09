#!/usr/bin/env bash
# Redirige la ejecución al script principal de instalación ubicado en la raíz del proyecto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -f "${ROOT_DIR}/install.sh" ]; then
    exec bash "${ROOT_DIR}/install.sh" "$@"
else
    echo "Error: No se encontró install.sh en ${ROOT_DIR}" >&2
    exit 1
fi

