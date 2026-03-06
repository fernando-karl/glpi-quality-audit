<?php
/**
 * Audit class - Main logic
 */

class PluginQualityauditAudit extends CommonDBTM {
   
   static $rightname = 'ticket';
   
   static function getTypeName($nb = 0) {
      return _n('Quality Audit', 'Quality Audits', $nb, 'qualityaudit');
   }
   
   /**
    * Auditar uma solução usando IA
    */
   static function auditSolution(ITILSolution $solution, $is_update = false) {
      global $DB;
      
      // Obter dados do chamado primeiro (para ter a entidade)
      $ticket_data = self::getTicketData($solution);
      
      if (!$ticket_data) {
         Toolbox::logInFile('qualityaudit','Quality Audit: Could not retrieve ticket data');
         return false;
      }
      
      // Obter configuração para a entidade do chamado
      $config = PluginQualityauditConfig::getConfig($ticket_data['entities_id']);
      
      // Check if auto audit is enabled
      if (!($config['auto_audit'] ?? true)) {
         return false;
      }

      // For updates, also check reaudit_on_update setting
      if ($is_update && !($config['reaudit_on_update'] ?? false)) {
         return false;
      }

      if (empty($config['api_key'])) {
         Toolbox::logInFile('qualityaudit','Quality Audit: API key not configured for entity ' . $ticket_data['entities_id']);
         return false;
      }
      
      // Verificar se o tipo de chamado está configurado para auditoria
      $audit_types = explode(',', $config['audit_ticket_types'] ?? 'Ticket');
      if (!in_array($ticket_data['itemtype'], $audit_types)) {
         return false; // Tipo não deve ser auditado
      }
      
      // Preparar prompt para IA
      $threshold = (int)($config['approval_threshold'] ?? 80);
      $prompt = self::buildPrompt($ticket_data, $threshold);
      
      // Chamar API de IA
      $ai_response = self::callAIWithRetry($prompt, $config);
      
      if (!$ai_response) {
         Toolbox::logInFile('qualityaudit','Quality Audit: AI API call failed');
         return false;
      }
      
      // Salvar auditoria
      $audit_id = self::saveAudit($solution, $ticket_data, $ai_response, $config);

      return $audit_id;
   }
   
   /**
    * Obter dados do chamado relacionado à solução
    */
   static function getTicketData(ITILSolution $solution) {
      $itemtype = $solution->fields['itemtype'];
      $items_id = $solution->fields['items_id'];

      // Whitelist allowed item types to prevent unsafe dynamic instantiation
      $allowed_types = ['Ticket', 'Change', 'Problem'];
      if (!in_array($itemtype, $allowed_types)) {
         Toolbox::logInFile('qualityaudit',"Quality Audit: Invalid itemtype '$itemtype'");
         return false;
      }

      $item = new $itemtype();
      if (!$item->getFromDB($items_id)) {
         return false;
      }
      
      return [
         'itemtype'    => $itemtype,
         'items_id'    => $items_id,
         'entities_id' => $item->fields['entities_id'] ?? 0,
         'title'       => $item->fields['name'] ?? '',
         'description' => $item->fields['content'] ?? '',
         'solution'    => $solution->fields['content'] ?? '',
         'tech_id'     => $solution->fields['users_id'] ?? 0
      ];
   }
   
   /**
    * Construir prompt para IA
    * Security: Sanitiza dados para evitar prompt injection
    */
   static function buildPrompt($ticket_data, $threshold = 80) {
      // Sanitize inputs to prevent prompt injection
      $sanitize = function($value) {
         if (empty($value)) return '';
         // Remove or escape potential prompt injection patterns
         $value = str_replace(['```', 'json', 'XML', 'HTML'], '', $value);
         // Limit length to prevent abuse
         return substr(trim($value), 0, 5000);
      };
      
      $title = $sanitize($ticket_data['title']);
      $description = $sanitize($ticket_data['description']);
      $solution = $sanitize($ticket_data['solution']);
      
      return "Contexto: Você é um assistente especializado em Auditoria de Qualidade de Atendimento ITSM. Sua tarefa é avaliar e sugerir melhorias no texto da solução proposta por um técnico para um chamado no GLPi.

IMPORTANTE:
1. Você SEMPRE deve fornecer uma sugestão de melhoria no campo sugestao_melhoria quando a nota for inferior a {$threshold}
2. Mantenha a essência do que o técnico escreveu, mas melhore o texto
3. NÃO invente procedimentos que não foram mencionados pelo técnico
4. Se o texto for vago ou curto demais (ex: \"resolvido\", \"ok\", \"feito\"), crie uma sugestão baseada no título e descrição do chamado, explicando de forma profissional o que provavelmente foi feito
5. SOMENTE deixe sugestao_melhoria vazio se a solução indicar explicitamente \"encerrado sem solução\", \"cancelado\", \"não resolvido\" — nesses casos a nota deve ser 0

Entradas do Chamado:
Título: {$title}
Descrição Inicial: {$description}
Solução Proposta: {$solution}

Critérios de Avaliação (Peso total: 100):
1. Ortografia e Gramática (20 pts): O texto é profissional e livre de erros?
2. Completude (30 pts): O texto explica adequadamente o que foi feito para resolver o problema? Textos vagos como \"resolvido\", \"ok\", \"feito\" ou frases genéricas sem detalhes devem receber nota baixa.
3. Resolução Efetiva (25 pts): O texto indica que o problema foi efetivamente resolvido?
4. Clareza e Tom (15 pts): O usuário final conseguirá entender a explicação? O tom é cordial e profissional?
5. Adequação Técnica (10 pts): O texto condiz com o problema descrito na descrição?

Instruções de Saída:
Retorne APENAS um objeto JSON válido (sem markdown, sem codigo) com os seguintes campos:
- nota: Um valor inteiro de 0 a 100
- analise: Um breve comentário sobre o que falta na solução e como melhorar
- status: \"APROVADO\" (se nota >= {$threshold}) ou \"RECUSADO\" (se nota < {$threshold})
- sugestao_melhoria: OBRIGATÓRIO quando status for RECUSADO. Reescreva o texto da solução de forma profissional, completa e clara. Use o título e descrição do chamado como contexto para elaborar uma solução adequada. Mantenha o que o técnico escreveu como base, mas adicione detalhes, tom profissional e explicações para o usuário. Deixe vazio APENAS se a solução for \"sem solução\"/\"cancelado\"/\"não resolvido\".
- criterios: Objeto com as notas individuais {\"ortografia\": X, \"completude\": Y, \"resolucao\": Z, \"clareza\": W, \"tecnica\": V}

Resposta em JSON:";
   }
   
   /**
    * Chamar API de IA (OpenAI/Claude/Gemini)
    * Security: Decrypt API key before use
    */
   static function callAI($prompt, $config) {
      $provider = $config['api_provider'] ?? 'openai';
      
      // Decrypt API key
      $api_key = $config['api_key'] ?? '';
      if (!empty($api_key)) {
         include_once Plugin::getPhpDir('qualityaudit') . '/hook.php';
         $api_key = plugin_qualityaudit_decrypt_key($api_key);
      }
      
      $model = $config['api_model'] ?? 'gpt-4o-mini';
      
      switch ($provider) {
         case 'openai':
            return self::callOpenAI($prompt, $api_key, $model);
         case 'claude':
            return self::callClaude($prompt, $api_key, $model);
         case 'gemini':
            return self::callGemini($prompt, $api_key, $model);
         default:
            return false;
      }
   }
   
   /**
    * Chamar OpenAI API
    */
   static function callOpenAI($prompt, $api_key, $model) {
      $url = 'https://api.openai.com/v1/chat/completions';
      
      $data = [
         'model' => $model,
         'messages' => [
            ['role' => 'user', 'content' => $prompt]
         ],
         'response_format' => ['type' => 'json_object']
      ];
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
         'Content-Type: application/json',
         'Authorization: Bearer ' . $api_key
      ]);
      
      $response = curl_exec($ch);
      if ($response === false) {
         $err = curl_error($ch);
         curl_close($ch);
         Toolbox::logInFile('qualityaudit',"Quality Audit: OpenAI cURL error: $err");
         return false;
      }
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($http_code !== 200) {
         Toolbox::logInFile('qualityaudit',"OpenAI API error: HTTP $http_code - $response");
         return false;
      }
      
      $result = json_decode($response, true);
      $content = $result['choices'][0]['message']['content'] ?? '';

      Toolbox::logInFile('qualityaudit', "OpenAI raw response (HTTP $http_code): " . substr($content, 0, 2000));

      // Parse JSON response
      $parsed = json_decode($content, true);

      if (!$parsed) {
         Toolbox::logInFile('qualityaudit',"Failed to parse AI response: $content");
         return false;
      }

      return $parsed;
   }

   /**
    * Chamar Claude API
    */
   static function callClaude($prompt, $api_key, $model) {
      $url = 'https://api.anthropic.com/v1/messages';
      
      $data = [
         'model' => $model,
         'max_tokens' => 1024,
         'messages' => [
            ['role' => 'user', 'content' => $prompt]
         ]
      ];
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
         'Content-Type: application/json',
         'x-api-key: ' . $api_key,
         'anthropic-version: 2023-06-01'
      ]);
      
      $response = curl_exec($ch);
      if ($response === false) {
         $err = curl_error($ch);
         curl_close($ch);
         Toolbox::logInFile('qualityaudit',"Quality Audit: Claude cURL error: $err");
         return false;
      }
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($http_code !== 200) {
         Toolbox::logInFile('qualityaudit',"Claude API error: HTTP $http_code - $response");
         return false;
      }
      
      $result = json_decode($response, true);
      $content = $result['content'][0]['text'] ?? '';

      Toolbox::logInFile('qualityaudit', "Claude raw response (HTTP $http_code): " . substr($content, 0, 2000));

      // Parse JSON response
      $parsed = json_decode($content, true);

      if (!$parsed) {
         Toolbox::logInFile('qualityaudit',"Failed to parse AI response: $content");
         return false;
      }

      return $parsed;
   }

   /**
    * Chamar Gemini API
    */
   static function callGemini($prompt, $api_key, $model) {
      // Note: Gemini API requires the key in the URL query string (no header auth option).
      // This is a Google API design limitation, not a bug.
      $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";
      
      $data = [
         'contents' => [
            ['parts' => [['text' => $prompt]]]
         ],
         'generationConfig' => [
            'temperature' => 0.3,
            'responseMimeType' => 'application/json'
         ]
      ];
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
         'Content-Type: application/json'
      ]);
      
      $response = curl_exec($ch);
      if ($response === false) {
         $err = curl_error($ch);
         curl_close($ch);
         Toolbox::logInFile('qualityaudit',"Quality Audit: Gemini cURL error: $err");
         return false;
      }
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($http_code !== 200) {
         Toolbox::logInFile('qualityaudit',"Gemini API error: HTTP $http_code - $response");
         return false;
      }
      
      $result = json_decode($response, true);
      $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

      Toolbox::logInFile('qualityaudit', "Gemini raw response (HTTP $http_code): " . substr($content, 0, 2000));

      // Parse JSON response
      $parsed = json_decode($content, true);

      if (!$parsed) {
         Toolbox::logInFile('qualityaudit',"Failed to parse AI response: $content");
         return false;
      }

      return $parsed;
   }
   
   /**
    * Salvar auditoria no banco
    */
   static function saveAudit($solution, $ticket_data, $ai_response, $config) {
      global $DB;
      
      $audit = new self();
      
      $data = [
         'entities_id'           => $ticket_data['entities_id'],
         'items_id'              => $solution->fields['id'],
         'itemtype'              => 'ITILSolution',
         'ticket_id'             => $ticket_data['items_id'],
         'ticket_type'           => $ticket_data['itemtype'],
         'ticket_title'          => $ticket_data['title'],
         'ticket_description'    => $ticket_data['description'],
         'solution_content'      => $ticket_data['solution'],
         'score'                 => $ai_response['nota'] ?? 0,
         'status'                => $ai_response['status'] ?? 'PENDING',
         'analysis'              => $ai_response['analise'] ?? '',
         'improvement_suggestion'=> $ai_response['sugestao_melhoria'] ?? '',
         'criteria_scores'       => json_encode($ai_response['criterios'] ?? []),
         'api_response'          => json_encode($ai_response),
         'technician_id'         => $ticket_data['tech_id'],
         'date_creation'         => $_SESSION['glpi_currenttime']
      ];
      
      return $audit->add($data);
   }
   
   /**
    * Call AI with retry logic
    */
   static function callAIWithRetry($prompt, $config, $max_retries = 3) {
      $attempt = 0;
      $last_error = null;
      
      while ($attempt < $max_retries) {
         $attempt++;
         
         try {
            $response = self::callAI($prompt, $config);
            
            if ($response !== false) {
               return $response;
            }
            
            // Wait before retry (exponential backoff)
            if ($attempt < $max_retries) {
               sleep(pow(2, $attempt)); // 2s, 4s, 8s
            }
            
         } catch (Exception $e) {
            $last_error = $e->getMessage();
            Toolbox::logInFile('qualityaudit',"Quality Audit: AI call failed (attempt $attempt/$max_retries): " . $last_error);
            
            if ($attempt < $max_retries) {
               sleep(pow(2, $attempt));
            }
         }
      }
      
      Toolbox::logInFile('qualityaudit',"Quality Audit: AI call failed after $max_retries attempts");
      return false;
   }
}
