<?php
/**
 * Report generator for Quality Audit plugin
 * Generates PDF reports using TCPDF library
 */

class PluginQualityauditReport {
   
   /**
    * Generate PDF report for audits
    * 
    * @param array $filters Filter options (date_from, date_to, technician_id, status)
    * @return string PDF file path
    */
   static function generatePdfReport($filters = []) {
      global $DB;

      // Build WHERE criteria for $DB->request()
      $criteria_where = [];

      if (!empty($filters['date_from'])) {
         $criteria_where[] = ['date_creation' => ['>=', $filters['date_from']]];
      }

      if (!empty($filters['date_to'])) {
         $criteria_where[] = ['date_creation' => ['<=', $filters['date_to'] . ' 23:59:59']];
      }

      if (!empty($filters['technician_id'])) {
         $criteria_where['technician_id'] = (int)$filters['technician_id'];
      }

      if (!empty($filters['status'])) {
         $criteria_where['status'] = $filters['status'];
      }

      // Get audit data
      $audits = [];
      $iterator = $DB->request([
         'FROM'   => 'glpi_plugin_qualityaudit_audits',
         'WHERE'  => $criteria_where,
         'ORDER'  => 'date_creation DESC'
      ]);
      foreach ($iterator as $row) {
         $audits[] = $row;
      }
      
      // If TCPDF is not available, return HTML report
      if (!class_exists('TCPDF')) {
         return self::generateHtmlReport($audits, $filters);
      }
      
      return self::generatePdfWithTcpdf($audits, $filters);
   }
   
   /**
    * Generate PDF using TCPDF
    */
   static function generatePdfWithTcpdf($audits, $filters) {
      // Create PDF
      $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
      
      // Set document information
      $pdf->SetCreator('GLPI Quality Audit');
      $pdf->SetAuthor('GLPI Quality Audit Plugin');
      $pdf->SetTitle('Relatório de Auditoria de Qualidade');
      
      // Remove default header/footer
      $pdf->setPrintHeader(false);
      $pdf->setPrintFooter(false);
      
      // Set margins
      $pdf->SetMargins(15, 15, 15);
      
      // Add page
      $pdf->AddPage();
      
      // Title
      $pdf->SetFont('helvetica', 'B', 16);
      $pdf->Cell(0, 10, 'Relatório de Auditoria de Qualidade', 0, true, 'C');
      $pdf->Ln(5);
      
      // Date range
      $pdf->SetFont('helvetica', '', 10);
      $date_range = '';
      if (!empty($filters['date_from'])) {
         $date_range .= 'De: ' . $filters['date_from'];
      }
      if (!empty($filters['date_to'])) {
         $date_range .= ' Até: ' . $filters['date_to'];
      }
      if (empty($date_range)) {
         $date_range = 'Período: Todo';
      }
      $pdf->Cell(0, 10, $date_range, 0, true, 'C');
      $pdf->Ln(10);
      
      // Summary statistics
      $total = count($audits);
      $approved = count(array_filter($audits, function($a) { return $a['status'] === 'APROVADO'; }));
      $refused = $total - $approved;
      $avg_score = $total > 0 ? round(array_sum(array_column($audits, 'score')) / $total) : 0;
      
      $pdf->SetFont('helvetica', 'B', 12);
      $pdf->Cell(0, 10, 'Resumo', 0, true, 'L');
      $pdf->SetFont('helvetica', '', 10);
      
      $summary = sprintf(
         "Total de Auditorias: %d | Aprovadas: %d (%.1f%%) | Recusadas: %d (%.1f%%) | Nota Média: %d/100",
         $total, $approved, $total > 0 ? ($approved/$total*100) : 0,
         $refused, $total > 0 ? ($refused/$total*100) : 0, $avg_score
      );
      $pdf->Cell(0, 10, $summary, 0, true, 'L');
      $pdf->Ln(10);
      
      // Table header
      $pdf->SetFont('helvetica', 'B', 9);
      $pdf->SetFillColor(240, 240, 240);
      
      $header = ['#', 'Data', 'Chamado', 'Técnico', 'Nota', 'Status'];
      $widths = [15, 35, 60, 50, 20, 25];
      
      foreach ($header as $i => $col) {
         $pdf->Cell($widths[$i], 7, $col, 1, 0, 'C', true);
      }
      $pdf->Ln();
      
      // Table rows
      $pdf->SetFont('helvetica', '', 8);
      
      foreach ($audits as $i => $audit) {
         // Get technician name
         $user = new User();
         $tech_name = $user->getFromDB($audit['technician_id']) ? $user->getName() : 'N/A';
         
         // Row colors based on status
         if ($audit['status'] === 'RECUSADO') {
            $pdf->SetFillColor(255, 235, 238);
         } else {
            $pdf->SetFillColor(255, 255, 255);
         }
         
         $pdf->Cell($widths[0], 6, $i + 1, 1, 0, 'C', true);
         $pdf->Cell($widths[1], 6, Html::convDateTime($audit['date_creation']), 1, 0, 'C', true);
         $pdf->Cell($widths[2], 6, '#' . $audit['ticket_id'], 1, 0, 'C', true);
         $pdf->Cell($widths[3], 6, substr($tech_name, 0, 20), 1, 0, 'L', true);
         $pdf->Cell($widths[4], 6, $audit['score'] . '/100', 1, 0, 'C', true);
         $pdf->Cell($widths[5], 6, $audit['status'], 1, 0, 'C', true);
         $pdf->Ln();
      }
      
      // Footer
      $pdf->SetY(-20);
      $pdf->SetFont('helvetica', 'I', 8);
      $pdf->Cell(0, 10, 'Gerado em: ' . date('d/m/Y H:i:s') . ' - GLPI Quality Audit Plugin', 0, true, 'C');
      
      // Output
      $filename = 'quality_audit_report_' . date('Ymd_His') . '.pdf';
      $filepath = GLPI_PLUGIN_DOC_DIR . '/qualityaudit/reports/';
      
      // Create directory if not exists
      if (!is_dir($filepath)) {
         mkdir($filepath, 0755, true);
      }
      
      $full_path = $filepath . $filename;
      $pdf->Output($full_path, 'F');
      
      return $full_path;
   }
   
   /**
    * Generate HTML report (fallback if TCPDF not available)
    */
   static function generateHtmlReport($audits, $filters) {
      global $DB, $CFG_GLPI;
      
      // Get statistics
      $total = count($audits);
      $approved = count(array_filter($audits, function($a) { return $a['status'] === 'APROVADO'; }));
      $refused = $total - $approved;
      $avg_score = $total > 0 ? round(array_sum(array_column($audits, 'score')) / $total) : 0;
      
      $html = "
      <!DOCTYPE html>
      <html>
      <head>
         <meta charset='UTF-8'>
         <title>Relatório de Auditoria de Qualidade</title>
         <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f5f5f5; }
            .approved { color: green; }
            .refused { color: red; }
            .summary { background: #f9f9f9; padding: 15px; margin: 20px 0; }
         </style>
      </head>
      <body>
         <h1>Relatório de Auditoria de Qualidade</h1>
         <p>Período: " . ($filters['date_from'] ?? 'Início') . " até " . ($filters['date_to'] ?? 'Hoje') . "</p>
         
         <div class='summary'>
            <strong>Resumo:</strong><br>
            Total: $total | Aprovadas: $approved (" . ($total > 0 ? round($approved/$total*100) : 0) . "%) | 
            Recusadas: $refused (" . ($total > 0 ? round($refused/$total*100) : 0) . "%) | 
            Nota Média: $avg_score/100
         </div>
         
         <table>
            <thead>
               <tr>
                  <th>#</th>
                  <th>Data</th>
                  <th>Chamado</th>
                  <th>Técnico</th>
                  <th>Nota</th>
                  <th>Status</th>
               </tr>
            </thead>
            <tbody>
      ";
      
      foreach ($audits as $i => $audit) {
         $user = new User();
         $tech_name = $user->getFromDB($audit['technician_id']) ? $user->getName() : 'N/A';
         $status_class = $audit['status'] === 'APROVADO' ? 'approved' : 'refused';
         
         $html .= "
               <tr>
                  <td>" . ($i + 1) . "</td>
                  <td>" . Html::convDateTime($audit['date_creation']) . "</td>
                  <td>#{$audit['ticket_id']}</td>
                  <td>" . htmlspecialchars($tech_name) . "</td>
                  <td>{$audit['score']}/100</td>
                  <td class='$status_class'>{$audit['status']}</td>
               </tr>
         ";
      }
      
      $html .= "
            </tbody>
         </table>
         <p style='margin-top: 20px; color: #666; font-size: 12px;'>
            Gerado em: " . date('d/m/Y H:i:s') . " - GLPI Quality Audit Plugin
         </p>
      </body>
      </html>
      ";
      
      // Save HTML file
      $filepath = GLPI_PLUGIN_DOC_DIR . '/qualityaudit/reports/';
      if (!is_dir($filepath)) {
         mkdir($filepath, 0755, true);
      }
      
      $filename = 'quality_audit_report_' . date('Ymd_His') . '.html';
      if (file_put_contents($filepath . $filename, $html) === false) {
         Toolbox::logInFile('qualityaudit', "Quality Audit: Failed to write report to $filepath$filename");
         return '';
      }

      return $filepath . $filename;
   }
   
   /**
    * Get statistics for dashboard
    * 
    * @param array $filters Filter options
    * @return array Statistics
    */
   static function getStats($filters = []) {
      global $DB;

      $criteria_where = [];

      if (!empty($filters['date_from'])) {
         $criteria_where[] = ['date_creation' => ['>=', $filters['date_from']]];
      }

      if (!empty($filters['date_to'])) {
         $criteria_where[] = ['date_creation' => ['<=', $filters['date_to'] . ' 23:59:59']];
      }

      if (!empty($filters['technician_id'])) {
         $criteria_where['technician_id'] = (int)$filters['technician_id'];
      }

      // Total
      $iterator = $DB->request([
         'COUNT'  => 'cpt',
         'FROM'   => 'glpi_plugin_qualityaudit_audits',
         'WHERE'  => $criteria_where
      ]);
      $stats['total'] = $iterator->current()['cpt'] ?? 0;

      // Approved
      $approved_where = array_merge($criteria_where, ['status' => 'APROVADO']);
      $iterator = $DB->request([
         'COUNT'  => 'cpt',
         'FROM'   => 'glpi_plugin_qualityaudit_audits',
         'WHERE'  => $approved_where
      ]);
      $stats['approved'] = $iterator->current()['cpt'] ?? 0;

      // Refused
      $refused_where = array_merge($criteria_where, ['status' => 'RECUSADO']);
      $iterator = $DB->request([
         'COUNT'  => 'cpt',
         'FROM'   => 'glpi_plugin_qualityaudit_audits',
         'WHERE'  => $refused_where
      ]);
      $stats['refused'] = $iterator->current()['cpt'] ?? 0;

      // Average score
      $iterator = $DB->request([
         'SELECT' => ['AVG' => 'score AS avg_score'],
         'FROM'   => 'glpi_plugin_qualityaudit_audits',
         'WHERE'  => $criteria_where
      ]);
      $stats['avg_score'] = round($iterator->current()['avg_score'] ?? 0);

      // Approval rate
      $stats['approval_rate'] = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0;

      return $stats;
   }
}
