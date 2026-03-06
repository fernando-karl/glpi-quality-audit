#!/bin/bash
# Script para fazer push e criar release v1.2.0 no GitHub
# Uso: ./push-and-release.sh

set -e

echo "🚀 GLPi Quality Audit - Push & Release v1.2.0"
echo ""

# Verificar se estamos no diretório correto
if [ ! -f "setup.php" ]; then
    echo "❌ Erro: Execute este script dentro do diretório glpi-quality-audit"
    exit 1
fi

# Verificar se há commits para fazer push
if ! git diff-index --quiet HEAD --; then
    echo "⚠️  Há mudanças não commitadas. Commitando..."
    git add -A
    git commit -m "chore: Auto-commit before release"
fi

echo "📊 Status do repositório:"
git log --oneline -3
echo ""

echo "🏷️  Tags existentes:"
git tag -l
echo ""

# 1. Fazer push dos commits
echo "📤 Passo 1: Fazendo push dos commits para origin/main..."
git push origin main

if [ $? -eq 0 ]; then
    echo "✅ Push dos commits concluído com sucesso!"
else
    echo "❌ Erro ao fazer push dos commits"
    echo "   Possível causa: Token sem permissão de escrita"
    echo ""
    echo "Solução manual:"
    echo "1. Vá para: https://github.com/settings/tokens"
    echo "2. Crie token com escopo 'repo'"
    echo "3. Execute: git remote set-url origin https://TOKEN@github.com/rehoboam-karl/glpi-quality-audit.git"
    echo "4. Execute novamente este script"
    exit 1
fi

echo ""

# 2. Fazer push das tags
echo "📤 Passo 2: Fazendo push da tag v1.2.0..."
git push origin v1.2.0

if [ $? -eq 0 ]; then
    echo "✅ Push da tag v1.2.0 concluído com sucesso!"
else
    echo "❌ Erro ao fazer push da tag"
    exit 1
fi

echo ""

# 3. Criar release no GitHub via gh CLI (se disponível)
if command -v gh &> /dev/null; then
    echo "📦 Passo 3: Criando release no GitHub..."
    
    gh release create v1.2.0 \
        --title "v1.2.0 - Real-time Solution Validation 🔥" \
        --notes-file RELEASE_NOTES_v1.2.0.md \
        --latest
    
    if [ $? -eq 0 ]; then
        echo "✅ Release v1.2.0 criada com sucesso!"
        echo ""
        echo "🎉 Release disponível em:"
        echo "   https://github.com/rehoboam-karl/glpi-quality-audit/releases/tag/v1.2.0"
    else
        echo "⚠️  Erro ao criar release via gh CLI"
        echo "   Crie manualmente em: https://github.com/rehoboam-karl/glpi-quality-audit/releases/new"
    fi
else
    echo "⚠️  GitHub CLI (gh) não instalado"
    echo ""
    echo "📦 Passo 3: Criar release manualmente"
    echo "   1. Acesse: https://github.com/rehoboam-karl/glpi-quality-audit/releases/new"
    echo "   2. Tag: v1.2.0"
    echo "   3. Title: v1.2.0 - Real-time Solution Validation 🔥"
    echo "   4. Description: Copie de RELEASE_NOTES_v1.2.0.md"
    echo "   5. Marque: 'Set as the latest release'"
    echo "   6. Clique 'Publish release'"
fi

echo ""
echo "✅ Processo concluído!"
echo ""
echo "📋 Próximos passos:"
echo "   1. Verificar release: https://github.com/rehoboam-karl/glpi-quality-audit/releases"
echo "   2. Publicar no ClawHub (se aplicável)"
echo "   3. Anunciar no GLPi Plugins Directory"
echo ""
