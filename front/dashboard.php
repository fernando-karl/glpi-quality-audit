<?php
/**
 * Dashboard page - Modern UI with ApexCharts
 */


Session::checkRight('ticket', READ);

Html::header(__('Quality Audit Dashboard', 'qualityaudit'), $_SERVER['PHP_SELF'], 'tools', 'pluginqualityauditmenu');

global $DB, $CFG_GLPI;

// Load dashboard CSS (use /plugins/ path - marketplace path returns 404 in GLPi 11)
$root_doc = $CFG_GLPI['root_doc'] ?? '';
echo '<link rel="stylesheet" href="' . $root_doc . '/plugins/qualityaudit/css/dashboard.css">';

// Group filter
$groups_id = isset($_GET['groups_id']) ? (int)$_GET['groups_id'] : 0;

// Get groups that have at least one technician with audits
$group_options = [];
$group_iter = $DB->request([
   'SELECT'          => ['gu.groups_id'],
   'DISTINCT'        => true,
   'FROM'            => 'glpi_plugin_qualityaudit_audits AS a',
   'INNER JOIN'      => [
      'glpi_groups_users AS gu' => [
         'ON' => [
            'a'  => 'technician_id',
            'gu' => 'users_id'
         ]
      ]
   ],
   'WHERE'           => ['a.technician_id' => ['>', 0]]
]);
foreach ($group_iter as $grow) {
   $group = new Group();
   if ($group->getFromDB($grow['groups_id'])) {
      $group_options[$grow['groups_id']] = $group->getName();
   }
}
asort($group_options);

// Build WHERE criteria for filtering by group
$where_criteria = [];
$tech_ids = [];
$selected_group_name = '';
if ($groups_id > 0 && isset($group_options[$groups_id])) {
   $selected_group_name = $group_options[$groups_id];
   $tech_iter = $DB->request([
      'SELECT' => ['users_id'],
      'FROM'   => 'glpi_groups_users',
      'WHERE'  => ['groups_id' => $groups_id]
   ]);
   foreach ($tech_iter as $trow) {
      $tech_ids[] = (int)$trow['users_id'];
   }
   if (!empty($tech_ids)) {
      $where_criteria = ['technician_id' => $tech_ids];
   } else {
      $where_criteria = ['technician_id' => -1];
   }
}

// ========== Statistics ==========

$stats = [];
$stats['total'] = countElementsInTable('glpi_plugin_qualityaudit_audits', $where_criteria);
$stats['approved'] = countElementsInTable('glpi_plugin_qualityaudit_audits', array_merge(['status' => 'APROVADO'], $where_criteria));
$stats['refused'] = countElementsInTable('glpi_plugin_qualityaudit_audits', array_merge(['status' => 'RECUSADO'], $where_criteria));

$avg_query = [
   'SELECT' => ['AVG' => 'score AS avg_score'],
   'FROM'   => 'glpi_plugin_qualityaudit_audits'
];
if (!empty($where_criteria)) {
   $avg_query['WHERE'] = $where_criteria;
}
$result = $DB->request($avg_query);
$stats['avg_score'] = round($result->current()['avg_score'] ?? 0);

$approved_pct = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0;
$refused_pct = $stats['total'] > 0 ? round(($stats['refused'] / $stats['total']) * 100) : 0;

// Score distribution
$score_distribution = ['0-39' => 0, '40-59' => 0, '60-79' => 0, '80-100' => 0];
$score_query = ['SELECT' => ['score'], 'FROM' => 'glpi_plugin_qualityaudit_audits'];
if (!empty($where_criteria)) {
   $score_query['WHERE'] = $where_criteria;
}
foreach ($DB->request($score_query) as $row) {
   $score = (int)$row['score'];
   if ($score < 40) $score_distribution['0-39']++;
   elseif ($score < 60) $score_distribution['40-59']++;
   elseif ($score < 80) $score_distribution['60-79']++;
   else $score_distribution['80-100']++;
}

// Audits per day (last 30 days) for trend chart
$daily_data = [];
$daily_query = [
   'SELECT' => [
      new QueryExpression("DATE(date_creation) AS audit_date"),
      'COUNT' => '* AS total',
      new QueryExpression("SUM(CASE WHEN status = 'APROVADO' THEN 1 ELSE 0 END) AS approved"),
      new QueryExpression("SUM(CASE WHEN status = 'RECUSADO' THEN 1 ELSE 0 END) AS refused"),
      new QueryExpression("ROUND(AVG(score)) AS avg_score")
   ],
   'FROM'   => 'glpi_plugin_qualityaudit_audits',
   'WHERE'  => array_merge(
      ['date_creation' => ['>=', date('Y-m-d', strtotime('-30 days'))]],
      $where_criteria
   ),
   'GROUPBY' => new QueryExpression("DATE(date_creation)"),
   'ORDER'   => 'audit_date ASC'
];
foreach ($DB->request($daily_query) as $row) {
   $daily_data[] = $row;
}

// Criteria averages
$criteria_avgs = ['ortografia' => 0, 'completude' => 0, 'resolucao' => 0, 'clareza' => 0, 'tecnica' => 0];
$criteria_query = ['SELECT' => ['criteria_scores'], 'FROM' => 'glpi_plugin_qualityaudit_audits'];
if (!empty($where_criteria)) {
   $criteria_query['WHERE'] = $where_criteria;
}
$criteria_count = 0;
foreach ($DB->request($criteria_query) as $row) {
   $c = json_decode($row['criteria_scores'] ?? '{}', true);
   if (!empty($c)) {
      $criteria_count++;
      foreach ($criteria_avgs as $key => &$val) {
         $val += ($c[$key] ?? 0);
      }
      unset($val);
   }
}
if ($criteria_count > 0) {
   foreach ($criteria_avgs as &$val) {
      $val = round($val / $criteria_count, 1);
   }
   unset($val);
}

// Top 5 technicians
$top_technicians = [];
$tech_where = !empty($where_criteria) ? ['technician_id' => $tech_ids] : ['technician_id' => ['>', 0]];
$iterator = $DB->request([
   'SELECT' => [
      'technician_id',
      'AVG' => 'score AS avg_score',
      'COUNT' => '* AS total_audits'
   ],
   'FROM'    => 'glpi_plugin_qualityaudit_audits',
   'WHERE'   => $tech_where,
   'GROUPBY' => 'technician_id',
   'ORDERBY' => 'avg_score DESC',
   'LIMIT'   => 5
]);
foreach ($iterator as $row) {
   $user = new User();
   $user->getFromDB($row['technician_id']);
   $top_technicians[] = [
      'name' => $user->getName(),
      'avg_score' => round($row['avg_score']),
      'total' => $row['total_audits']
   ];
}

// Recent audits
$recent_audits = [];
$recent_query = [
   'FROM'  => 'glpi_plugin_qualityaudit_audits',
   'ORDER' => 'date_creation DESC',
   'LIMIT' => 10
];
if (!empty($where_criteria)) {
   $recent_query['WHERE'] = $where_criteria;
}
foreach ($DB->request($recent_query) as $row) {
   $recent_audits[] = $row;
}

// Prepare JSON data for charts
$chart_dates = array_map(function($d) { return $d['audit_date']; }, $daily_data);
$chart_approved = array_map(function($d) { return (int)$d['approved']; }, $daily_data);
$chart_refused = array_map(function($d) { return (int)$d['refused']; }, $daily_data);
$chart_avg = array_map(function($d) { return (int)$d['avg_score']; }, $daily_data);

?>

<div class="qa-dashboard-wrapper">

   <!-- Header -->
   <div class="qa-dashboard-header">
      <h2>
         <i class="fas fa-clipboard-check"></i>
         <?php echo __('Quality Audit Dashboard', 'qualityaudit'); ?>
      </h2>
      <div class="qa-dashboard-actions">
         <a href="reports.php" class="qa-btn-report">
            <i class="fas fa-file-pdf"></i> <?php echo __('Generate Report', 'qualityaudit'); ?>
         </a>
      </div>
   </div>

   <?php if (!empty($group_options)): ?>
   <!-- Group Filter -->
   <div class="qa-filter-bar">
      <form method="GET" action="dashboard.php" class="d-flex align-items-center" style="gap: 10px; width: 100%;">
         <label for="groups_id">
            <i class="fas fa-users"></i> <?php echo __('Filter by Group', 'qualityaudit'); ?>
         </label>
         <select name="groups_id" id="groups_id" class="form-select" style="max-width: 280px;" onchange="this.form.submit()">
            <option value="0"><?php echo __('All Groups', 'qualityaudit'); ?></option>
            <?php foreach ($group_options as $gid => $gname): ?>
               <option value="<?php echo (int)$gid; ?>" <?php echo ($groups_id == $gid) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($gname); ?>
               </option>
            <?php endforeach; ?>
         </select>
         <?php if ($groups_id > 0 && !empty($selected_group_name)): ?>
            <span class="qa-filter-badge">
               <i class="fas fa-filter"></i> <?php echo htmlspecialchars($selected_group_name); ?>
            </span>
         <?php endif; ?>
      </form>
   </div>
   <?php endif; ?>

   <!-- KPI Cards -->
   <div class="qa-kpi-grid">
      <div class="qa-kpi-card kpi-total">
         <div class="qa-kpi-icon"><i class="fas fa-clipboard-list"></i></div>
         <div class="qa-kpi-label"><?php echo __('Total Audits', 'qualityaudit'); ?></div>
         <div class="qa-kpi-value"><?php echo (int)$stats['total']; ?></div>
      </div>

      <div class="qa-kpi-card kpi-approved">
         <div class="qa-kpi-icon"><i class="fas fa-check-circle"></i></div>
         <div class="qa-kpi-label"><?php echo __('Approved', 'qualityaudit'); ?></div>
         <div class="qa-kpi-value"><?php echo (int)$stats['approved']; ?></div>
         <div class="qa-kpi-sub">
            <span class="text-success"><?php echo $approved_pct; ?>%</span> <?php echo __('of total', 'qualityaudit'); ?>
         </div>
      </div>

      <div class="qa-kpi-card kpi-refused">
         <div class="qa-kpi-icon"><i class="fas fa-times-circle"></i></div>
         <div class="qa-kpi-label"><?php echo __('Refused', 'qualityaudit'); ?></div>
         <div class="qa-kpi-value"><?php echo (int)$stats['refused']; ?></div>
         <div class="qa-kpi-sub">
            <span class="text-danger"><?php echo $refused_pct; ?>%</span> <?php echo __('of total', 'qualityaudit'); ?>
         </div>
      </div>

      <div class="qa-kpi-card kpi-avg">
         <div class="qa-kpi-icon"><i class="fas fa-chart-line"></i></div>
         <div class="qa-kpi-label"><?php echo __('Average Score', 'qualityaudit'); ?></div>
         <div class="qa-kpi-value"><?php echo (int)$stats['avg_score']; ?><span style="font-size: 16px; font-weight: 400; color: #999;">/100</span></div>
      </div>
   </div>

   <!-- Charts Row -->
   <div class="qa-chart-grid">

      <!-- Trend Chart (30 days) -->
      <div class="qa-chart-card">
         <div class="qa-chart-title">
            <i class="fas fa-chart-area"></i> <?php echo __('Audit Trend (30 days)', 'qualityaudit'); ?>
         </div>
         <div id="qa-trend-chart" style="height: 280px;"></div>
         <?php if (empty($daily_data)): ?>
            <div class="qa-empty">
               <i class="fas fa-chart-area"></i>
               <span><?php echo __('No data for the last 30 days', 'qualityaudit'); ?></span>
            </div>
         <?php endif; ?>
      </div>

      <!-- Approval Donut -->
      <div class="qa-chart-card">
         <div class="qa-chart-title">
            <i class="fas fa-chart-pie"></i> <?php echo __('Approval Rate', 'qualityaudit'); ?>
         </div>
         <div id="qa-donut-chart" style="height: 280px;"></div>
         <?php if ($stats['total'] == 0): ?>
            <div class="qa-empty">
               <i class="fas fa-chart-pie"></i>
               <span><?php echo __('No audits yet', 'qualityaudit'); ?></span>
            </div>
         <?php endif; ?>
      </div>

      <!-- Score Distribution Bar -->
      <div class="qa-chart-card">
         <div class="qa-chart-title">
            <i class="fas fa-chart-bar"></i> <?php echo __('Score Distribution', 'qualityaudit'); ?>
         </div>
         <div id="qa-distribution-chart" style="height: 280px;"></div>
      </div>

      <!-- Criteria Radar -->
      <div class="qa-chart-card">
         <div class="qa-chart-title">
            <i class="fas fa-bullseye"></i> <?php echo __('Average by Criteria', 'qualityaudit'); ?>
         </div>
         <div id="qa-criteria-chart" style="height: 280px;"></div>
         <?php if ($criteria_count == 0): ?>
            <div class="qa-empty">
               <i class="fas fa-bullseye"></i>
               <span><?php echo __('No criteria data yet', 'qualityaudit'); ?></span>
            </div>
         <?php endif; ?>
      </div>

   </div>

   <!-- Tables Row -->
   <div class="qa-table-grid">

      <!-- Top Technicians -->
      <div class="qa-table-card">
         <div class="qa-table-header">
            <h3><i class="fas fa-trophy"></i> <?php echo __('Top 5 Technicians', 'qualityaudit'); ?></h3>
         </div>
         <?php if (!empty($top_technicians)): ?>
         <table class="qa-data-table">
            <thead>
               <tr>
                  <th>#</th>
                  <th><?php echo __('Technician'); ?></th>
                  <th><?php echo __('Avg Score'); ?></th>
                  <th><?php echo __('Audits'); ?></th>
               </tr>
            </thead>
            <tbody>
            <?php foreach ($top_technicians as $i => $tech): ?>
               <?php
                  $rank_class = ($i === 0) ? 'rank-1' : (($i === 1) ? 'rank-2' : (($i === 2) ? 'rank-3' : 'rank-default'));
                  $bar_class = $tech['avg_score'] >= 80 ? 'high' : ($tech['avg_score'] >= 60 ? 'medium' : 'low');
               ?>
               <tr>
                  <td><span class="qa-rank <?php echo $rank_class; ?>"><?php echo ($i + 1); ?></span></td>
                  <td><?php echo htmlspecialchars($tech['name']); ?></td>
                  <td>
                     <div class="qa-mini-bar">
                        <div class="qa-mini-bar-track">
                           <div class="qa-mini-bar-fill <?php echo $bar_class; ?>" style="width: <?php echo $tech['avg_score']; ?>%;"></div>
                        </div>
                        <span class="qa-mini-bar-value"><?php echo (int)$tech['avg_score']; ?></span>
                     </div>
                  </td>
                  <td><?php echo (int)$tech['total']; ?></td>
               </tr>
            <?php endforeach; ?>
            </tbody>
         </table>
         <?php else: ?>
         <div class="qa-empty">
            <i class="fas fa-users"></i>
            <span><?php echo __('No data yet', 'qualityaudit'); ?></span>
         </div>
         <?php endif; ?>
      </div>

      <!-- Recent Audits -->
      <div class="qa-table-card">
         <div class="qa-table-header">
            <h3><i class="fas fa-clock"></i> <?php echo __('Recent Audits', 'qualityaudit'); ?></h3>
         </div>
         <?php if (!empty($recent_audits)): ?>
         <table class="qa-data-table">
            <thead>
               <tr>
                  <th><?php echo __('Ticket'); ?></th>
                  <th><?php echo __('Type'); ?></th>
                  <th><?php echo __('Score'); ?></th>
                  <th><?php echo __('Status'); ?></th>
                  <th><?php echo __('Date'); ?></th>
               </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_audits as $audit): ?>
               <?php
                  $score = (int)$audit['score'];
                  $badge_class = $audit['status'] === 'APROVADO' ? 'approved' : ($score >= 60 ? 'warning' : 'refused');
               ?>
               <tr>
                  <td>
                     <a href="<?php echo Ticket::getFormURLWithID($audit['ticket_id']); ?>">
                        #<?php echo (int)$audit['ticket_id']; ?>
                     </a>
                  </td>
                  <td><?php echo htmlspecialchars($audit['ticket_type']); ?></td>
                  <td><strong><?php echo $score; ?></strong>/100</td>
                  <td>
                     <?php if ($audit['status'] === 'APROVADO'): ?>
                        <span class="qa-score-badge approved">
                           <i class="fas fa-check"></i> <?php echo __('Approved'); ?>
                        </span>
                     <?php else: ?>
                        <span class="qa-score-badge refused">
                           <i class="fas fa-times"></i> <?php echo __('Refused'); ?>
                        </span>
                     <?php endif; ?>
                  </td>
                  <td style="white-space: nowrap;"><?php echo Html::convDateTime($audit['date_creation']); ?></td>
               </tr>
            <?php endforeach; ?>
            </tbody>
         </table>
         <?php else: ?>
         <div class="qa-empty">
            <i class="fas fa-inbox"></i>
            <span><?php echo __('No audits yet', 'qualityaudit'); ?></span>
         </div>
         <?php endif; ?>
      </div>

   </div>

</div>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

   var chartColors = {
      primary: '#007bff',
      success: '#28a745',
      danger: '#dc3545',
      warning: '#ffc107',
      purple: '#6f42c1',
      info: '#17a2b8',
      gray: '#adb5bd'
   };

   // ========== 1. Trend Chart (Area) ==========
   <?php if (!empty($daily_data)): ?>
   var trendOptions = {
      chart: {
         type: 'area',
         height: 280,
         toolbar: { show: false },
         fontFamily: 'inherit',
         zoom: { enabled: false }
      },
      series: [
         { name: '<?php echo __("Approved", "qualityaudit"); ?>', data: <?php echo json_encode($chart_approved); ?> },
         { name: '<?php echo __("Refused", "qualityaudit"); ?>', data: <?php echo json_encode($chart_refused); ?> }
      ],
      xaxis: {
         categories: <?php echo json_encode($chart_dates); ?>,
         labels: {
            style: { fontSize: '11px', colors: '#6c757d' },
            formatter: function(val) {
               if (!val) return '';
               var d = new Date(val + 'T00:00:00');
               return d.getDate() + '/' + (d.getMonth() + 1);
            }
         },
         axisBorder: { show: false },
         axisTicks: { show: false }
      },
      yaxis: {
         labels: { style: { fontSize: '11px', colors: '#6c757d' } },
         forceNiceScale: true,
         min: 0
      },
      colors: [chartColors.success, chartColors.danger],
      fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      grid: { borderColor: '#f0f0f0', strokeDashArray: 3 },
      tooltip: {
         x: { format: 'dd/MM/yyyy' },
         theme: 'light'
      },
      legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' }
   };
   new ApexCharts(document.querySelector('#qa-trend-chart'), trendOptions).render();
   <?php endif; ?>

   // ========== 2. Donut Chart ==========
   <?php if ($stats['total'] > 0): ?>
   var donutOptions = {
      chart: {
         type: 'donut',
         height: 280,
         fontFamily: 'inherit'
      },
      series: [<?php echo (int)$stats['approved']; ?>, <?php echo (int)$stats['refused']; ?>],
      labels: ['<?php echo __("Approved", "qualityaudit"); ?>', '<?php echo __("Refused", "qualityaudit"); ?>'],
      colors: [chartColors.success, chartColors.danger],
      plotOptions: {
         pie: {
            donut: {
               size: '65%',
               labels: {
                  show: true,
                  name: { show: true, fontSize: '14px', color: '#333' },
                  value: { show: true, fontSize: '22px', fontWeight: 700, color: '#333' },
                  total: {
                     show: true,
                     label: '<?php echo __("Total", "qualityaudit"); ?>',
                     fontSize: '13px',
                     color: '#6c757d',
                     formatter: function(w) {
                        return w.globals.seriesTotals.reduce(function(a, b) { return a + b; }, 0);
                     }
                  }
               }
            }
         }
      },
      dataLabels: { enabled: false },
      legend: { position: 'bottom', fontSize: '13px' },
      stroke: { width: 2 }
   };
   new ApexCharts(document.querySelector('#qa-donut-chart'), donutOptions).render();
   <?php endif; ?>

   // ========== 3. Score Distribution Bar ==========
   var distOptions = {
      chart: {
         type: 'bar',
         height: 280,
         toolbar: { show: false },
         fontFamily: 'inherit'
      },
      series: [{
         name: '<?php echo __("Audits", "qualityaudit"); ?>',
         data: [
            <?php echo $score_distribution['0-39']; ?>,
            <?php echo $score_distribution['40-59']; ?>,
            <?php echo $score_distribution['60-79']; ?>,
            <?php echo $score_distribution['80-100']; ?>
         ]
      }],
      xaxis: {
         categories: ['0-39', '40-59', '60-79', '80-100'],
         labels: { style: { fontSize: '12px', fontWeight: 600 } }
      },
      yaxis: {
         labels: { style: { fontSize: '11px', colors: '#6c757d' } },
         forceNiceScale: true,
         min: 0
      },
      colors: [chartColors.danger, chartColors.warning, chartColors.info, chartColors.success],
      plotOptions: {
         bar: {
            borderRadius: 6,
            columnWidth: '55%',
            distributed: true
         }
      },
      dataLabels: {
         enabled: true,
         style: { fontSize: '13px', fontWeight: 700, colors: ['#fff'] },
         offsetY: -2
      },
      grid: { borderColor: '#f0f0f0', strokeDashArray: 3 },
      legend: { show: false },
      tooltip: { theme: 'light' }
   };
   new ApexCharts(document.querySelector('#qa-distribution-chart'), distOptions).render();

   // ========== 4. Criteria Radar ==========
   <?php if ($criteria_count > 0): ?>
   var radarOptions = {
      chart: {
         type: 'radar',
         height: 280,
         toolbar: { show: false },
         fontFamily: 'inherit'
      },
      series: [{
         name: '<?php echo __("Average", "qualityaudit"); ?>',
         data: [
            <?php echo $criteria_avgs['ortografia']; ?>,
            <?php echo $criteria_avgs['completude']; ?>,
            <?php echo $criteria_avgs['resolucao']; ?>,
            <?php echo $criteria_avgs['clareza']; ?>,
            <?php echo $criteria_avgs['tecnica']; ?>
         ]
      }],
      xaxis: {
         categories: [
            '<?php echo __("Spelling", "qualityaudit"); ?> (20)',
            '<?php echo __("Completeness", "qualityaudit"); ?> (30)',
            '<?php echo __("Resolution", "qualityaudit"); ?> (25)',
            '<?php echo __("Clarity", "qualityaudit"); ?> (15)',
            '<?php echo __("Technical", "qualityaudit"); ?> (10)'
         ]
      },
      yaxis: { show: false },
      colors: [chartColors.purple],
      fill: { opacity: 0.25 },
      stroke: { width: 2 },
      markers: { size: 4, hover: { size: 6 } },
      dataLabels: { enabled: true, style: { fontSize: '11px' }, background: { enabled: true, borderRadius: 2 } },
      plotOptions: {
         radar: {
            polygons: {
               strokeColors: '#e9ecef',
               connectorColors: '#e9ecef',
               fill: { colors: ['#f8f9fa', '#fff'] }
            }
         }
      }
   };
   new ApexCharts(document.querySelector('#qa-criteria-chart'), radarOptions).render();
   <?php endif; ?>

});
</script>

<?php Html::footer(); ?>
