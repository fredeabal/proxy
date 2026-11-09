#!/usr/bin/env bash
# Redirige la ejecución al script principal de actualización ubicado en la raíz del proyecto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -f "${ROOT_DIR}/update.sh" ]; then
    exec bash "${ROOT_DIR}/update.sh" "$@"
else
    echo "Error: No se encontró update.sh en ${ROOT_DIR}" >&2
    exit 1
fi

