<?php
/**
 * Notification handler for Quality Audit plugin
 * Sends emails to technicians when solutions are refused
 */

class PluginQualityauditNotification {

   /**
    * Send email notification to technician when solution is refused
    *
    * @param array $audit_data Audit record data
    * @param array $ai_response AI response with score and analysis
    * @return bool Success
    */
   static function notifyTechnicianRefusal($audit_data, $ai_response) {
      global $DB, $CFG_GLPI;

      $technician_id = $audit_data['technician_id'] ?? 0;

      if (!$technician_id) {
         Toolbox::logInfo("Quality Audit: No technician ID to notify");
         return false;
      }

      // Get technician user data
      $user = new User();
      if (!$user->getFromDB($technician_id)) {
         Toolbox::logInFile('qualityaudit',"Quality Audit: Could not load user $technician_id");
         return false;
      }

      $email = $user->getDefaultEmail();
      if (empty($email)) {
         Toolbox::logInFile('qualityaudit',"Quality Audit: User $technician_id has no email");
         return false;
      }

      // Get ticket info for context - sanitize all inputs
      $ticket_id = (int)($audit_data['ticket_id'] ?? 0);
      $ticket_name = htmlspecialchars($audit_data['ticket_title'] ?? '#' . $ticket_id);
      $score = (int)($ai_response['nota'] ?? 0);
      $analysis = htmlspecialchars($ai_response['analise'] ?? '');
      $suggestion = htmlspecialchars($ai_response['sugestao_melhoria'] ?? '');

      // Validate score is in range
      $score = min(100, max(0, $score));

      // Build email content
      $subject = sprintf(__('[Quality Audit] Solution Refused - Ticket #%d', 'qualityaudit'), $ticket_id);

      // Get configured threshold from entity config
      $config = PluginQualityauditConfig::getConfig($audit_data['entities_id'] ?? 0);
      $threshold = (int)($config['approval_threshold'] ?? 80);

      $body = self::buildEmailBody($ticket_id, $ticket_name, $score, $analysis, $suggestion, $threshold);

      // Send email using GLPI's mailing system
      $sent = self::sendEmail($email, $subject, $body);

      if ($sent) {
         Toolbox::logInfo("Quality Audit: Notification sent to $email for ticket $ticket_id");
      } else {
         Toolbox::logInFile('qualityaudit',"Quality Audit: Failed to send notification to $email");
      }

      return $sent;
   }

   /**
    * Build email body HTML
    */
   static function buildEmailBody($ticket_id, $ticket_name, $score, $analysis, $suggestion, $threshold = 80) {
      global $CFG_GLPI;

      $ticket_url = $CFG_GLPI['url_base'] . "/front/ticket.form.php?id=$ticket_id";

      $html = "
      <html>
      <head>
         <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .score { font-size: 48px; font-weight: bold; color: #dc3545; }
            .details { margin: 20px 0; }
            .details th { text-align: left; padding: 8px; background: #e9ecef; }
            .details td { padding: 8px; }
            .suggestion { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
         </style>
      </head>
      <body>
         <div class='container'>
            <div class='header'>
               <h2>" . __('Solution Refused in Quality Audit', 'qualityaudit') . "</h2>
            </div>
            <div class='content'>
               <p>" . __('Dear user,', 'qualityaudit') . "</p>
               <p>" . sprintf(
                  __('Your solution for ticket <strong>#%d - %s</strong> was analyzed by Quality Audit and <strong>was not approved</strong>.', 'qualityaudit'),
                  $ticket_id, $ticket_name
               ) . "</p>

               <div class='details'>
                  <table width='100%'>
                     <tr>
                        <th>" . __('Ticket:', 'qualityaudit') . "</th>
                        <td>#$ticket_id - $ticket_name</td>
                     </tr>
                     <tr>
                        <th>" . __('Score:', 'qualityaudit') . "</th>
                        <td><span class='score'>$score/100</span> " . sprintf(__('(minimum: %d)', 'qualityaudit'), $threshold) . "</td>
                     </tr>
                     <tr>
                        <th>" . __('Status:', 'qualityaudit') . "</th>
                        <td><strong style='color: #dc3545;'>" . __('REFUSED', 'qualityaudit') . "</strong></td>
                     </tr>
                  </table>
               </div>

               <h3>" . __('Analysis:', 'qualityaudit') . "</h3>
               <p>" . nl2br($analysis) . "</p>
      ";

      if (!empty($suggestion)) {
         $html .= "
               <div class='suggestion'>
                  <h4>" . __('Improvement Suggestion:', 'qualityaudit') . "</h4>
                  <p>" . nl2br($suggestion) . "</p>
               </div>
         ";
      }

      $html .= "
               <h3>" . __('Next Steps:', 'qualityaudit') . "</h3>
               <ol>
                  <li>" . __('Review the solution provided in the ticket', 'qualityaudit') . "</li>
                  <li>" . __('Adjust the text according to the suggestion above', 'qualityaudit') . "</li>
                  <li>" . __('Resubmit the solution for validation', 'qualityaudit') . "</li>
               </ol>

               <p>" . __('To access the ticket directly, click the link below:', 'qualityaudit') . "</p>
               <p><a href='" . $ticket_url . "' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>" . sprintf(__('View Ticket #%d', 'qualityaudit'), $ticket_id) . "</a></p>
            </div>
            <div class='footer'>
               <p>" . __('This is an automated message from the GLPi Quality Audit System', 'qualityaudit') . "</p>
               <p>" . __('Do not reply to this email', 'qualityaudit') . "</p>
            </div>
         </div>
      </body>
      </html>
      ";

      return $html;
   }

   /**
    * Send email using GLPI's core functions
    */
   static function sendEmail($to, $subject, $body) {
      global $CFG_GLPI;

      // Check if emails are enabled
      if (empty($CFG_GLPI['use_notifications'])) {
         Toolbox::logInFile('qualityaudit', "Notifications disabled in GLPI config\n");
         return false;
      }

      // Get default sender (name must be string, never null)
      $from = $CFG_GLPI['noreply_email'] ?? $CFG_GLPI['admin_email'] ?? '';
      $fromname = (string)($CFG_GLPI['noreply_email_name'] ?? $CFG_GLPI['admin_email_name'] ?? '');

      if (empty($from)) {
         Toolbox::logInFile('qualityaudit', "No sender email configured in GLPI\n");
         return false;
      }

      try {
         $mailer = new GLPIMailer();
         $mailer->setFrom($from, $fromname);
         $mailer->addAddress($to);
         $mailer->Subject = $subject;
         $mailer->Body = $body;
         $mailer->AltBody = strip_tags(str_replace(['<br>', '<br />', '<br/>'], "\n", $body));

         if (!$mailer->send()) {
            $error = $mailer->getError() ?? 'Unknown error';
            Toolbox::logInFile('qualityaudit', "Mailer error: $error\n");
            return false;
         }

         return true;
      } catch (\Exception $e) {
         Toolbox::logInFile('qualityaudit', "Mailer exception: " . $e->getMessage() . "\n");
         return false;
      }
   }

   /**
    * Send summary report to administrators
    *
    * @param array $stats Statistics array
    * @param string $period Period description
    */
   static function notifyAdminSummary($stats, $period = 'daily') {
      global $CFG_GLPI;

      // Get all admins with notification enabled
      $admins = User::getUsersWithRight('config', UPDATE, true);

      $emails = [];
      foreach ($admins as $admin) {
         $email = $admin['default_email'] ?? '';
         if (!empty($email)) {
            $emails[] = $email;
         }
      }

      if (empty($emails)) {
         return false;
      }

      $subject = sprintf(__('[Quality Audit] Summary %s - %s', 'qualityaudit'), $period, date('d/m/Y'));

      $body = self::buildSummaryBody($stats, $period);

      foreach ($emails as $email) {
         self::sendEmail($email, $subject, $body);
      }

      return true;
   }

   /**
    * Build summary email body
    */
   static function buildSummaryBody($stats, $period) {
      global $CFG_GLPI;

      $total = $stats['total'] ?? 0;
      $approved = $stats['approved'] ?? 0;
      $refused = $stats['refused'] ?? 0;
      $avg_score = $stats['avg_score'] ?? 0;

      $approved_pct = $total > 0 ? round(($approved / $total) * 100) : 0;

      $html = "
      <html>
      <head>
         <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .stats { display: flex; justify-content: space-around; padding: 20px; }
            .stat { text-align: center; }
            .stat-value { font-size: 32px; font-weight: bold; }
            .stat-label { font-size: 12px; color: #666; }
         </style>
      </head>
      <body>
         <div class='container'>
            <div class='header'>
               <h2>" . __('Quality Audit Summary', 'qualityaudit') . "</h2>
               <p>" . sprintf(__('Period: %s', 'qualityaudit'), $period) . "</p>
            </div>
            <div class='stats'>
               <div class='stat'>
                  <div class='stat-value'>$total</div>
                  <div class='stat-label'>" . __('Total', 'qualityaudit') . "</div>
               </div>
               <div class='stat'>
                  <div class='stat-value' style='color: #28a745;'>$approved</div>
                  <div class='stat-label'>" . sprintf(__('Approved (%d%%)', 'qualityaudit'), $approved_pct) . "</div>
               </div>
               <div class='stat'>
                  <div class='stat-value' style='color: #dc3545;'>$refused</div>
                  <div class='stat-label'>" . __('Refused', 'qualityaudit') . "</div>
               </div>
               <div class='stat'>
                  <div class='stat-value'>$avg_score</div>
                  <div class='stat-label'>" . __('Avg Score', 'qualityaudit') . "</div>
               </div>
            </div>
            <p style='text-align: center;'>
               <a href='" . ($CFG_GLPI['url_base'] ?? '') . "/front/plugin.php?page=qualityaudit/dashboard'>" . __('View Full Dashboard', 'qualityaudit') . "</a>
            </p>
         </div>
      </body>
      </html>
      ";

      return $html;
   }
}
