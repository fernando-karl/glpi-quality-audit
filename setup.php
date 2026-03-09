<?php
/**
 * Plugin Quality Audit for GLPi
 * 
 * Analisa automaticamente a qualidade das soluções de chamados
 * usando IA (OpenAI/Claude) com base em critérios objetivos.
 * 
 * @version 1.0.0
 * @author Rehoboam AI / Fernando Karl
 * @license MIT
 */

define('PLUGIN_QUALITYAUDIT_VERSION', '1.0.7');
define('PLUGIN_QUALITYAUDIT_MIN_GLPI', '10.0.0');
define('PLUGIN_QUALITYAUDIT_MAX_GLPI', '11.0.99');

/**
 * Plugin init
 */
function plugin_init_qualityaudit() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['qualityaudit'] = true;
   
   $plugin = new Plugin();
   
   // Register firewall strategies for front scripts (GLPi 11+ only)
   if (class_exists('\Glpi\Http\Firewall')) {
      \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
         'qualityaudit',
         '#^/front/.*\.php$#',
         \Glpi\Http\Firewall::STRATEGY_AUTHENTICATED
      );
   }

   if ($plugin->isInstalled('qualityaudit') && $plugin->isActivated('qualityaudit')) {

      Plugin::registerClass('PluginQualityauditConfig', [
         'addtabon' => 'Config'
      ]);
      
      Plugin::registerClass('PluginQualityauditAudit');
      
      // Hook para interceptar fechamento de chamados
      $PLUGIN_HOOKS['item_add']['qualityaudit'] = [
         'ITILSolution' => 'plugin_qualityaudit_item_add_solution'
      ];
      
      $PLUGIN_HOOKS['item_update']['qualityaudit'] = [
         'ITILSolution' => 'plugin_qualityaudit_item_update_solution'
      ];
      
      // Menu
      $PLUGIN_HOOKS['menu_toadd']['qualityaudit'] = [
         'tools' => 'PluginQualityauditMenu'
      ];
      
      // Config page
      $PLUGIN_HOOKS['config_page']['qualityaudit'] = 'front/config.form.php';
      
      // Dashboard
      $PLUGIN_HOOKS['dashboard_cards']['qualityaudit'] = 'plugin_qualityaudit_dashboard_cards';
      
      // Add JavaScript for real-time validation
      $PLUGIN_HOOKS['add_javascript']['qualityaudit'] = ['js/solution_validator.js'];
      
      // Add responsive CSS
      $PLUGIN_HOOKS['add_css']['qualityaudit'] = ['css/responsive.css'];
   }
}

/**
 * Get plugin version
 */
function plugin_version_qualityaudit() {
   return [
      'name'           => __('Quality Audit', 'qualityaudit'),
      'version'        => PLUGIN_QUALITYAUDIT_VERSION,
      'author'         => 'Fernando Karl / Rehoboam AI',
      'license'        => 'MIT',
      'homepage'       => 'https://github.com/fernandokarl/glpi-quality-audit',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_QUALITYAUDIT_MIN_GLPI,
            'max' => PLUGIN_QUALITYAUDIT_MAX_GLPI
         ],
         'php' => [
            'min' => '7.4'
         ]
      ]
   ];
}

/**
 * Check plugin prerequisites
 */
function plugin_qualityaudit_check_prerequisites() {
   if (version_compare(GLPI_VERSION, PLUGIN_QUALITYAUDIT_MIN_GLPI, 'lt')
       || version_compare(GLPI_VERSION, PLUGIN_QUALITYAUDIT_MAX_GLPI, 'gt')) {
      echo "This plugin requires GLPi >= " . PLUGIN_QUALITYAUDIT_MIN_GLPI 
           . " and < " . PLUGIN_QUALITYAUDIT_MAX_GLPI;
      return false;
   }
   
   if (version_compare(PHP_VERSION, '7.4', 'lt')) {
      echo "This plugin requires PHP >= 7.4";
      return false;
   }
   
   return true;
}

/**
 * Check plugin configuration
 */
function plugin_qualityaudit_check_config() {
   return true;
}

/**
 * Hook: quando uma solução é adicionada
 */
function plugin_qualityaudit_item_add_solution(ITILSolution $solution) {
   // auditSolution() internally fetches the correct entity config from the ticket,
   // so we delegate all config checking to it rather than using session entity here.
   PluginQualityauditAudit::auditSolution($solution);
}

/**
 * Hook: quando uma solução é atualizada
 */
function plugin_qualityaudit_item_update_solution(ITILSolution $solution) {
   // auditSolution() internally fetches the correct entity config from the ticket.
   // For updates, we pass a flag so auditSolution can check reaudit_on_update.
   PluginQualityauditAudit::auditSolution($solution, true);
}
