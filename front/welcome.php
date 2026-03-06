<?php
/**
 * Welcome/Setup page for Quality Audit - First time access
 */


Session::checkRight('config', UPDATE);

Html::header(__('Quality Audit Setup', 'qualityaudit'), $_SERVER['PHP_SELF'], 'tools', 'pluginqualityauditmenu');

global $CFG_GLPI;

// Get real config state for the root entity
$config = PluginQualityauditConfig::getConfig(0);
$has_api_key    = !empty($config['api_key']);
$has_provider   = !empty($config['api_provider']);
$has_model      = !empty($config['api_model']);
$auto_audit_on  = !empty($config['auto_audit']);
$total_audits   = countElementsInTable('glpi_plugin_qualityaudit_audits');

// Determine step states
$step1_done = $has_api_key && $has_provider && $has_model;
$step2_done = $step1_done && $auto_audit_on;
$step3_done = $step2_done && $total_audits > 0;

// Progress calculation
$steps_done = (int)$step1_done + (int)$step2_done + (int)$step3_done;
$progress = round(($steps_done / 3) * 100);

// Config page URL
$config_url = $CFG_GLPI['root_doc'] . '/plugins/qualityaudit/front/config.form.php';
$dashboard_url = $CFG_GLPI['root_doc'] . '/plugins/qualityaudit/front/dashboard.php';

?>

<style>
   .qa-onboarding {
      max-width: 720px;
      margin: 0 auto;
      padding: 20px;
   }
   .qa-ob-header {
      text-align: center;
      margin-bottom: 28px;
   }
   .qa-ob-header h2 {
      margin: 12px 0 4px;
      font-size: 22px;
      font-weight: 600;
      color: #333;
   }
   .qa-ob-header p {
      color: #6c757d;
      font-size: 14px;
      margin: 0;
   }
   .qa-progress-bar {
      height: 8px;
      background: #e9ecef;
      border-radius: 4px;
      margin: 0 0 6px;
      overflow: hidden;
   }
   .qa-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #007bff, #28a745);
      transition: width 0.5s ease;
      border-radius: 4px;
   }
   .qa-progress-label {
      text-align: right;
      font-size: 12px;
      color: #6c757d;
      margin-bottom: 24px;
   }
   .qa-step {
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: 10px;
      padding: 20px 24px;
      margin-bottom: 16px;
      display: flex;
      align-items: flex-start;
      gap: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      transition: border-color 0.2s;
   }
   .qa-step.done {
      border-left: 4px solid #28a745;
   }
   .qa-step.current {
      border-left: 4px solid #007bff;
      background: #f8fbff;
   }
   .qa-step.pending {
      border-left: 4px solid #dee2e6;
      opacity: 0.6;
   }
   .qa-step-icon {
      flex-shrink: 0;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      margin-top: 2px;
   }
   .qa-step.done .qa-step-icon {
      background: #28a745;
   }
   .qa-step.current .qa-step-icon {
      background: #007bff;
   }
   .qa-step.pending .qa-step-icon {
      background: #adb5bd;
   }
   .qa-step-body {
      flex: 1;
      min-width: 0;
   }
   .qa-step-body h4 {
      margin: 0 0 4px;
      font-size: 15px;
      font-weight: 600;
      color: #333;
   }
   .qa-step-body p {
      margin: 0 0 10px;
      font-size: 13px;
      color: #6c757d;
      line-height: 1.5;
   }
   .qa-step-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 12px;
   }
   .qa-step-status.ok {
      background: #d4edda;
      color: #155724;
   }
   .qa-step-status.warn {
      background: #fff3cd;
      color: #856404;
   }
   .qa-step-status.info {
      background: #e8f4fd;
      color: #004085;
   }
   .qa-step-detail {
      font-size: 12px;
      color: #6c757d;
      margin-top: 8px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
   }
   .qa-step-detail span {
      display: inline-flex;
      align-items: center;
      gap: 4px;
   }
   .qa-step-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 16px;
      border-radius: 5px;
      font-size: 13px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.15s;
      cursor: pointer;
      border: none;
   }
   .qa-step-btn.primary {
      background: #007bff;
      color: #fff;
   }
   .qa-step-btn.primary:hover {
      background: #0056b3;
      color: #fff;
      text-decoration: none;
   }
   .qa-step-btn.success {
      background: #28a745;
      color: #fff;
   }
   .qa-step-btn.success:hover {
      background: #1e7e34;
      color: #fff;
      text-decoration: none;
   }
   .qa-step-btn.outline {
      background: transparent;
      border: 1px solid #dee2e6;
      color: #495057;
   }
   .qa-step-btn.outline:hover {
      background: #f8f9fa;
      text-decoration: none;
   }
   .qa-ob-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid #e9ecef;
   }
   @media (max-width: 600px) {
      .qa-onboarding { padding: 10px; }
      .qa-step { flex-direction: column; gap: 10px; padding: 16px; }
      .qa-step-icon { width: 30px; height: 30px; font-size: 12px; }
      .qa-ob-actions { flex-direction: column; }
      .qa-step-btn { width: 100%; justify-content: center; }
   }
</style>

<div class="qa-onboarding">

   <div class="qa-ob-header">
      <i class="fas fa-clipboard-check fa-2x" style="color: #007bff;"></i>
      <h2><?php echo __('Welcome to Quality Audit!', 'qualityaudit'); ?></h2>
      <p><?php echo __('Follow the steps below to activate AI-powered solution auditing.', 'qualityaudit'); ?></p>
   </div>

   <div class="qa-progress-bar">
      <div class="qa-progress-fill" style="width: <?php echo $progress; ?>%;"></div>
   </div>
   <div class="qa-progress-label"><?php echo $steps_done; ?>/3 <?php echo __('complete', 'qualityaudit'); ?></div>

   <!-- STEP 1: Configure API (Provider + Key + Model + Test) -->
   <?php
   $s1_class = $step1_done ? 'done' : 'current';
   ?>
   <div class="qa-step <?php echo $s1_class; ?>">
      <div class="qa-step-icon">
         <?php echo $step1_done ? '<i class="fas fa-check"></i>' : '1'; ?>
      </div>
      <div class="qa-step-body">
         <h4><?php echo __('Configure & Test API Connection', 'qualityaudit'); ?></h4>
         <p><?php echo __('Choose your AI provider, enter the API key, select the model, and test the connection. Everything is on the same page.', 'qualityaudit'); ?></p>

         <?php if ($step1_done): ?>
            <span class="qa-step-status ok"><i class="fas fa-check-circle"></i> <?php echo __('Connected', 'qualityaudit'); ?></span>
            <div class="qa-step-detail">
               <span><i class="fas fa-cloud"></i> <?php echo htmlspecialchars($config['api_provider'] ?? ''); ?></span>
               <span><i class="fas fa-robot"></i> <?php echo htmlspecialchars($config['api_model'] ?? ''); ?></span>
               <span><i class="fas fa-key"></i> <?php echo __('Key saved', 'qualityaudit'); ?></span>
            </div>
            <div style="margin-top: 10px;">
               <a href="<?php echo $config_url; ?>" class="qa-step-btn outline">
                  <i class="fas fa-pen"></i> <?php echo __('Edit Configuration', 'qualityaudit'); ?>
               </a>
            </div>
         <?php else: ?>
            <?php if ($has_provider && !$has_api_key): ?>
               <span class="qa-step-status warn"><i class="fas fa-exclamation-triangle"></i> <?php echo __('API Key missing', 'qualityaudit'); ?></span>
            <?php else: ?>
               <span class="qa-step-status warn"><i class="fas fa-exclamation-triangle"></i> <?php echo __('Not configured', 'qualityaudit'); ?></span>
            <?php endif; ?>
            <div style="margin-top: 10px;">
               <a href="<?php echo $config_url; ?>" class="qa-step-btn primary">
                  <i class="fas fa-cog"></i> <?php echo __('Open Configuration', 'qualityaudit'); ?>
               </a>
            </div>
         <?php endif; ?>
      </div>
   </div>

   <!-- STEP 2: Enable Auto Audit -->
   <?php
   $s2_class = !$step1_done ? 'pending' : ($step2_done ? 'done' : 'current');
   ?>
   <div class="qa-step <?php echo $s2_class; ?>">
      <div class="qa-step-icon">
         <?php echo $step2_done ? '<i class="fas fa-check"></i>' : '2'; ?>
      </div>
      <div class="qa-step-body">
         <h4><?php echo __('Enable Automatic Auditing', 'qualityaudit'); ?></h4>
         <p><?php echo __('When enabled, every solution added to a ticket will be automatically evaluated by AI and scored based on quality criteria.', 'qualityaudit'); ?></p>

         <?php if (!$step1_done): ?>
            <span class="qa-step-status info"><i class="fas fa-hourglass-half"></i> <?php echo __('Complete Step 1 first', 'qualityaudit'); ?></span>
         <?php elseif ($step2_done): ?>
            <span class="qa-step-status ok"><i class="fas fa-check-circle"></i> <?php echo __('Auto Audit is ON', 'qualityaudit'); ?></span>
            <div class="qa-step-detail">
               <?php
               $audit_types = explode(',', $config['audit_ticket_types'] ?? 'Ticket');
               $type_labels = ['Ticket' => __('Incidents', 'qualityaudit'), 'Change' => __('Changes', 'qualityaudit'), 'Problem' => __('Problems', 'qualityaudit')];
               foreach ($audit_types as $t) {
                  $label = $type_labels[$t] ?? $t;
                  echo "<span><i class='fas fa-check' style='color:#28a745;'></i> " . htmlspecialchars($label) . "</span>";
               }
               ?>
               <span><i class="fas fa-star"></i> <?php echo __('Threshold', 'qualityaudit'); ?>: <?php echo (int)($config['approval_threshold'] ?? 80); ?></span>
            </div>
            <div style="margin-top: 10px;">
               <a href="<?php echo $config_url; ?>" class="qa-step-btn outline">
                  <i class="fas fa-sliders-h"></i> <?php echo __('Adjust Rules', 'qualityaudit'); ?>
               </a>
            </div>
         <?php else: ?>
            <span class="qa-step-status warn"><i class="fas fa-pause-circle"></i> <?php echo __('Auto Audit is OFF', 'qualityaudit'); ?></span>
            <p style="font-size: 12px; color: #856404; margin: 8px 0 0;">
               <i class="fas fa-info-circle"></i>
               <?php echo __('The plugin will not audit solutions until this is enabled.', 'qualityaudit'); ?>
            </p>
            <div style="margin-top: 10px;">
               <a href="<?php echo $config_url; ?>" class="qa-step-btn primary">
                  <i class="fas fa-toggle-on"></i> <?php echo __('Enable in Configuration', 'qualityaudit'); ?>
               </a>
            </div>
         <?php endif; ?>
      </div>
   </div>

   <!-- STEP 3: Ready / Dashboard -->
   <?php
   $s3_class = !$step2_done ? 'pending' : ($step3_done ? 'done' : 'current');
   ?>
   <div class="qa-step <?php echo $s3_class; ?>">
      <div class="qa-step-icon">
         <?php echo $step3_done ? '<i class="fas fa-check"></i>' : '3'; ?>
      </div>
      <div class="qa-step-body">
         <h4><?php echo __('Monitor Results', 'qualityaudit'); ?></h4>

         <?php if (!$step2_done): ?>
            <p><?php echo __('Once configured and enabled, audit results will appear on the dashboard automatically as technicians close tickets.', 'qualityaudit'); ?></p>
            <span class="qa-step-status info"><i class="fas fa-hourglass-half"></i> <?php echo __('Waiting for setup', 'qualityaudit'); ?></span>
         <?php elseif ($total_audits == 0): ?>
            <p><?php echo __('Everything is set up! Audits will start appearing here as soon as a technician adds a solution to a ticket.', 'qualityaudit'); ?></p>
            <span class="qa-step-status info"><i class="fas fa-clock"></i> <?php echo __('Waiting for first audit...', 'qualityaudit'); ?></span>
            <div style="margin-top: 10px;">
               <a href="<?php echo $dashboard_url; ?>" class="qa-step-btn outline">
                  <i class="fas fa-chart-line"></i> <?php echo __('Open Dashboard', 'qualityaudit'); ?>
               </a>
            </div>
         <?php else: ?>
            <p><?php echo sprintf(__('You already have %d audit(s) recorded. Check the dashboard for scores, trends, and reports.', 'qualityaudit'), $total_audits); ?></p>
            <span class="qa-step-status ok"><i class="fas fa-chart-bar"></i> <?php echo sprintf(__('%d audits', 'qualityaudit'), $total_audits); ?></span>
            <div style="margin-top: 10px;">
               <a href="<?php echo $dashboard_url; ?>" class="qa-step-btn success">
                  <i class="fas fa-chart-line"></i> <?php echo __('Open Dashboard', 'qualityaudit'); ?>
               </a>
            </div>
         <?php endif; ?>
      </div>
   </div>

   <!-- Quick Actions -->
   <div class="qa-ob-actions">
      <a href="<?php echo $dashboard_url; ?>" class="qa-step-btn outline">
         <i class="fas fa-chart-line"></i> Dashboard
      </a>
      <a href="audit.php" class="qa-step-btn outline">
         <i class="fas fa-list"></i> <?php echo __('Audits', 'qualityaudit'); ?>
      </a>
      <a href="reports.php" class="qa-step-btn outline">
         <i class="fas fa-file-alt"></i> <?php echo __('Reports', 'qualityaudit'); ?>
      </a>
      <a href="<?php echo $config_url; ?>" class="qa-step-btn outline">
         <i class="fas fa-cog"></i> <?php echo __('Configuration', 'qualityaudit'); ?>
      </a>
   </div>

</div>

<?php Html::footer(); ?>
