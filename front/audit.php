<?php
/**
 * Audits list page for Quality Audit
 */


Session::checkRight('ticket', READ);

Html::header(__('Quality Audit Audits', 'qualityaudit'), $_SERVER['PHP_SELF'], 'tools', 'pluginqualityauditmenu');

global $DB;

// Pagination - with sanitization
$start = isset($_GET['start']) && is_numeric($_GET['start']) ? (int)$_GET['start'] : 0;
$limit = 20;

// Get filters - with sanitization
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['APROVADO', 'RECUSADO', '']) 
   ? $_GET['status'] 
   : '';

$technician_filter = isset($_GET['technician_id']) && is_numeric($_GET['technician_id']) 
   ? (int)$_GET['technician_id'] 
   : 0;

// Build WHERE criteria for $DB->request()
$criteria_where = [];

if (!empty($status_filter)) {
   $criteria_where['status'] = $status_filter;
}

if (!empty($technician_filter)) {
   $criteria_where['technician_id'] = $technician_filter;
}

// Get total count
$count_iterator = $DB->request([
   'COUNT'  => 'cpt',
   'FROM'   => 'glpi_plugin_qualityaudit_audits',
   'WHERE'  => $criteria_where
]);
$total = $count_iterator->current()['cpt'] ?? 0;

// Get audits
$audits = [];
$iterator = $DB->request([
   'FROM'   => 'glpi_plugin_qualityaudit_audits',
   'WHERE'  => $criteria_where,
   'ORDER'  => 'date_creation DESC',
   'START'  => $start,
   'LIMIT'  => $limit
]);
foreach ($iterator as $row) {
   $audits[] = $row;
}

// Get technicians for filter
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
      <h3 class="box-title"><?php echo __('Quality Audit Audits', 'qualityaudit'); ?></h3>
      
      <!-- Filters -->
      <div class="box-tools pull-right">
         <form method="get" action="" class="form-inline">
            <select name="status" class="form-control input-sm">
               <option value=""><?php echo __('All Status', 'qualityaudit'); ?></option>
               <option value="APROVADO" <?php echo $status_filter == 'APROVADO' ? 'selected' : ''; ?>>
                  <?php echo __('Approved', 'qualityaudit'); ?>
               </option>
               <option value="RECUSADO" <?php echo $status_filter == 'RECUSADO' ? 'selected' : ''; ?>>
                  <?php echo __('Refused', 'qualityaudit'); ?>
               </option>
            </select>
            
            <select name="technician_id" class="form-control input-sm">
               <option value="0"><?php echo __('All Technicians', 'qualityaudit'); ?></option>
               <?php foreach ($technicians as $id => $name): ?>
                  <option value="<?php echo $id; ?>" <?php echo $technician_filter == $id ? 'selected' : ''; ?>>
                     <?php echo htmlspecialchars($name); ?>
                  </option>
               <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-sm btn-default">
               <i class="fas fa-filter"></i> <?php echo __('Filter', 'qualityaudit'); ?>
            </button>
         </form>
      </div>
   </div>
   
   <div class="box-body no-padding">
      <table class="table table-striped">
         <thead>
            <tr>
               <th><?php echo __('ID', 'qualityaudit'); ?></th>
               <th><?php echo __('Date', 'qualityaudit'); ?></th>
               <th><?php echo __('Ticket', 'qualityaudit'); ?></th>
               <th><?php echo __('Type', 'qualityaudit'); ?></th>
               <th><?php echo __('Technician', 'qualityaudit'); ?></th>
               <th><?php echo __('Score', 'qualityaudit'); ?></th>
               <th><?php echo __('Status', 'qualityaudit'); ?></th>
               <th><?php echo __('Analysis', 'qualityaudit'); ?></th>
            </tr>
         </thead>
         <tbody>
            <?php foreach ($audits as $audit): ?>
               <?php 
               $user = new User();
               $tech_name = $user->getFromDB($audit['technician_id']) ? $user->getName() : 'N/A';
               ?>
               <tr>
                  <td><?php echo $audit['id']; ?></td>
                  <td><?php echo Html::convDateTime($audit['date_creation']); ?></td>
                  <td>
                     <a href="<?php echo Ticket::getFormURLWithID($audit['ticket_id']); ?>" target="_blank">
                        #<?php echo $audit['ticket_id']; ?>
                     </a>
                  </td>
                  <td><?php echo htmlspecialchars($audit['ticket_type']); ?></td>
                  <td><?php echo htmlspecialchars($tech_name); ?></td>
                  <td>
                     <strong><?php echo $audit['score']; ?>/100</strong>
                  </td>
                  <td>
                     <?php if ($audit['status'] === 'APROVADO'): ?>
                        <span class="badge bg-success">✓ <?php echo __('Approved', 'qualityaudit'); ?></span>
                     <?php else: ?>
                        <span class="badge bg-danger">✗ <?php echo __('Refused', 'qualityaudit'); ?></span>
                     <?php endif; ?>
                  </td>
                  <td>
                     <?php if (!empty($audit['analysis'])): ?>
                        <button class="btn btn-xs btn-info qa-analysis-btn"
                                data-audit-id="<?php echo (int)$audit['id']; ?>"
                                data-score="<?php echo (int)$audit['score']; ?>"
                                data-status="<?php echo htmlspecialchars($audit['status']); ?>"
                                data-analysis="<?php echo htmlspecialchars($audit['analysis']); ?>"
                                data-suggestion="<?php echo htmlspecialchars($audit['improvement_suggestion'] ?? ''); ?>"
                                data-criteria="<?php echo htmlspecialchars($audit['criteria_scores'] ?? '{}'); ?>">
                           <i class="fas fa-eye"></i>
                        </button>
                     <?php endif; ?>
                  </td>
               </tr>
            <?php endforeach; ?>
            
            <?php if (empty($audits)): ?>
               <tr>
                  <td colspan="8" class="text-center text-muted">
                     <?php echo __('No audits found', 'qualityaudit'); ?>
                  </td>
               </tr>
            <?php endif; ?>
         </tbody>
      </table>
   </div>
   
   <!-- Pagination -->
   <?php if ($total > $limit): ?>
   <div class="box-footer clearfix">
      <?php
      $prev_start = max(0, $start - $limit);
      $next_start = $start + $limit;
      ?>
      <ul class="pagination pagination-sm no-margin pull-right">
         <?php if ($start > 0): ?>
            <li><a href="?start=<?php echo $prev_start; ?>&status=<?php echo htmlspecialchars($status_filter); ?>&technician_id=<?php echo (int)$technician_filter; ?>">«</a></li>
         <?php endif; ?>
         
         <li class="active"><a href="#"><?php echo ($start / $limit) + 1; ?> / <?php echo ceil($total / $limit); ?></a></li>
         
         <?php if ($next_start < $total): ?>
            <li><a href="?start=<?php echo $next_start; ?>&status=<?php echo htmlspecialchars($status_filter); ?>&technician_id=<?php echo (int)$technician_filter; ?>">»</a></li>
         <?php endif; ?>
      </ul>
   </div>
   <?php endif; ?>
</div>

<!-- Analysis Modal -->
<div id="qa-analysis-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5);">
   <div style="background:#fff; border-radius:10px; max-width:600px; width:90%; margin:60px auto; max-height:80vh; overflow-y:auto; box-shadow:0 8px 30px rgba(0,0,0,0.25);">
      <div id="qa-modal-header" style="padding:16px 24px; border-bottom:1px solid #e9ecef; display:flex; align-items:center; justify-content:space-between;">
         <h4 style="margin:0; font-size:16px; font-weight:700; color:#333;">
            <i class="fas fa-clipboard-check"></i> <?php echo __('Audit Analysis', 'qualityaudit'); ?>
            <span id="qa-modal-id" style="color:#6c757d; font-weight:400;"></span>
         </h4>
         <button id="qa-modal-close" style="background:none; border:none; font-size:20px; cursor:pointer; color:#6c757d; padding:4px;">&times;</button>
      </div>
      <div style="padding:24px;">
         <!-- Score -->
         <div style="text-align:center; margin-bottom:20px;">
            <div id="qa-modal-score" style="font-size:48px; font-weight:700; line-height:1;"></div>
            <div id="qa-modal-status" style="margin-top:6px;"></div>
         </div>
         <!-- Criteria -->
         <div id="qa-modal-criteria" style="margin-bottom:20px;"></div>
         <!-- Analysis -->
         <div style="margin-bottom:16px;">
            <h5 style="font-size:13px; font-weight:700; text-transform:uppercase; color:#6c757d; margin:0 0 8px;"><?php echo __('Analysis', 'qualityaudit'); ?></h5>
            <div id="qa-modal-analysis" style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:12px; font-size:13px; line-height:1.6; color:#333;"></div>
         </div>
         <!-- Suggestion -->
         <div id="qa-modal-suggestion-wrap" style="display:none;">
            <h5 style="font-size:13px; font-weight:700; text-transform:uppercase; color:#6c757d; margin:0 0 8px;"><?php echo __('Improvement Suggestion', 'qualityaudit'); ?></h5>
            <div id="qa-modal-suggestion" style="background:#fff3cd; border:1px solid #ffeeba; border-radius:6px; padding:12px; font-size:13px; line-height:1.6; color:#856404;"></div>
         </div>
      </div>
   </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
   var modal = document.getElementById('qa-analysis-modal');

   // Close modal
   document.getElementById('qa-modal-close').addEventListener('click', function() {
      modal.style.display = 'none';
   });
   modal.addEventListener('click', function(e) {
      if (e.target === modal) modal.style.display = 'none';
   });
   document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') modal.style.display = 'none';
   });

   // Open modal on button click
   document.querySelectorAll('.qa-analysis-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
         var score = parseInt(this.dataset.score);
         var status = this.dataset.status;
         var analysis = this.dataset.analysis;
         var suggestion = this.dataset.suggestion;
         var criteria = {};
         try { criteria = JSON.parse(this.dataset.criteria); } catch(e) {}

         // ID
         document.getElementById('qa-modal-id').textContent = ' #' + this.dataset.auditId;

         // Score color
         var scoreEl = document.getElementById('qa-modal-score');
         scoreEl.textContent = score + '/100';
         scoreEl.style.color = score >= 80 ? '#28a745' : (score >= 60 ? '#ffc107' : '#dc3545');

         // Status badge
         var statusEl = document.getElementById('qa-modal-status');
         if (status === 'APROVADO') {
            statusEl.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 14px;border-radius:14px;background:#d4edda;color:#155724;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?php echo __("Approved", "qualityaudit"); ?></span>';
         } else {
            statusEl.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 14px;border-radius:14px;background:#f8d7da;color:#721c24;font-size:13px;font-weight:600;"><i class="fas fa-times-circle"></i> <?php echo __("Refused", "qualityaudit"); ?></span>';
         }

         // Criteria bars
         var criteriaEl = document.getElementById('qa-modal-criteria');
         var labels = {
            ortografia: {name: '<?php echo __("Spelling/Grammar", "qualityaudit"); ?>', max: 20},
            completude: {name: '<?php echo __("Completeness", "qualityaudit"); ?>', max: 30},
            resolucao:  {name: '<?php echo __("Resolution", "qualityaudit"); ?>', max: 25},
            clareza:    {name: '<?php echo __("Clarity/Tone", "qualityaudit"); ?>', max: 15},
            tecnica:    {name: '<?php echo __("Technical", "qualityaudit"); ?>', max: 10}
         };
         var html = '';
         var hasAny = false;
         for (var key in labels) {
            var val = criteria[key] ?? 0;
            if (val > 0) hasAny = true;
            var pct = Math.round((val / labels[key].max) * 100);
            var color = pct >= 80 ? '#28a745' : (pct >= 60 ? '#ffc107' : '#dc3545');
            html += '<div style="margin-bottom:8px;">';
            html += '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">';
            html += '<span style="color:#495057;font-weight:600;">' + labels[key].name + '</span>';
            html += '<span style="color:#333;font-weight:700;">' + val + '/' + labels[key].max + '</span>';
            html += '</div>';
            html += '<div style="height:6px;border-radius:3px;background:#e9ecef;overflow:hidden;">';
            html += '<div style="height:100%;width:' + pct + '%;background:' + color + ';border-radius:3px;transition:width 0.4s;"></div>';
            html += '</div></div>';
         }
         criteriaEl.innerHTML = hasAny ? html : '';

         // Analysis
         document.getElementById('qa-modal-analysis').textContent = analysis;

         // Suggestion
         var sugWrap = document.getElementById('qa-modal-suggestion-wrap');
         if (suggestion && suggestion.trim()) {
            document.getElementById('qa-modal-suggestion').textContent = suggestion;
            sugWrap.style.display = 'block';
         } else {
            sugWrap.style.display = 'none';
         }

         modal.style.display = 'block';
      });
   });
});
</script>

<?php Html::footer(); ?>
