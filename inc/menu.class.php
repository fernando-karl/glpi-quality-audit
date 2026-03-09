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
      $menu = [];
      $menu['title'] = self::getMenuName();
      $menu['page']  = '/plugins/qualityaudit/front/dashboard.php';
      $menu['icon']  = 'fas fa-clipboard-check';

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

      // Show config and setup only for users with config rights
      if (Session::haveRight('config', UPDATE)) {
         $menu['options']['welcome'] = [
            'title' => __('Getting Started', 'qualityaudit'),
            'page'  => '/plugins/qualityaudit/front/welcome.php',
            'icon'  => 'fas fa-rocket'
         ];

         $menu['options']['config'] = [
            'title' => __('Configuration', 'qualityaudit'),
            'page'  => '/plugins/qualityaudit/front/config.form.php',
            'icon'  => 'fas fa-cog'
         ];
      }

      return $menu;
   }
}
