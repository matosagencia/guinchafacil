#!/usr/bin/env bash
set -euo pipefail

echo "=== INICIANDO DEPLOY GUINCHAFÁCIL ==="

# 1. Invalida o cache de opcode e rotas do LiteSpeed/lsphp
if [ -d "$HOME" ]; then
    touch "$HOME/.lsphp_restart.txt"
    echo "-> .lsphp_restart.txt tocado com sucesso."
fi

# 2. Ajustar permissões corretas de arquivos e pastas
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod +x tools/*.sh 2>/dev/null || true

# 3. Teste de sintaxe PHP em todos os arquivos principais
for file in $(find src views -name "*.php"); do
    php -l "$file" >/dev/null || { echo "ERRO DE SINTAXE em $file"; exit 1; }
done

# 4. §7.2 — OSRM: bloqueia producao sem instancia propria.
#    Detectar ambiente: usa APP_ENV do .env, com fallback para 'production'.
APP_ENV_EFETIVO="$(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
if [ -z "$APP_ENV_EFETIVO" ]; then
    APP_ENV_EFETIVO="production"
fi

if [ "$APP_ENV_EFETIVO" = "local" ] || [ "$APP_ENV_EFETIVO" = "dev" ] || [ "$APP_ENV_EFETIVO" = "development" ]; then
    echo "-> OSRM check: ambiente $APP_ENV_EFETIVO (dev). Somente aviso."
    php tools/check-osrm-deploy.php --dev || true
else
    echo "-> OSRM check: ambiente $APP_ENV_EFETIVO (producao). Bloqueante."
    if ! php tools/check-osrm-deploy.php --production; then
        echo ""
        echo "================================================================"
        echo "  DEPLOY ABORTADO: OSRM nao configurado para producao."
        echo "================================================================"
        echo ""
        echo "  O servidor demo publico (router.project-osrm.org) tem"
        echo "  rate-limit e ToS que proibem uso em producao."
        echo ""
        echo "  Corrija um dos dois:"
        echo "    1. .env: OSRM_BASE_URL=http://<host>:5000"
        echo "    2. configuracoes: chave por_road_match_base_url"
        echo ""
        echo "  Instancia OSRM propria (exemplo):"
        echo "    docker run -d -p 5000:5000 \\"
        echo "      -v /data/osrm:/data osrm/osrm-backend \\"
        echo "      osrm-routed --algorithm mld /data/brazil-latest.osrm"
        echo ""
        exit 1
    fi
fi

echo "=== DEPLOY CONCLUÍDO COM SUCESSO ==="