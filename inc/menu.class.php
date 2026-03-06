<?php
/**
 * Menu class
 */
class PluginQualityauditMenu extends CommonGLPI {
   
   static $rightname = 'ticket';
   
   static function getMenuName() {
      return __('Quality Audit', 'qualityaudit');
   }
   
   static function getMenuContent() {
      global $DB;
      
      // Check if first time (no audits yet)
      $total_audits = countElementsInTable('glpi_plugin_qualityaudit_audits');
      
      // Check if API is configured
      $config = PluginQualityauditConfig::getConfig(0);
      $is_configured = !empty($config['api_key']);
      
      // Show welcome page if not configured, otherwise dashboard
      $default_page = (!$is_configured || $total_audits == 0) 
         ? '/plugins/qualityaudit/front/welcome.php' 
         : '/plugins/qualityaudit/front/dashboard.php';
      
      $menu = [];
      $menu['title'] = self::getMenuName();
      $menu['page']  = $default_page;
      $menu['icon']  = 'fas fa-clipboard-check';
      
      $menu['options']['welcome'] = [
         'title' => __('Getting Started', 'qualityaudit'),
         'page'  => '/plugins/qualityaudit/front/welcome.php',
         'icon'  => 'fas fa-rocket'
      ];
      
      $menu['options']['dashboard'] = [
         'title' => __('Dashboard', 'qualityaudit'),
         'page'  => '/plugins/qualityaudit/front/dashboard.php',
         'icon'  => 'fas fa-chart-line'
      ];
      
      $menu['options']['audits'] = [
         'title' => __('Audits', 'qualityaudit'),
         'page'  => '/plugins/qualityaudit/front/audit.php',
         'icon'  => 'fas fa-list'
      ];
      
      $menu['options']['reports'] = [
         'title' => __('Reports', 'qualityaudit'),
         'page'  => '/plugins/qualityaudit/front/reports.php',
         'icon'  => 'fas fa-file-pdf'
      ];
      
      $menu['options']['config'] = [
         'title' => __('Configuration', 'qualityaudit'),
         'page'  => '/plugins/qualityaudit/front/config.form.php',
         'icon'  => 'fas fa-cog'
      ];
      
      return $menu;
   }
}
