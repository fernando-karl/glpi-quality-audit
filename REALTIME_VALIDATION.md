# 🔒 Real-Time Solution Validation

## 📋 Nova Funcionalidade (v1.2)

O plugin agora **valida soluções ANTES de salvar**, bloqueando o envio de textos com qualidade abaixo do threshold configurado.

---

## 🎯 Como Funciona

### Fluxo de Validação

```
1. Analista digita solução → 
2. Clica "Adicionar" (ou "Validar Qualidade") →
3. Plugin intercepta e chama IA →
4. IA avalia e retorna score + feedback →
5a. Se score >= threshold → Permite salvar ✅
5b. Se score < threshold → BLOQUEIA e mostra sugestão ❌
6. Analista edita e tenta novamente →
7. Loop até atingir nota mínima
```

### Interface Visual

**Ao tentar salvar uma solução ruim:**

```
┌────────────────────────────────────────────┐
│ ✗ RECUSADO                      45/100     │
├────────────────────────────────────────────┤
│ [████████████░░░░░░░░░░░░░░░░] 45 pontos  │
├────────────────────────────────────────────┤
│ 🔤 Ortografia: 15 pts │ 📝 Completude: 10  │
│ 💬 Clareza: 12 pts    │ 🔧 Resolução: 8    │
├────────────────────────────────────────────┤
│ 📋 Análise:                                │
│ Solução muito vaga. Não explica o que foi │
│ feito nem como o problema foi resolvido.   │
├────────────────────────────────────────────┤
│ 💡 Sugestão de Melhoria:                   │
│                                            │
│ Prezado usuário,                           │
│                                            │
│ Identificamos que o problema era causado  │
│ por... (texto completo sugerido pela IA)  │
│                                            │
│ [Usar esta sugestão]                       │
├────────────────────────────────────────────┤
│ ⚠️ Ação Necessária:                        │
│ A solução não atingiu a nota mínima de 80. │
│ Revise e melhore o texto antes de salvar.  │
└────────────────────────────────────────────┘
```

**Ao atingir o score mínimo:**

```
┌────────────────────────────────────────────┐
│ ✓ APROVADO                      87/100     │
├────────────────────────────────────────────┤
│ [████████████████████░░░░░░░░] 87 pontos  │
├────────────────────────────────────────────┤
│ ✅ Pronto para enviar!                     │
│ Sua solução atingiu a qualidade mínima.    │
│ Clique em "Adicionar" para salvar.         │
└────────────────────────────────────────────┘
```

---

## 🚀 Instalação

### 1. Atualizar Plugin

```bash
cd /var/www/html/glpi/plugins/qualityaudit
git pull origin main
```

### 2. Verificar Arquivos

Certifique-se de que existem:
- ✅ `js/solution_validator.js` - JavaScript de validação
- ✅ `front/validate_solution.php` - Endpoint AJAX

### 3. Limpar Cache do GLPi

```bash
rm -rf /var/www/html/glpi/files/_cache/*
```

### 4. Reativar Plugin (se necessário)

1. Configurar → Plugins
2. Desativar "Quality Audit"
3. Ativar novamente

---

## ⚙️ Configuração

### Threshold por Entidade

A nota mínima é definida em **Ferramentas → Quality Audit → Configuração**:

- **Approval Threshold:** 80 (padrão)
- **Valores típicos:**
  - 70 - Leniente (permite textos básicos)
  - 80 - Padrão (qualidade moderada)
  - 90 - Rigoroso (apenas textos excelentes)

### Desabilitar Validação Preventiva

Se quiser voltar ao comportamento antigo (auditoria post-mortem):

1. Editar `setup.php`
2. Comentar linha:
   ```php
   // $PLUGIN_HOOKS['add_javascript']['qualityaudit'] = ['js/solution_validator.js'];
   ```

---

## 📖 Guia para Analistas

### Cenário 1: Primeira Tentativa Recusada

**Problema:** Você escreveu "resolvido" e tentou salvar.

**O que acontece:**
1. Sistema bloqueia salvamento
2. Mostra score baixo (ex: 25/100)
3. Exibe feedback: "Solução muito vaga"
4. Sugere texto melhor

**Ação:**
- Clique em **"Usar esta sugestão"**
- Edite o texto sugerido conforme necessário
- Tente salvar novamente

### Cenário 2: Segunda Tentativa Aprovada

**Problema:** Você melhorou o texto para 85/100.

**O que acontece:**
1. Sistema mostra ✅ APROVADO
2. Barra verde com 85 pontos
3. Permite salvar normalmente

**Ação:**
- Clique em **"Adicionar"** para salvar

### Cenário 3: Validação Manual

**Problema:** Quer validar antes de tentar salvar.

**Ação:**
1. Digite sua solução
2. Clique em **"🔍 Validar Qualidade"** (botão azul abaixo do textarea)
3. Veja o score e feedback
4. Ajuste se necessário
5. Salve quando estiver pronto

---

## 🎓 Dicas para Atingir Boa Nota

### ✅ Boas Práticas

1. **Seja Específico:**
   ```
   ❌ "Resolvido o problema de internet"
   ✅ "Reiniciado o roteador após identificar perda de pacotes.
       Velocidade normalizada para 100 Mbps."
   ```

2. **Explique o Processo:**
   ```
   ❌ "Trocado o cabo"
   ✅ "Identificado cabo Ethernet danificado. Substituído por cabo
       Cat6 novo. Testado conectividade com sucesso."
   ```

3. **Use Linguagem Profissional:**
   ```
   ❌ "vlw, ja arrumei ai"
   ✅ "Prezado usuário, o problema foi solucionado. Caso persista,
       entre em contato novamente."
   ```

4. **Seja Completo:**
   ```
   ❌ "ok"
   ✅ "Problema: Impressora não imprimia
       Causa: Driver desatualizado
       Ação: Instalado driver v2.5.1 do site do fabricante
       Resultado: Impressão funcionando normalmente"
   ```

### ❌ Erros Comuns

| Erro | Score | Correção |
|------|-------|----------|
| "resolvido" | 10-20 | Explique O QUE foi feito |
| "ok" | 5-15 | Descreva a solução completa |
| "ta funfando" | 15-25 | Use português formal |
| Sem contexto | 20-40 | Explique o problema + ação + resultado |
| Muito técnico | 40-60 | Simplifique para usuário final |

---

## 🔧 Troubleshooting

### Problema: Botão "Adicionar" não responde

**Causa:** JavaScript não carregado.

**Solução:**
1. Abra Console do navegador (F12)
2. Veja se há erros
3. Verifique se `solution_validator.js` está carregando
4. Limpe cache do navegador (Ctrl+Shift+Del)

### Problema: Validação demora muito

**Causa:** API de IA lenta ou com problemas.

**Solução:**
- **Se > 10 segundos:** API pode estar indisponível
- Sistema permite salvar sem validação (failsafe)
- Verifique logs: `/var/www/html/glpi/files/_log/php-errors.log`

### Problema: Score sempre baixo mesmo com texto bom

**Causa:** Threshold muito alto ou contexto do chamado inadequado.

**Solução:**
1. Verifique threshold em Configuração
2. Reduza para 70 (teste)
3. Certifique-se de que descrição do chamado está preenchida

### Problema: Validação não aparece

**Causa 1:** Plugin não configurado para a entidade.

**Solução:** Configure API key em Ferramentas → Quality Audit → Configuração

**Causa 2:** JavaScript bloqueado por AdBlock/NoScript.

**Solução:** Adicione exceção para o domínio do GLPi

---

## 🔒 Segurança

### Bypass de Validação

**Quando acontece:**
- API de IA indisponível (timeout)
- Erro de configuração (sem API key)
- Erro interno do servidor

**Comportamento:**
- Sistema mostra aviso amarelo
- Permite salvar mesmo assim (failsafe)
- Registra erro no log

**Motivo:** Evitar bloquear técnicos em situações críticas.

### Dados Enviados

O endpoint `validate_solution.php` envia para a API de IA:
- Título do chamado
- Descrição do chamado
- Texto da solução (proposta)

**Não envia:**
- Dados do usuário final
- Histórico do chamado
- Arquivos anexos

---

## 📊 Métricas de Uso

### Estatísticas Disponíveis

Após implementação, você pode ver:
- **Taxa de aprovação na primeira tentativa** (ideal: >60%)
- **Tentativas médias até aprovação** (ideal: 1.5)
- **Score médio** (ideal: >80)

### Query SQL para Métricas

```sql
-- Taxa de aprovação na primeira tentativa
SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as approved,
  ROUND(SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as approval_rate
FROM glpi_plugin_qualityaudit_audits
WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Score médio por técnico (top 10)
SELECT 
  u.name,
  AVG(a.score) as avg_score,
  COUNT(*) as total_audits
FROM glpi_plugin_qualityaudit_audits a
JOIN glpi_users u ON a.technician_id = u.id
WHERE a.date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id, u.name
ORDER BY avg_score DESC
LIMIT 10;
```

---

## 🎯 Casos de Uso

### Caso 1: Treinamento de Novos Técnicos

**Objetivo:** Ensinar padrão de qualidade.

**Configuração:**
- Threshold: 85 (rigoroso)
- Notification on Refusal: Sim
- Mostrar sugestões: Sim

**Benefício:** Técnicos aprendem com exemplos da IA.

### Caso 2: Departamento de Suporte (High-Volume)

**Objetivo:** Garantir mínimo de qualidade sem travar operação.

**Configuração:**
- Threshold: 70 (leniente)
- Auto Audit: Sim
- Re-audit on Update: Não

**Benefício:** Rapidez + qualidade aceitável.

### Caso 3: Suporte Premium (SLA Crítico)

**Objetivo:** Qualidade máxima, clientes exigentes.

**Configuração:**
- Threshold: 90 (muito rigoroso)
- Re-audit on Update: Sim
- Notification on Refusal: Sim + Email

**Benefício:** Apenas soluções excelentes são enviadas.

---

## 📚 Perguntas Frequentes

### 1. O que acontece se a API da IA cair?

**Resposta:** Sistema permite salvar sem validação (failsafe mode). Um aviso amarelo é exibido.

### 2. Posso desabilitar para alguns técnicos?

**Resposta:** Não diretamente. Você pode:
- Criar entidade separada sem configuração de API
- Ou dar permissão de admin (bypass)

### 3. A validação consome muitos créditos da API?

**Resposta:** 
- Cada validação = 1 chamada à API
- Se técnico tenta 3x até aprovar = 3 chamadas
- Custo médio: $0.001-0.01 por auditoria (OpenAI gpt-4o-mini)
- Para 1000 chamados/mês: ~$1-10/mês

### 4. Posso validar soluções já salvas (batch)?

**Resposta:** Sim, use o endpoint:
```bash
curl -X POST "https://glpi.example.com/plugins/qualityaudit/front/validate_solution.php" \
  -H "Content-Type: application/json" \
  -d '{"solution_content": "...", "ticket_id": 123}'
```

### 5. Como ver o histórico de tentativas de um técnico?

**Resposta:** Não é salvo automaticamente (validação em tempo real). Apenas a auditoria final é salva após aprovação. Para rastrear, adicione log customizado.

---

## 🔄 Migração do Comportamento Antigo

Se você usava v1.0/v1.1 (auditoria post-mortem), veja as diferenças:

| Aspecto | v1.0/v1.1 (Antigo) | v1.2 (Novo) |
|---------|-------------------|-------------|
| **Quando audita** | Depois de salvar | Antes de salvar |
| **Bloqueio** | Não bloqueia | Bloqueia se score baixo |
| **Feedback** | Via notificação/email | Em tempo real na tela |
| **Tentativas** | 1 (salvo e acabou) | Infinitas (até aprovar) |
| **Banco de dados** | Todas auditorias salvas | Apenas auditorias aprovadas |

**Vantagens do novo modo:**
- ✅ Previne soluções ruins desde o início
- ✅ Feedback imediato para o técnico
- ✅ Aprende com sugestões da IA
- ✅ Menos retrabalho

**Desvantagens:**
- ❌ Pode desacelerar técnicos (se threshold alto)
- ❌ Depende de conectividade com API
- ❌ Não rastreia tentativas fracassadas

---

## 📈 Roadmap v1.3

- [ ] Salvar histórico de tentativas (audit trail)
- [ ] Modo "sugestão apenas" (não bloqueia)
- [ ] Configurar threshold por tipo de chamado
- [ ] Bypass temporário para emergências
- [ ] Integração com gamificação (pontos por qualidade)

---

**Última atualização:** 2026-02-10  
**Versão:** 1.2.0  
**Autor:** Fernando Karl / Rehoboam AI
