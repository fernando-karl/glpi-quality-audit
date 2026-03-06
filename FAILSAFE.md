# 🛡️ Sistema de Failsafe - Quality Audit

## 📋 Visão Geral

O plugin implementa um **sistema de failsafe robusto em múltiplas camadas** para garantir que técnicos nunca fiquem bloqueados em situações críticas.

---

## 🎯 Filosofia do Failsafe

**Princípio:** Nunca bloquear operações críticas do helpdesk.

**Objetivo:** Validação de qualidade é importante, mas **não pode impedir o trabalho**.

---

## 🔄 Camadas de Proteção

### Camada 1: Retry Automático
- **3 tentativas** automáticas com exponential backoff (2s, 4s, 8s)
- Trata falhas temporárias de rede
- Transparente para o usuário

### Camada 2: Validação Básica Heurística
- Se IA falhar após 3 tentativas, aplica validação simplificada
- Baseada em regras (comprimento, padrões ruins, termos técnicos)
- Score máximo: 60/100 (indica "não auditado por IA")

### Camada 3: Bypass Manual
- Botões explícitos: **"Tentar Novamente"** e **"Salvar Mesmo Assim"**
- Usuário tem controle total
- Confirmação antes de salvar sem validação

### Camada 4: Log de Falhas
- Todas as falhas são registradas em `php-errors.log`
- Facilita debugging e identificação de problemas recorrentes
- Permite análise de disponibilidade da API

---

## 🎭 Comportamento em Diferentes Cenários

### Cenário 1: API Lenta (>10s)

**O que acontece:**
1. Primeira tentativa: timeout após 10s
2. Segunda tentativa: timeout após 10s (backoff 2s)
3. Terceira tentativa: timeout após 10s (backoff 4s)
4. **Ativa failsafe:** Validação básica aplicada

**Usuário vê:**
```
⚠️ Modo de Segurança Ativado (Failsafe)

Serviço de IA temporariamente indisponível.

📊 Validação Básica (Heurística):
Score estimado: 35/60 (máximo em modo failsafe)

Feedback:
- Solução é curta. Considere adicionar mais detalhes.
- Evite respostas genéricas como "resolvido". Explique o que foi feito.
- Considere adicionar ações específicas (ex: "configurado", "reiniciado").

✅ Você pode salvar mesmo assim
A validação completa com IA está temporariamente indisponível.

[🔄 Tentar Novamente] [💾 Salvar Mesmo Assim]
```

**Ações disponíveis:**
- **Tentar Novamente:** Nova tentativa de validação completa
- **Salvar Mesmo Assim:** Prossegue sem validação IA

---

### Cenário 2: API Indisponível (Erro 500)

**O que acontece:**
1. Primeira tentativa: HTTP 500
2. Backoff 2s
3. Segunda tentativa: HTTP 500
4. **Ativa failsafe imediatamente** (não espera 3 tentativas em erro crítico)

**Usuário vê:**
```
⚠️ Modo de Segurança Ativado (Failsafe)

API de IA retornou erro. Serviço pode estar em manutenção.

📊 Validação Básica aplicada: 40/60

✅ Você pode salvar mesmo assim

[🔄 Tentar Novamente] [💾 Salvar Mesmo Assim]
```

---

### Cenário 3: Erro de Rede

**O que acontece:**
1. JavaScript detecta erro de conexão
2. Não chega nem a tentar retry (network error)
3. **Ativa failsafe frontend imediatamente**

**Usuário vê:**
```
⚠️ Modo de Segurança Ativado (Failsafe)

Erro de conexão ao validar solução. O serviço de IA pode estar indisponível.

Erro de rede. Você pode salvar sem validação completa.

[🔄 Tentar Novamente] [💾 Salvar Mesmo Assim]
```

---

### Cenário 4: API Key Inválida

**O que acontece:**
1. Backend detecta API key ausente ou inválida
2. Retorna erro com `bypass: true`
3. **Ativa failsafe imediatamente** (sem retry)

**Usuário vê:**
```
⚠️ Modo de Segurança Ativado (Failsafe)

Quality Audit não configurado para esta entidade.

✅ Você pode salvar sem validação
Configure a API key em Ferramentas → Quality Audit → Configuração

[💾 Salvar Mesmo Assim]
```

---

### Cenário 5: Timeout de PHP

**O que acontece:**
1. Script PHP excede `max_execution_time` (padrão: 30s)
2. PHP retorna erro 500
3. Frontend detecta erro HTTP
4. **Ativa failsafe frontend**

**Usuário vê:**
```
⚠️ Modo de Segurança Ativado (Failsafe)

Erro de conexão (timeout). A validação demorou muito.

Você pode salvar sem validação completa.

[🔄 Tentar Novamente] [💾 Salvar Mesmo Assim]
```

---

## 📊 Validação Básica Heurística

Quando IA falha, aplica-se validação simplificada:

### Critérios (Score máx: 60)

#### 1. Comprimento (0-30 pts)
```
< 20 chars:   0 pts   (Muito curto)
< 50 chars:  10 pts   (Curto)
< 100 chars: 20 pts   (Adequado)
>= 100 chars: 30 pts  (Completo)
```

#### 2. Padrões Ruins (-20 pts)
Detecta soluções genéricas:
- "resolvido"
- "ok"
- "feito"
- "pronto"
- "done"
- "fixed"

**Penalidade:** -20 pontos se texto for APENAS isso.

#### 3. Estrutura de Sentenças (+15 pts)
- Conta sentenças (separadas por `.!?`)
- **Mínimo:** 2 sentenças
- **Bonus:** +15 pts se >= 2 sentenças

#### 4. Termos Técnicos (+15 pts)
Detecta ações técnicas:
- instalado, configurado, reiniciado
- verificado, testado, corrigido
- atualizado, replaced, configured, etc.

**Bonus:** +15 pts se contém pelo menos 1 termo.

#### 5. Polidez (+10 pts)
Detecta saudações/despedidas:
- prezado, caro, obrigado
- atenciosamente, dear, thank you
- regards, sincerely

**Bonus:** +10 pts se contém.

---

### Exemplo de Cálculo

**Texto:** "resolvido"

```
Comprimento: 9 chars → 0 pts
Padrão ruim: "resolvido" → -20 pts
Sentenças: 1 → 0 pts
Termos técnicos: não → 0 pts
Polidez: não → 0 pts

TOTAL: 0 pts (após cap em 0)
```

**Feedback:**
```
- Solução muito curta (menos de 20 caracteres)
- Evite respostas genéricas como "resolvido". Explique o que foi feito.
- Solução deveria ter pelo menos 2 sentenças explicando as ações tomadas
- Considere adicionar ações específicas (ex: "configurado", "reiniciado")
```

---

**Texto:** "Problema de impressão resolvido. Reinstalei o driver HP LaserJet."

```
Comprimento: 64 chars → 20 pts
Padrão ruim: não (não é APENAS "resolvido") → 0 pts
Sentenças: 2 → 15 pts
Termos técnicos: "reinstalei" → 15 pts
Polidez: não → 0 pts

TOTAL: 50 pts
```

**Feedback:**
```
- Bom: Contém palavras de ação técnica
```

---

## 🔧 Configuração do Failsafe

### Opções Disponíveis

#### 1. Timeout de Retry

**Arquivo:** `inc/audit.class.php`

```php
static function callAIWithRetry($prompt, $config, $max_retries = 3) {
    // Modificar aqui para ajustar timeout
    sleep(pow(2, $attempt)); // 2s, 4s, 8s
}
```

**Valores recomendados:**
- **Padrão:** 2, 4, 8 segundos (total ~14s)
- **Rápido:** 1, 2, 4 segundos (total ~7s)
- **Lento:** 3, 6, 12 segundos (total ~21s)

#### 2. Número de Tentativas

**Arquivo:** `front/validate_solution.php`

```php
$ai_response = PluginQualityauditAudit::callAIWithRetry($prompt, $config, 2); // Max 2 retries
```

**Valores recomendados:**
- **Real-time:** 2 retries (rápido)
- **Background:** 3 retries (mais robusto)

#### 3. Desabilitar Validação Básica

Se quiser **pular direto para bypass** sem validação heurística:

**Arquivo:** `front/validate_solution.php`

```php
if (!$ai_response) {
    // Comentar linhas da validação básica
    // $basic_validation = performBasicValidation(...);
    
    echo json_encode([
        'valid' => false,
        'error' => __('AI service unavailable. Please try again later.', 'qualityaudit'),
        'failsafe' => true,
        'bypass' => true,
        // Remover: 'basic_score' e 'basic_feedback'
    ]);
    exit;
}
```

---

## 📝 Logs de Failsafe

### Onde Encontrar

**Arquivo:** `/var/www/html/glpi/files/_log/php-errors.log`

### Formato

```
[2026-02-10 07:30:15] Quality Audit: AI service unavailable for ticket 1234. Allowing bypass.
[2026-02-10 07:31:22] Quality Audit: AI call failed (attempt 1/2): HTTP 500 - Internal Server Error
[2026-02-10 07:31:24] Quality Audit: AI call failed (attempt 2/2): HTTP 500 - Internal Server Error
[2026-02-10 07:31:24] Quality Audit: AI call failed after 2 attempts
```

### Análise de Logs

**Verificar taxa de falhas:**

```bash
grep "AI service unavailable" /var/www/html/glpi/files/_log/php-errors.log | wc -l
```

**Ver últimas 10 falhas:**

```bash
grep "Quality Audit.*failed" /var/www/html/glpi/files/_log/php-errors.log | tail -10
```

---

## 📊 Métricas de Failsafe

### KPIs Recomendados

1. **Taxa de Failsafe:**
   ```sql
   -- Não implementado ainda (v1.3)
   -- Requer salvar flag failsafe em audits
   ```

2. **Disponibilidade da API:**
   ```
   Uptime = (Total validações - Failsafes) / Total validações
   
   Exemplo: (1000 - 5) / 1000 = 99.5% uptime
   ```

3. **Tempo Médio de Validação:**
   ```
   Ideal: 3-5 segundos
   Alerta: >10 segundos
   Crítico: >15 segundos
   ```

---

## 🎓 Guia para Usuários

### O Que Fazer em Modo Failsafe

#### Opção 1: Tentar Novamente (Recomendado)
- Clique **"🔄 Tentar Novamente"**
- Aguarde nova tentativa de validação
- API pode ter se recuperado

#### Opção 2: Salvar Sem Validação
- Clique **"💾 Salvar Mesmo Assim"**
- Confirme no popup
- Solução será salva **sem auditoria IA**
- **Validação básica aplicada** (se disponível)

#### Opção 3: Melhorar Texto Manualmente
- Leia o feedback da validação básica
- Melhore o texto seguindo as sugestões
- Tente salvar novamente

---

### Boas Práticas em Failsafe

**✅ FAÇA:**
- Leia o feedback da validação básica
- Melhore o texto se possível
- Use "Tentar Novamente" em falhas temporárias
- Salve sem validação em situações urgentes

**❌ NÃO FAÇA:**
- Salvar textos ruins só porque failsafe permite
- Ignorar completamente o feedback básico
- Abusar do "Salvar Mesmo Assim"

---

## 🔒 Segurança do Failsafe

### Proteções Implementadas

#### 1. Confirmação de Bypass
```javascript
if (confirm('⚠️ Confirma que deseja salvar sem validação completa?')) {
    // Só salva se usuário confirmar
}
```

#### 2. Não Salva Automaticamente
- Failsafe **não auto-submete** o formulário
- Usuário deve clicar explicitamente em "Salvar Mesmo Assim"

#### 3. Autenticação Mantida
- Failsafe não bypassa autenticação
- Ainda requer `Session::checkLoginUser()`
- Ainda verifica permissões de entidade

#### 4. Log de Ações
- Todas as validações (com ou sem failsafe) são logadas
- Permite auditoria de uso de bypass

---

## 🐛 Troubleshooting

### Problema: Failsafe Ativa Sempre

**Causas possíveis:**
1. API key inválida
2. API provider bloqueando IP
3. Firewall bloqueando conexões HTTPS

**Solução:**
```bash
# 1. Testar API key manualmente
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer sk-..."

# 2. Verificar logs
tail -f /var/www/html/glpi/files/_log/php-errors.log

# 3. Testar conectividade
php -r "file_get_contents('https://api.openai.com/v1/models');"
```

### Problema: Validação Básica Muito Leniente

**Causa:** Score máximo de 60 permite textos ruins.

**Solução:** Ajustar threshold ou desabilitar validação básica.

```php
// front/validate_solution.php
// Comentar performBasicValidation() completamente
// Força retry ou manual fix
```

### Problema: Timeout Muito Curto

**Causa:** PHP `max_execution_time` baixo.

**Solução:**
```php
// front/validate_solution.php (topo)
set_time_limit(60); // 60 segundos ao invés de 30
```

---

## 📈 Roadmap do Failsafe

### v1.3 (Próxima)
- [ ] Salvar flag `failsafe` em audits (análise de taxa)
- [ ] Dashboard com uptime da API
- [ ] Alertas automáticos se taxa failsafe > 10%
- [ ] Fallback para modelo alternativo (GPT-4o-mini → GPT-3.5)

### v2.0 (Futuro)
- [ ] Validação offline com modelo local (Ollama)
- [ ] Cache de validações anteriores (textos similares)
- [ ] Fila de retry em background (não bloqueia usuário)

---

## 🎯 Resumo

**O failsafe garante:**
- ✅ Usuários nunca ficam bloqueados
- ✅ Validação básica sempre disponível
- ✅ Controle total sobre bypass
- ✅ Logs para análise de problemas
- ✅ Retry automático para falhas temporárias

**Filosofia:**
> "Validação de qualidade é importante, mas nunca pode bloquear operações críticas do helpdesk."

---

**Última atualização:** 2026-02-10  
**Versão:** 1.2.0  
**Autor:** Fernando Karl / Rehoboam AI
