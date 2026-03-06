# 🤖 Quality Audit Plugin for GLPi

**Plugin de Auditoria de Qualidade para GLPi** - Analisa automaticamente a qualidade das soluções de chamados usando Inteligência Artificial.

## 🎯 Funcionalidades

### 🔒 Validação Preventiva em Tempo Real (v1.2+)
- **Valida ANTES de salvar** - Bloqueia soluções com qualidade abaixo do threshold
- **Feedback imediato** - Score, análise e sugestões na tela
- **Loop de correção** - Analista ajusta e valida novamente até aprovar
- **Sugestão inteligente** - IA reescreve o texto de forma profissional
- **Failsafe automático** - Permite salvar se API indisponível

### 📧 Notificações por Email (v1.3+)
- **Notificação automática** ao técnico quando solução é recusada
- **Template profissional** com score, análise e sugestões
- **Link direto** para o chamado no GLPi
- **Resumo diário** para administradores (opcional)

### 📄 Relatórios PDF (v1.3+)
- **Geração de relatórios** por período, técnico ou status
- **Filtros avançados** (data, entidade, tipo)
- **Estatísticas completas** (total, %, média)
- **Exportação** em PDF ou HTML

### 📋 Lista de Auditorias (v1.3+)
- **Histórico completo** de todas as auditorias
- **Paginação** para grandes volumes
- **Filtros** por status e técnico
- **Visualização** de análise detalhada

### ✅ Análise Automática de Soluções
- **5 critérios objetivos:**
  - 🔤 **Ortografia e Gramática** (20 pts): Texto profissional e sem erros
  - 📝 **Completude** (30 pts): Explica o que foi feito e como
  - ✅ **Resolução Efetiva** (25 pts): Problema realmente foi resolvido?
  - 💬 **Clareza e Tom** (15 pts): Linguagem compreensível e cordial
  - 🔧 **Adequação Técnica** (10 pts): Condiz com o problema relatado
- **Detecção de "sem solução"** - Textos como "encerrado sem solução" recebem nota 0
- **Sugestões contextuais** - IA melhora texto sem inventar procedimentos

### 🏢 Configuração por Entidade
- **Configurações específicas** para cada entidade organizacional
- **Herança automática** de configurações (filhas herdam de pais)
- **Chaves de API isoladas** por entidade (multi-tenant seguro)
- **Thresholds personalizados** por departamento
- **Árvore de herança visual** para administradores

### 📊 Dashboard Completo (v1.3.1+)
- **Cards informativos** com design moderno
- **Distribuição de notas** (gráfico de barras)
- **Indicadores visuais** de performance
- **Estatísticas em tempo real**
- **Botão de gerar relatório** direto no dashboard
- **Top 5 técnicos** com melhor pontuação

### 🎨 Interface Moderna
- **Design responsivo** com Bootstrap GLPi
- **Animações suaves** e transições
- **Ícones Font Awesome**
- **Feedback visual** em tempo real

### 🔌 Integrações com IA
- **OpenAI** (GPT-4, GPT-4o, GPT-4o-mini)
- **Anthropic** (Claude 3.5 Sonnet, Claude 3 Opus)
- **Google** (Gemini 1.5 Pro, Gemini 1.5 Flash)
- **Teste de conexão** integrado na configuração

### 🔒 Segurança (v1.2.1+)
- **CSRF Protection** em todos os formulários
- **Input Sanitization** de todos os dados de entrada
- **API Key criptografada** no banco de dados
- **XSS Prevention** com Html::clean()
- **Path Traversal Protection** em downloads

---

## 📋 Requisitos

- **GLPi:** 10.0.0 - 10.0.99
- **PHP:** 7.4 ou superior
- **Extensões PHP:** curl, json
- **API Key:** OpenAI, Anthropic ou Google Gemini

---

## 🚀 Instalação

### 1. Download do Plugin

```bash
cd /var/www/html/glpi/plugins
git clone https://github.com/fernandokarl/glpi-quality-audit.git qualityaudit
```

Ou baixe o ZIP e extraia em `glpi/plugins/qualityaudit`

### 2. Instalar via Interface GLPi

1. Acesse: **Configurar → Plugins**
2. Encontre **Quality Audit**
3. Clique em **Instalar**
4. Clique em **Ativar**

### 3. Configurar API

1. Acesse: **Ferramentas → Quality Audit → Configuração**
2. Configure:
   - **API Provider:** OpenAI, Claude ou Gemini
   - **API Key:** Sua chave de API
   - **Modelo:** `gpt-4o-mini` (recomendado para custo/benefício)
   - **Auto Audit:** Ativar
   - **Threshold:** 80 (padrão)

3. Clique em **Testar Conexão** para verificar

---

## ⚙️ Configuração

### Opções Disponíveis

| Opção | Descrição | Padrão |
|-------|-----------|--------|
| **API Provider** | Provedor de IA (openai, claude, gemini) | openai |
| **API Key** | Chave de API (criptografada) | - |
| **AI Model** | Modelo específico | gpt-4o-mini |
| **Auto Audit** | Auditar automaticamente ao fechar | Sim |
| **Re-audit on Update** | Re-auditar quando solução for editada | Não |
| **Approval Threshold** | Nota mínima para aprovação | 80 |
| **Ticket Types** | Tipos a auditar (Incident, Change, Problem) | Todos |
| **Notify on Refusal** | Notificar técnico quando recusado | Sim |
| **Entity** | Entidade organizacional | Atual |
| **Recursive** | Aplicar a entidades filhas | Sim |

### Modelos Recomendados

#### OpenAI
- **gpt-4o-mini** (mais barato, ~$0.001/auditoria) ⭐ **RECOMENDADO**
- **gpt-4o** (mais preciso, ~$0.01/auditoria)
- **gpt-4** (legacy, mais caro)

#### Anthropic Claude
- **claude-3-5-sonnet-20241022** (equilibrado) ⭐ **RECOMENDADO**
- **claude-3-opus-20240229** (mais preciso, mais caro)

#### Google Gemini
- **gemini-1.5-flash** (rápido e barato) ⭐ **RECOMENDADO**
- **gemini-1.5-pro** (mais preciso)

---

## 📖 Como Usar

### Fluxo Automático

1. **Técnico resolve chamado** e fecha com solução
2. **Plugin intercepta** o fechamento
3. **IA analisa** a solução em tempo real
4. **Resultado salvo** no banco de dados
5. **Notificação enviada** (se recusado)

### Visualizar Resultados

#### Dashboard
- Acesse: **Ferramentas → Quality Audit → Dashboard**
- Veja estatísticas gerais e ranking de técnicos

#### Detalhes de Auditoria
- Acesse: **Ferramentas → Quality Audit → Audits**
- Filtre por período, técnico, status ou nota
- Clique em uma auditoria para ver detalhes completos

### Exemplo de Análise

**Chamado:**
```
Título: Internet lenta no setor administrativo
Descrição: Usuários relatam lentidão ao acessar sites externos
```

**Solução Original (Ruim):**
```
resolvido
```

**Resultado da Auditoria:**
- **Nota:** 25/100
- **Status:** ❌ RECUSADO
- **Análise:** Solução vaga, não explica o que foi feito
- **Sugestão de Melhoria:**
  ```
  Prezado usuário,
  
  Identificamos que o problema de lentidão era causado por sobrecarga 
  no servidor DNS. Realizamos as seguintes ações:
  
  1. Alteração dos servidores DNS para 1.1.1.1 (primário) e 8.8.8.8 (secundário)
  2. Limpeza do cache DNS nos computadores do setor
  3. Teste de velocidade confirmando normalização (50 Mbps download)
  
  A navegação deve estar normalizada. Caso persista alguma lentidão, 
  por favor nos informe através de um novo chamado.
  
  Atenciosamente,
  Equipe de TI
  ```

---

## 🎯 Critérios de Avaliação

### 1. Ortografia e Gramática (20 pts)
- ✅ **Bom:** Texto profissional, sem erros
- ❌ **Ruim:** Erros de português, abreviações inadequadas

### 2. Completude (30 pts)
- ✅ **Bom:** Explica o problema, ação tomada e resultado
- ❌ **Ruim:** Soluções vagas ("resolvido", "ok", "feito")

### 3. Resolução Efetiva (25 pts) - NOVO
- ✅ **Bom:** Indica que o problema foi realmente resolvido
- ❌ **Ruim:** "encerrado sem solução", "cancelado", "não resolvido"

### 4. Clareza e Tom (15 pts)
- ✅ **Bom:** Linguagem clara, cordial, compreensível
- ❌ **Ruim:** Jargões técnicos excessivos, tom rude

### 5. Adequação Técnica (10 pts)
- ✅ **Bom:** Solução condiz com o problema
- ❌ **Ruim:** Solução genérica ou irrelevante

---

## 📊 Dashboard

### Métricas Disponíveis

- **Total de Auditorias** realizadas
- **Taxa de Aprovação** (% de soluções ≥80)
- **Nota Média** geral
- **Top 5 Técnicos** (melhores médias)
- **Histórico Recente** (últimas 10 auditorias)

### Relatórios

- **Por técnico:** Desempenho individual
- **Por período:** Evolução temporal
- **Por tipo de chamado:** Incident vs Change vs Problem
- **Por criticidade:** Scores <60, 60-79, 80-100

---

## 🔒 Segurança

### API Key
- Armazenada **criptografada** no banco
- Nunca exposta em logs ou telas
- Acesso restrito a administradores

### Permissões
- **Visualizar Dashboard:** `ticket` (READ)
- **Configurar Plugin:** `config` (UPDATE)
- **Ver Auditorias:** `ticket` (READ)

### Logs
- Erros de API registrados em `glpi/files/_log/php-errors.log`
- Auditorias salvas no banco (`glpi_plugin_qualityaudit_audits`)
- Resposta completa da IA disponível (campo `api_response`)

### 🆓 Free vs Pro
- **Gratuito:** 100 auditorias/mês, 1 entidade
- **Pro:** Ilimitado, múltiplas entidades, relatórios PDF, webhooks, suporte

---

## 🛠️ Troubleshooting

### Plugin não aparece no menu
```bash
# Verificar permissões
chmod -R 755 /var/www/html/glpi/plugins/qualityaudit
chown -R www-data:www-data /var/www/html/glpi/plugins/qualityaudit

# Limpar cache do GLPi
rm -rf /var/www/html/glpi/files/_cache/*
```

### API Key não funciona
1. Verificar se a chave está correta
2. Testar com `curl`:
```bash
# OpenAI
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer YOUR_KEY"

# Claude
curl https://api.anthropic.com/v1/messages \
  -H "x-api-key: YOUR_KEY" \
  -H "anthropic-version: 2023-06-01"
```

### Auditorias não estão sendo criadas
1. Verificar logs: `tail -f /var/www/html/glpi/files/_log/php-errors.log`
2. Verificar se `auto_audit` está ativo
3. Verificar se o tipo de chamado está configurado para auditoria
4. Testar manualmente via dashboard

### Erro "Failed to parse AI response"
- A IA pode retornar formato inválido ocasionalmente
- Verificar campo `api_response` na tabela para debug
- Considerar usar `gpt-4o-mini` ao invés de `gpt-4` (mais estável)

---

## 💰 Custos Estimados

### OpenAI (gpt-4o-mini)
- **Input:** $0.150 / 1M tokens
- **Output:** $0.600 / 1M tokens
- **Por auditoria:** ~500 tokens (~$0.0005)
- **1000 auditorias/mês:** ~$0.50/mês ⭐ **MAIS BARATO**

### Anthropic (claude-3-5-sonnet)
- **Input:** $3.00 / 1M tokens
- **Output:** $15.00 / 1M tokens
- **Por auditoria:** ~500 tokens (~$0.01)
- **1000 auditorias/mês:** ~$10/mês

### Google (gemini-1.5-flash)
- **Input:** $0.075 / 1M tokens
- **Output:** $0.30 / 1M tokens
- **Por auditoria:** ~500 tokens (~$0.0002)
- **1000 auditorias/mês:** ~$0.20/mês ⭐ **MAIS BARATO AINDA**

---

## 🤝 Contribuindo

Contribuições são bem-vindas!

```bash
git clone https://github.com/fernandokarl/glpi-quality-audit.git
cd glpi-quality-audit
# Faça suas alterações
git commit -m "feat: nova funcionalidade"
git push origin main
```

---

## 📝 Licença

**MIT License** - Use livremente em projetos comerciais e pessoais.

---

## 🆘 Suporte

- **Issues:** https://github.com/fernandokarl/glpi-quality-audit/issues
- **Email:** fernando@example.com
- **Documentação:** https://github.com/fernandokarl/glpi-quality-audit/wiki

---

## 📚 Documentação Adicional

- **[ROADMAP.md](ROADMAP.md)** - 🗺️ Plano de evolução completo do plugin
- **[REALTIME_VALIDATION.md](REALTIME_VALIDATION.md)** - 🔥 Guia de validação em tempo real (NOVO em v1.2)
- **[ENTITY_CONFIG.md](ENTITY_CONFIG.md)** - Guia completo de configuração por entidade
- **[INSTALL.md](INSTALL.md)** - Instruções de instalação
- **[README.md](README.md)** - Documentação principal (este arquivo)

---

## 🎉 Roadmap

### v1.2 (Atual) ✅
- [x] **Validação preventiva em tempo real**
- [x] Bloqueio de salvamento se score baixo
- [x] Feedback visual com score e sugestões
- [x] Loop de correção até atingir threshold
- [x] Failsafe automático (permite salvar se API cair)

### v1.1 ✅
- [x] **Configuração por entidade**
- [x] Herança de configurações
- [x] Isolamento de API keys por entidade

### v1.3 - Maturidade (Q1 2026) ✅
- [x] **Notificações via email** para técnicos
- [x] **Relatórios PDF** exportáveis
- [x] **Lista de auditorias** com paginação
- [x] **Dashboard melhorado** com cards e distribuição de notas
- [x] **Segurança** (CSRF, XSS, sanitization)
- [ ] Webhooks para n8n/Zapier

### v1.4 - Diferenciação (Q2 2026) 🚀
- [ ] AI Copilot (sugestão em tempo real enquanto escreve)
- [ ] Análise de sentimento do usuário
- [ ] Integração Slack/Teams
- [ ] Gamificação (badges, rankings)

### v2.0 - Escala (Q3 2026) 🏢
- [ ] API REST pública
- [ ] Multi-tenant para MSPs
- [ ] Audit trail completo (LGPD/SOC2)
- [ ] Modelo customizado

### Modelo de Monetização
- **Free:** 100 auditorias/mês, 1 entidade
- **Pro ($49/mês):** Ilimitado, múltiplas entidades, PDFs, webhooks
- **MSP ($149/mês):** White-label, multi-tenant, API

➡️ Ver **[ROADMAP.md](ROADMAP.md)** para detalhes completos.

---

**Desenvolvido com 💪 por Fernando Karl / Rehoboam AI**

**Projeto criado em 2026-02-09 - GLPi Quality Audit Plugin**
