# 🚀 Setup GitHub Repository

## Repositório Preparado ✅

O plugin **glpi-quality-audit** está pronto para ser publicado no GitHub!

**Localização:** `/tmp/glpi-quality-audit`  
**Commit inicial:** `ac58799` ✅  
**Branch:** `main`

---

## 📋 Opção 1: Criar via Interface Web (RECOMENDADO)

### Passo 1: Criar Repositório no GitHub

1. Acesse: https://github.com/new
2. Preencha:
   - **Repository name:** `glpi-quality-audit`
   - **Description:** `🤖 GLPi Plugin - AI-powered quality audit for ticket solutions (OpenAI/Claude/Gemini)`
   - **Visibility:** ✅ Public
   - **NÃO marque:** README, .gitignore ou license (já existem localmente)
3. Clique em **Create repository**

### Passo 2: Push do Código

```bash
cd /tmp/glpi-quality-audit

# Adicionar remote
git remote add origin https://github.com/rehoboam-karl/glpi-quality-audit.git

# Push
git push -u origin main
```

✅ **Pronto!** Repositório publicado em `https://github.com/rehoboam-karl/glpi-quality-audit`

---

## 📋 Opção 2: Criar via GitHub CLI (se tiver permissões)

```bash
cd /tmp/glpi-quality-audit

# Fazer login com token que tenha permissão 'repo'
gh auth login

# Criar e fazer push
gh repo create glpi-quality-audit \
  --public \
  --source=. \
  --description="🤖 GLPi Plugin - AI-powered quality audit for ticket solutions (OpenAI/Claude/Gemini)" \
  --push
```

---

## 📋 Opção 3: Usar Token com Permissões Corretas

Se o `GITHUB_TOKEN` atual não tem permissão, gere um novo token:

1. Acesse: https://github.com/settings/tokens/new
2. Selecione escopo: **repo** (Full control of private repositories)
3. Gere e copie o token
4. Execute:

```bash
# Fazer login com novo token
echo "SEU_TOKEN_AQUI" | gh auth login --with-token

# Criar repositório
cd /tmp/glpi-quality-audit
gh repo create glpi-quality-audit --public --source=. --push
```

---

## 📦 Conteúdo do Repositório

```
glpi-quality-audit/
├── .gitignore           ✅ Configurado
├── LICENSE              ✅ MIT License
├── README.md            ✅ Documentação completa
├── INSTALL.md           ✅ Guia de instalação
├── setup.php            ✅ Configuração do plugin
├── hook.php             ✅ Hooks GLPi
├── front/               ✅ Páginas frontend
│   ├── config.form.php
│   └── dashboard.php
├── inc/                 ✅ Classes PHP
│   ├── audit.class.php
│   ├── config.class.php
│   └── menu.class.php
└── locales/             ✅ Traduções
    └── pt_BR/
        └── qualityaudit.po
```

**Total:** 12 arquivos, ~1600 linhas de código

---

## 🎯 Próximos Passos Após Publicar

### 1. Adicionar Topics no GitHub

Após criar o repositório, adicione topics relevantes:

```
glpi, glpi-plugin, ai, openai, claude, gemini, quality-audit, ticket-management, php
```

### 2. Criar Release v1.0.0

```bash
cd /tmp/glpi-quality-audit

# Criar tag
git tag -a v1.0.0 -m "Release v1.0.0 - Initial public release

Features:
- AI-powered solution quality analysis
- Support for OpenAI, Claude, and Gemini
- Automatic auditing on ticket closure
- Dashboard with metrics and rankings
- Configurable approval threshold
- Multi-language support (English, Portuguese)
"

# Push tag
git push origin v1.0.0

# Criar release via GitHub CLI
gh release create v1.0.0 \
  --title "Quality Audit v1.0.0 - Initial Release" \
  --notes "See CHANGELOG for details"
```

### 3. Publicar no GLPi Plugins Directory

1. Acesse: https://plugins.glpi-project.org/
2. Registre conta (se não tiver)
3. Submeta o plugin:
   - **Name:** Quality Audit
   - **Key:** qualityaudit
   - **Repository:** https://github.com/rehoboam-karl/glpi-quality-audit
   - **Category:** Helpdesk & ITSM
   - **Tags:** AI, Quality, Automation

---

## 📝 Comandos Úteis

```bash
# Verificar status do repositório
cd /tmp/glpi-quality-audit
git status
git log --oneline

# Fazer alterações
git add .
git commit -m "fix: correção de bug"
git push

# Ver histórico
git log --graph --oneline --all
```

---

## 🆘 Troubleshooting

### Erro: "Authentication failed"
```bash
# Refazer login
gh auth logout
gh auth login
```

### Erro: "remote origin already exists"
```bash
# Remover remote existente
git remote remove origin

# Adicionar novamente
git remote add origin https://github.com/rehoboam-karl/glpi-quality-audit.git
```

### Erro: "Permission denied (publickey)"
```bash
# Usar HTTPS ao invés de SSH
git remote set-url origin https://github.com/rehoboam-karl/glpi-quality-audit.git
```

---

**Status Atual:** ✅ Repositório local pronto, aguardando criação no GitHub

**Quando criar o repositório, execute:**
```bash
cd /tmp/glpi-quality-audit
git remote add origin https://github.com/rehoboam-karl/glpi-quality-audit.git
git push -u origin main
```
