<?php
/**
 * Encrypt API key for secure storage
 */
function plugin_qualityaudit_encrypt_key($api_key) {
   if (empty($api_key)) {
      return '';
   }
   if (class_exists('GLPIKey')) {
      try {
         return (new \GLPIKey())->encrypt($api_key);
      } catch (\Exception $e) {
         // Fallback to base64 if encryption fails
      }
   }
   return 'B64:' . base64_encode($api_key);
}

/**
 * Decrypt API key
 */
function plugin_qualityaudit_decrypt_key($encrypted_key) {
   if (empty($encrypted_key)) {
      return '';
   }
   // Check if it's base64 encoded (legacy)
   if (strpos($encrypted_key, 'B64:') === 0) {
      return base64_decode(substr($encrypted_key, 4));
   }
   // Use GLPI's decryption
   if (class_exists('GLPIKey')) {
      try {
         return (new \GLPIKey())->decrypt($encrypted_key);
      } catch (\Exception $e) {
         return '';
      }
   }
   return $encrypted_key;
}

/**
 * Install plugin
 */
function plugin_qualityaudit_install() {
   global $DB;
   
   $migration = new Migration(PLUGIN_QUALITYAUDIT_VERSION);
   
   // Tabela de configuração
   if (!$DB->tableExists('glpi_plugin_qualityaudit_configs')) {
      $query = "CREATE TABLE `glpi_plugin_qualityaudit_configs` (
         `id` INT(11) NOT NULL AUTO_INCREMENT,
         `entities_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'Entity ID (0 = root)',
         `is_recursive` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Apply to child entities',
         `api_provider` VARCHAR(50) DEFAULT 'openai',
         `api_key` VARCHAR(255) DEFAULT NULL,
         `api_model` VARCHAR(100) DEFAULT 'gpt-4',
         `auto_audit` TINYINT(1) DEFAULT 1,
         `reaudit_on_update` TINYINT(1) DEFAULT 0,
         `approval_threshold` INT(11) DEFAULT 80,
         `audit_ticket_types` VARCHAR(255) DEFAULT 'Ticket,Change,Problem',
         `notification_on_refusal` TINYINT(1) DEFAULT 1,
         `date_mod` TIMESTAMP NULL DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `entities_id` (`entities_id`),
         KEY `is_recursive` (`is_recursive`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
      
      $DB->doQuery($query);

      // Insert default config for root entity
      $DB->insert('glpi_plugin_qualityaudit_configs', [
         'entities_id' => 0,
         'is_recursive' => 1,
         'api_provider' => 'openai',
         'api_model' => 'gpt-4o-mini',
         'auto_audit' => 1,
         'approval_threshold' => 80
      ]);
   }
   
   // Tabela de auditorias
   if (!$DB->tableExists('glpi_plugin_qualityaudit_audits')) {
      $query = "CREATE TABLE `glpi_plugin_qualityaudit_audits` (
         `id` INT(11) NOT NULL AUTO_INCREMENT,
         `entities_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'Entity where audit was performed',
         `items_id` INT(11) NOT NULL COMMENT 'ID da solução (ITILSolution)',
         `itemtype` VARCHAR(100) NOT NULL DEFAULT 'ITILSolution',
         `ticket_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID do chamado',
         `ticket_type` VARCHAR(50) DEFAULT NULL COMMENT 'Ticket, Change, Problem',
         `ticket_title` TEXT DEFAULT NULL,
         `ticket_description` TEXT DEFAULT NULL,
         `solution_content` TEXT DEFAULT NULL,
         `score` INT(11) NOT NULL DEFAULT 0 COMMENT 'Nota 0-100',
         `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'APROVADO ou RECUSADO',
         `analysis` TEXT DEFAULT NULL COMMENT 'Comentário da análise',
         `improvement_suggestion` TEXT DEFAULT NULL COMMENT 'Sugestão de melhoria',
         `criteria_scores` TEXT DEFAULT NULL COMMENT 'JSON com notas por critério',
         `api_response` TEXT DEFAULT NULL COMMENT 'Resposta completa da API (debug)',
         `technician_id` INT(11) DEFAULT NULL COMMENT 'ID do técnico',
         `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
         `date_mod` TIMESTAMP NULL DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `entities_id` (`entities_id`),
         KEY `items_id` (`items_id`),
         KEY `ticket_id` (`ticket_id`),
         KEY `status` (`status`),
         KEY `score` (`score`),
         KEY `technician_id` (`technician_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
      
      $DB->doQuery($query);
   }

   // Tabela de histórico de melhorias
   if (!$DB->tableExists('glpi_plugin_qualityaudit_improvements')) {
      $query = "CREATE TABLE `glpi_plugin_qualityaudit_improvements` (
         `id` INT(11) NOT NULL AUTO_INCREMENT,
         `audit_id` INT(11) NOT NULL,
         `original_solution` TEXT DEFAULT NULL,
         `improved_solution` TEXT DEFAULT NULL,
         `accepted` TINYINT(1) DEFAULT 0 COMMENT 'Técnico aceitou sugestão?',
         `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`),
         KEY `audit_id` (`audit_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
      
      $DB->doQuery($query);
   }

   return true;
}

/**
 * Uninstall plugin
 */
function plugin_qualityaudit_uninstall() {
   global $DB;
   
   $tables = [
      'glpi_plugin_qualityaudit_configs',
      'glpi_plugin_qualityaudit_audits',
      'glpi_plugin_qualityaudit_improvements'
   ];
   
   foreach ($tables as $table) {
      $DB->dropTable($table, true);
   }
   
   return true;
}
