<?php
/**
 * Reports page for Quality Audit
 */


Session::checkRight('ticket', READ);

Html::header(__('Quality Audit Reports', 'qualityaudit'), $_SERVER['PHP_SELF'], 'tools', 'pluginqualityauditmenu');

global $DB;

// CSRF is automatically validated by GLPi's CheckCsrfListener for all POST requests

// Handle report generation
$message = '';
if (isset($_POST['generate_report'])) {
   // Sanitize and validate inputs
   $date_from = '';
   if (!empty($_POST['date_from'])) {
      $d = DateTime::createFromFormat('Y-m-d', $_POST['date_from']);
      if ($d && $d->format('Y-m-d') === $_POST['date_from']) {
         $date_from = $_POST['date_from'];
      }
   }

   $date_to = '';
   if (!empty($_POST['date_to'])) {
      $d = DateTime::createFromFormat('Y-m-d', $_POST['date_to']);
      if ($d && $d->format('Y-m-d') === $_POST['date_to']) {
         $date_to = $_POST['date_to'];
      }
   }
   
   $technician_id = 0;
   if (!empty($_POST['technician_id']) && is_numeric($_POST['technician_id'])) {
      $technician_id = (int)$_POST['technician_id'];
   }
   
   $status = '';
   if (in_array($_POST['status'], ['APROVADO', 'RECUSADO', ''])) {
      $status = $_POST['status'];
   }
   
   $filters = [
      'date_from' => $date_from,
      'date_to' => $date_to,
      'technician_id' => $technician_id,
      'status' => $status
   ];
   
   // Include report class
   include_once __DIR__ . '/../inc/report.class.php';
   
   $report_file = PluginQualityauditReport::generatePdfReport($filters);
   
   if (file_exists($report_file) && preg_match('/^[a-zA-Z0-9_\-\.]+$/', basename($report_file))) {
      $filename = basename($report_file);
      $message = sprintf(__('Report generated successfully: %s', 'qualityaudit'), $filename);
      
      // Offer download - use safe path
      $safe_filename = basename($report_file);
      echo "<div class='center' style='margin: 20px;'>";
      echo "<a href='" . GLPI_PLUGIN_DOC_DIR . "/qualityaudit/reports/$safe_filename' target='_blank' class='vsubmit'>";
      echo "<i class='fas fa-download'></i> " . __('Download Report', 'qualityaudit');
      echo "</a>";
      echo "</div>";
   } else {
      $message = __('Error generating report', 'qualityaudit');
   }
}

// Get current filters - with sanitization
$date_from = date('Y-m-01');
if (isset($_POST['date_from'])) {
   $d = DateTime::createFromFormat('Y-m-d', $_POST['date_from']);
   if ($d && $d->format('Y-m-d') === $_POST['date_from']) {
      $date_from = $_POST['date_from'];
   }
}

$date_to = date('Y-m-d');
if (isset($_POST['date_to'])) {
   $d = DateTime::createFromFormat('Y-m-d', $_POST['date_to']);
   if ($d && $d->format('Y-m-d') === $_POST['date_to']) {
      $date_to = $_POST['date_to'];
   }
}

$technician_id = isset($_POST['technician_id']) && is_numeric($_POST['technician_id']) 
   ? (int)$_POST['technician_id'] 
   : 0;

$status_filter = isset($_POST['status']) && in_array($_POST['status'], ['APROVADO', 'RECUSADO', '']) 
   ? $_POST['status'] 
   : '';

// Get statistics
include_once __DIR__ . '/../inc/report.class.php';
$stats = PluginQualityauditReport::getStats([
   'date_from' => $date_from,
   'date_to' => $date_to,
   'technician_id' => $technician_id,
   'status' => $status_filter
]);

// Get all technicians who have audits
$technicians = [];
$tech_iterator = $DB->request([
   'SELECT'   => 'technician_id',
   'DISTINCT' => true,
   'FROM'     => 'glpi_plugin_qualityaudit_audits',
   'WHERE'    => ['technician_id' => ['>', 0]]
]);
foreach ($tech_iterator as $row) {
   $user = new User();
   if ($user->getFromDB($row['technician_id'])) {
      $technicians[$row['technician_id']] = $user->getName();
   }
}

?>

<div class="box">
   <div class="box-header with-border">
      <h3 class="box-title"><?php echo __('Quality Audit Reports', 'qualityaudit'); ?></h3>
   </div>
   
   <?php if ($message): ?>
   <div class="box-body">
      <div class="alert alert-success">
         <?php echo $message; ?>
      </div>
   </div>
   <?php endif; ?>
   
   <!-- Filters -->
   <div class="box-body">
      <form method="post" action="">
         <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
         
         <div class="row">
            <div class="col-md-3">
               <div class="form-group">
                  <label><?php echo __('Date From', 'qualityaudit'); ?></label>
                  <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
               </div>
            </div>
            
            <div class="col-md-3">
               <div class="form-group">
                  <label><?php echo __('Date To', 'qualityaudit'); ?></label>
                  <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
               </div>
            </div>
            
            <div class="col-md-3">
               <div class="form-group">
                  <label><?php echo __('Technician', 'qualityaudit'); ?></label>
                  <select name="technician_id" class="form-control">
                     <option value="0"><?php echo __('All Technicians', 'qualityaudit'); ?></option>
                     <?php foreach ($technicians as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo $technician_id == $id ? 'selected' : ''; ?>>
                           <?php echo htmlspecialchars($name); ?>
                        </option>
                     <?php endforeach; ?>
                  </select>
               </div>
            </div>
            
            <div class="col-md-3">
               <div class="form-group">
                  <label><?php echo __('Status', 'qualityaudit'); ?></label>
                  <select name="status" class="form-control">
                     <option value=""><?php echo __('All', 'qualityaudit'); ?></option>
                     <option value="APROVADO" <?php echo $status_filter == 'APROVADO' ? 'selected' : ''; ?>>
                        <?php echo __('Approved', 'qualityaudit'); ?>
                     </option>
                     <option value="RECUSADO" <?php echo $status_filter == 'RECUSADO' ? 'selected' : ''; ?>>
                        <?php echo __('Refused', 'qualityaudit'); ?>
                     </option>
                  </select>
               </div>
            </div>
         </div>
         
         <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
               <button type="submit" name="generate_report" value="1" class="btn btn-primary">
                  <i class="fas fa-file-pdf"></i> <?php echo __('Generate PDF Report', 'qualityaudit'); ?>
               </button>
            </div>
         </div>
      </form>
   </div>
   
   <!-- Statistics -->
   <div class="box-body">
      <h4><?php echo __('Current Period Statistics', 'qualityaudit'); ?></h4>
      
      <div class="row">
         <div class="col-md-3">
            <div class="card card-default">
               <div class="card-body text-center">
                  <h3><?php echo (int)$stats['total']; ?></h3>
                  <p><?php echo __('Total Audits', 'qualityaudit'); ?></p>
               </div>
            </div>
         </div>
         
         <div class="col-md-3">
            <div class="card card-success">
               <div class="card-body text-center">
                  <h3><?php echo (int)$stats['approved']; ?></h3>
                  <p><?php echo __('Approved', 'qualityaudit'); ?></p>
                  <small><?php echo $stats['approval_rate']; ?>%</small>
               </div>
            </div>
         </div>
         
         <div class="col-md-3">
            <div class="card card-danger">
               <div class="card-body text-center">
                  <h3><?php echo (int)$stats['refused']; ?></h3>
                  <p><?php echo __('Refused', 'qualityaudit'); ?></p>
                  <small><?php echo 100 - $stats['approval_rate']; ?>%</small>
               </div>
            </div>
         </div>
         
         <div class="col-md-3">
            <div class="card card-info">
               <div class="card-body text-center">
                  <h3><?php echo (int)$stats['avg_score']; ?>/100</h3>
                  <p><?php echo __('Average Score', 'qualityaudit'); ?></p>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<?php Html::footer(); ?>
