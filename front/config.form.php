<?php
/**
 * Configuration page with Entity support
 */


Session::checkRight('config', UPDATE);

Html::header(__('Quality Audit', 'qualityaudit'), $_SERVER['PHP_SELF'], 'config', 'plugins');

// Handle config update (must be checked BEFORE view_entity since both are present in the form)
if (isset($_POST['update_config'])) {
   $entities_id = (int)($_POST['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
   
   // Encrypt API key if provided and not masked
   $api_key = $_POST['api_key'] ?? '';
   if (!empty($api_key) && $api_key !== '********') {
      // New key provided - encrypt it
      include_once __DIR__ . '/../hook.php';
      $api_key = plugin_qualityaudit_encrypt_key($api_key);
   } elseif ($api_key === '********') {
      // User didn't change the key - get existing
      $existing_config = PluginQualityauditConfig::getConfig($entities_id);
      $api_key = $existing_config['api_key'] ?? '';
   } else {
      $api_key = '';
   }
   
   $data = [
      'entities_id'           => $entities_id,
      'is_recursive'          => (int)($_POST['is_recursive'] ?? 0),
      'api_provider'          => preg_replace('/[^a-z]/i', '', $_POST['api_provider'] ?? 'openai'),
      'api_key'               => $api_key,
      'api_model'             => preg_replace('/[^a-z0-9\-_.]/i', '', $_POST['api_model'] ?? 'gpt-4o-mini'),
      'auto_audit'            => (int)($_POST['auto_audit'] ?? 0),
      'reaudit_on_update'     => (int)($_POST['reaudit_on_update'] ?? 0),
      'approval_threshold'    => min(100, max(0, (int)($_POST['approval_threshold'] ?? 80))),
      'audit_ticket_types'    => implode(',', array_filter($_POST['audit_ticket_types'] ?? ['Ticket'], function($v) {
         return in_array($v, ['Ticket', 'Change', 'Problem']);
      })),
      'notification_on_refusal' => 0
   ];
   
   if (PluginQualityauditConfig::updateConfig($data)) {
      Session::addMessageAfterRedirect(
         sprintf(__('Configuration saved successfully for entity: %s', 'qualityaudit'), 
                 Dropdown::getDropdownName('glpi_entities', $entities_id)),
         false, 
         INFO
      );
   } else {
      Session::addMessageAfterRedirect(__('Error saving configuration', 'qualityaudit'), false, ERROR);
   }
   
   Html::back();
}

// Handle config deletion
if (isset($_POST['delete_config'])) {
   $entities_id = (int)($_POST['entities_id'] ?? 0);
   
   if ($entities_id != 0) { // Don't allow deleting root entity config
      if (PluginQualityauditConfig::deleteConfig($entities_id)) {
         Session::addMessageAfterRedirect(
            __('Configuration deleted. Entity will now use inherited configuration.', 'qualityaudit'),
            false,
            INFO
         );
      } else {
         Session::addMessageAfterRedirect(__('Error deleting configuration', 'qualityaudit'), false, ERROR);
      }
   } else {
      Session::addMessageAfterRedirect(__('Cannot delete root entity configuration', 'qualityaudit'), false, ERROR);
   }
   
   Html::back();
}

// Handle entity view change (no CSRF needed, read-only action)
if (isset($_POST['view_entity'])) {
   $entities_id = (int)($_POST['entities_id'] ?? 0);
   $config = new PluginQualityauditConfig();
   $config->showForm(1, ['entities_id' => $entities_id]);
   Html::footer();
   return;
}

// Show form
$config = new PluginQualityauditConfig();
$entities_id = (int)($_GET['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
$config->showForm(1, ['entities_id' => $entities_id]);

Html::footer();
