<?php
/**
 * APM - Export Handler (PDF via HTML/CSS print + Excel via CSV)
 * Note: For full TCPDF/PhpSpreadsheet, install via composer.
 * This provides a working fallback that works on shared hosting without Composer.
 */

function exportReport(string $format, array $data, int $year, string $month = ''): void {
    $title = "Leave Report - $year" . ($month ? " ($month)" : '');
    $generated = date('d M Y H:i');
    
    if ($format === 'excel') {
        exportCSV($data, $title);
    } elseif ($format === 'pdf') {
        exportPDF($data, $title, $generated);
    }
    exit;
}

function exportCSV(array $data, string $title): void {
    $filename = 'apm_leave_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");
    
    // Title
    fputcsv($out, [$title]);
    fputcsv($out, ['Generated: ' . date('d M Y H:i')]);
    fputcsv($out, []);
    
    // Headers
    fputcsv($out, [
        'Employee ID', 'First Name', 'Last Name', 'Email',
        'Shift', 'Team',
        'Annual Days', 'Carryover', 'Total Balance',
        'Used Days', 'Pending Days', 'Remaining Balance',
        'Approved Requests', 'Last Leave Date'
    ]);
    
    foreach ($data as $row) {
        $total = ((float)($row['annual_days']??21)) + ((float)($row['carryover_days']??0));
        fputcsv($out, [
            $row['employee_id'] ?? '',
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['shift_name'] ?? '',
            $row['team_code'] ?? '',
            $row['annual_days'] ?? 21,
            $row['carryover_days'] ?? 0,
            $total,
            $row['approved_days'] ?? 0,
            $row['pending_days'] ?? 0,
            max(0, (float)($row['remaining_balance'] ?? $total)),
            $row['approved_count'] ?? 0,
            $row['last_leave_date'] ?? '',
        ]);
    }
    
    // Summary
    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Total Employees', count($data)]);
    fputcsv($out, ['Total Approved Days', array_sum(array_column($data, 'approved_days'))]);
    fputcsv($out, ['Total Pending Days', array_sum(array_column($data, 'pending_days'))]);
    
    fclose($out);
}

function exportPDF(array $data, string $title, string $generated): void {
    $filename = 'apm_leave_report_' . date('Ymd_His') . '.pdf';
    
    // Generate HTML for PDF
    $totalApproved = array_sum(array_column($data, 'approved_days'));
    $totalPending  = array_sum(array_column($data, 'pending_days'));
    
    $rows = '';
    foreach ($data as $i => $row) {
        $total = ((float)($row['annual_days']??21)) + ((float)($row['carryover_days']??0));
        $remaining = max(0, (float)($row['remaining_balance'] ?? $total));
        $bg = ($i % 2 === 0) ? '#f8f9fa' : '#ffffff';
        $remColor = $remaining < 5 ? '#dc3545' : '#198754';
        $rows .= "<tr style='background:{$bg}'>
            <td>" . htmlspecialchars($row['employee_id']??'') . "</td>
            <td><strong>" . htmlspecialchars($row['first_name'].' '.$row['last_name']) . "</strong></td>
            <td>" . htmlspecialchars($row['shift_name']??'') . "</td>
            <td><strong>" . htmlspecialchars($row['team_code']??'') . "</strong></td>
            <td style='text-align:center'>{$total}</td>
            <td style='text-align:center;color:#dc3545'><strong>" . ($row['approved_days']??0) . "</strong></td>
            <td style='text-align:center;color:#fd7e14'>" . ($row['pending_days']??0) . "</td>
            <td style='text-align:center;color:{$remColor}'><strong>{$remaining}</strong></td>
            <td style='text-align:center'>" . ($row['approved_count']??0) . "</td>
            <td>" . ($row['last_leave_date'] ? date('d/m/Y', strtotime($row['last_leave_date'])) : '—') . "</td>
        </tr>";
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>
  @media print { @page { margin: 1cm; size: A4 landscape; } }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 20px; }
  .header { background: #1a3c6e; color: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
  .header h1 { margin: 0; font-size: 20px; }
  .header .meta { font-size: 12px; opacity: 0.8; }
  .summary { display: flex; gap: 15px; margin-bottom: 20px; }
  .summary-card { flex: 1; background: #f8f9fa; border-radius: 6px; padding: 12px 15px; border-left: 4px solid #1a3c6e; }
  .summary-card .value { font-size: 22px; font-weight: bold; color: #1a3c6e; }
  .summary-card .label { font-size: 11px; color: #666; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  th { background: #1a3c6e; color: white; padding: 8px 10px; text-align: left; }
  td { padding: 7px 10px; border-bottom: 1px solid #eee; }
  .footer { margin-top: 20px; font-size: 10px; color: #999; text-align: center; }
  .no-print { display: block; margin-bottom: 15px; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()" style="background:#1a3c6e;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px">🖨️ Print / Save as PDF</button>
  <button onclick="window.close()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px;margin-left:10px">✕ Close</button>
</div>
<div class="header">
  <div>
    <h1>APM Leave Management Report</h1>
    <div class="meta">{$title}</div>
  </div>
  <div class="meta">Generated: {$generated}</div>
</div>
<div class="summary">
  <div class="summary-card"><div class="value">{$totalApproved}</div><div class="label">Total Approved Days</div></div>
  <div class="summary-card"><div class="value">{$totalPending}</div><div class="label">Total Pending Days</div></div>
  <div class="summary-card"><div class="value">{$_}</div><div class="label">Total Employees</div></div>
</div>
<table>
<thead>
  <tr>
    <th>Emp ID</th><th>Name</th><th>Shift</th><th>Team</th>
    <th>Total</th><th>Used</th><th>Pending</th><th>Remaining</th><th>Requests</th><th>Last Leave</th>
  </tr>
</thead>
<tbody>{$rows}</tbody>
</table>
<div class="footer">APM Leave Management System &mdash; Confidential Report</div>
</body>
</html>
HTML;

    // Replace count placeholder
    $count = count($data);
    $html = str_replace('{$_}', $count, $html);
    
    // Output as HTML for browser print-to-PDF
    echo $html;
}
