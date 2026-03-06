#!/bin/bash
# Script para publicar glpi-quality-audit no GitHub
# Uso: ./publish-to-github.sh

set -e

REPO_NAME="glpi-quality-audit"
GITHUB_USER="rehoboam-karl"
REPO_URL="https://github.com/${GITHUB_USER}/${REPO_NAME}.git"

echo "🚀 Publishing ${REPO_NAME} to GitHub..."
echo ""

# Verificar se estamos no diretório correto
if [ ! -f "setup.php" ]; then
    echo "❌ Erro: Execute este script dentro do diretório /tmp/glpi-quality-audit"
    exit 1
fi

# Verificar se já existe remote
if git remote | grep -q origin; then
    echo "⚠️  Remote 'origin' já existe. Removendo..."
    git remote remove origin
fi

echo "📝 Passo 1: Criar repositório no GitHub"
echo "   Acesse: https://github.com/new"
echo "   Nome: ${REPO_NAME}"
echo "   Descrição: 🤖 GLPi Plugin - AI-powered quality audit for ticket solutions"
echo "   Visibilidade: Public"
echo "   NÃO marque README, .gitignore ou license"
echo ""
read -p "✅ Repositório criado no GitHub? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Cancelado. Crie o repositório e execute novamente."
    exit 1
fi

echo ""
echo "📡 Passo 2: Adicionando remote origin..."
git remote add origin "$REPO_URL"
echo "✅ Remote adicionado: $REPO_URL"

echo ""
echo "📤 Passo 3: Fazendo push para GitHub..."
git push -u origin main

echo ""
echo "🎉 Sucesso! Repositório publicado em:"
echo "   https://github.com/${GITHUB_USER}/${REPO_NAME}"
echo ""
echo "📋 Próximos passos recomendados:"
echo "   1. Adicionar topics no GitHub (glpi, ai, openai, claude)"
echo "   2. Criar release v1.0.0"
echo "   3. Publicar no GLPi Plugins Directory"
echo ""
echo "✅ Concluído!"
