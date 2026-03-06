# 📦 Guia de Instalação Rápida - Quality Audit Plugin

## ⚡ Instalação em 5 Minutos

### 1. Download e Extração
```bash
cd /var/www/html/glpi/plugins
wget https://github.com/fernandokarl/glpi-quality-audit/archive/main.zip
unzip main.zip
mv glpi-quality-audit-main qualityaudit
rm main.zip
```

### 2. Permissões
```bash
chown -R www-data:www-data qualityaudit
chmod -R 755 qualityaudit
```

### 3. Instalar via GLPi
1. Acesse GLPi: **Configurar → Plugins**
2. Encontre **Quality Audit**
3. Clique **Instalar** → **Ativar**

### 4. Configurar API (OBRIGATÓRIO)
1. Obtenha uma API key:
   - **OpenAI:** https://platform.openai.com/api-keys
   - **Claude:** https://console.anthropic.com/settings/keys
   - **Gemini:** https://makersuite.google.com/app/apikey

2. Configure no GLPi:
   - **Ferramentas → Quality Audit → Configuração**
   - **API Provider:** openai
   - **API Key:** sk-proj-...
   - **AI Model:** gpt-4o-mini (recomendado)
   - **Auto Audit:** Sim
   - **Threshold:** 80

3. **Teste:** Clique em "Testar Conexão"

### 5. Pronto!
Ao fechar um chamado com solução, a auditoria será automática.

---

## 🔧 Configuração Avançada

### Base de Dados Manual (se necessário)
```bash
mysql -u root -p glpi < install.sql
```

### Debug
```bash
# Ver logs de erro
tail -f /var/www/html/glpi/files/_log/php-errors.log

# Verificar tabelas
mysql -u root -p glpi -e "SHOW TABLES LIKE 'glpi_plugin_qualityaudit%';"

# Ver auditorias
mysql -u root -p glpi -e "SELECT * FROM glpi_plugin_qualityaudit_audits ORDER BY id DESC LIMIT 5;"
```

### Desinstalar
1. GLPi: **Configurar → Plugins**
2. Quality Audit → **Desativar** → **Desinstalar**
3. (Opcional) Remover pasta:
```bash
rm -rf /var/www/html/glpi/plugins/qualityaudit
```

---

## ⚠️ Troubleshooting Comum

### Plugin não aparece
```bash
# Limpar cache GLPi
rm -rf /var/www/html/glpi/files/_cache/*

# Recarregar página (Ctrl+F5)
```

### Erro 500 ao instalar
```bash
# Verificar PHP error log
tail -f /var/www/html/glpi/files/_log/php-errors.log

# Verificar permissões
ls -la /var/www/html/glpi/plugins/qualityaudit
```

### Auditorias não funcionam
1. Verificar se API key está configurada
2. Testar conexão via "Testar Conexão"
3. Verificar se Auto Audit está ativo
4. Ver logs para erros de API

---

## 📞 Suporte

**Problemas?** Abra uma issue:
https://github.com/fernandokarl/glpi-quality-audit/issues
