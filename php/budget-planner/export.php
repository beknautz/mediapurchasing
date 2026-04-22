<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('/budget-planner/index.php');
}

$plannerService = new BudgetPlannerService();
$proposal       = $plannerService->getProposal($id);
if (empty($proposal)) {
    redirect('/budget-planner/index.php');
}

$allocations = json_decode($proposal['allocation_json'] ?? '{}', true) ?: [];
$unifiedRows = BudgetPlannerService::buildUnifiedTable($allocations);

$budgetGood   = (float)($proposal['budget_good']   ?? 0);
$budgetBetter = (float)($proposal['budget_better'] ?? 0);
$budgetBest   = (float)($proposal['budget_best']   ?? 0);
$today        = date('F j, Y');

$filename = 'Budget_Proposal_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $proposal['title']) . '_' . date('Ymd') . '.xls';

// ─── Helpers ──────────────────────────────────────────────────────────────────
function xCell(string $value, string $type = 'String', string $style = ''): string
{
    $styleAttr = $style !== '' ? " ss:StyleID=\"{$style}\"" : '';
    $value     = htmlspecialchars($value, ENT_XML1, 'UTF-8');
    return "<Cell{$styleAttr}><Data ss:Type=\"{$type}\">{$value}</Data></Cell>";
}

function xNum(float $value, string $style = ''): string
{
    $styleAttr = $style !== '' ? " ss:StyleID=\"{$style}\"" : '';
    return "<Cell{$styleAttr}><Data ss:Type=\"Number\">{$value}</Data></Cell>";
}

function xEmpty(int $count = 1, string $style = ''): string
{
    $styleAttr = $style !== '' ? " ss:StyleID=\"{$style}\"" : '';
    $out       = '';
    for ($i = 0; $i < $count; $i++) {
        $out .= "<Cell{$styleAttr}/>";
    }
    return $out;
}

// Build the category-subtotaled rows for the sheet
$sheetRows  = [];
$currentCat = null;
$catRows    = [];

$finishCategory = function () use (&$catRows, &$sheetRows, &$currentCat) {
    if ($currentCat === null || empty($catRows)) return;
    $sheetRows[] = ['type' => 'cat_header', 'category' => $currentCat];
    foreach ($catRows as $r) {
        $sheetRows[] = ['type' => 'vendor', 'row' => $r];
    }
    $catGood   = array_sum(array_column($catRows, 'good_amount'));
    $catBetter = array_sum(array_column($catRows, 'better_amount'));
    $catBest   = array_sum(array_column($catRows, 'best_amount'));
    $sheetRows[] = ['type' => 'cat_total', 'category' => $currentCat, 'good' => $catGood, 'better' => $catBetter, 'best' => $catBest];
    $catRows     = [];
};

foreach ($unifiedRows as $row) {
    if ($row['category'] !== $currentCat) {
        $finishCategory();
        $currentCat = $row['category'];
    }
    $catRows[] = $row;
}
$finishCategory();

$grandGood   = array_sum(array_column($unifiedRows, 'good_amount'));
$grandBetter = array_sum(array_column($unifiedRows, 'better_amount'));
$grandBest   = array_sum(array_column($unifiedRows, 'best_amount'));

// ─── Output headers ──────────────────────────────────────────────────────────
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<?php echo '<?mso-application progid="Excel.Sheet"?>' . "\n"; ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:x="urn:schemas-microsoft-com:office:excel">

  <Styles>
    <!-- Title -->
    <Style ss:ID="title">
      <Font ss:Bold="1" ss:Size="16" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
      <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    </Style>
    <!-- Subtitle -->
    <Style ss:ID="subtitle">
      <Font ss:Size="10" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
    </Style>
    <!-- Column headers -->
    <Style ss:ID="colhdr">
      <Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>
      <Interior ss:Color="#2C5282" ss:Pattern="Solid"/>
      <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
      <Borders>
        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1E3A5F"/>
      </Borders>
    </Style>
    <!-- Category header -->
    <Style ss:ID="cathdr">
      <Font ss:Bold="1" ss:Color="#1E3A5F" ss:Size="11"/>
      <Interior ss:Color="#EBF4FF" ss:Pattern="Solid"/>
    </Style>
    <!-- Category total -->
    <Style ss:ID="cattotal">
      <Font ss:Bold="1" ss:Color="#2C5282"/>
      <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
      <NumberFormat ss:Format="_(&quot;$&quot;* #,##0.00_);_(&quot;$&quot;* (#,##0.00);_(&quot;$&quot;* &quot;-&quot;??_);_(@_)"/>
    </Style>
    <!-- Vendor name -->
    <Style ss:ID="vname">
      <Font ss:Bold="0"/>
      <Alignment ss:Horizontal="Left"/>
    </Style>
    <!-- Money cell -->
    <Style ss:ID="money">
      <NumberFormat ss:Format="_(&quot;$&quot;* #,##0.00_);_(&quot;$&quot;* (#,##0.00);_(&quot;$&quot;* &quot;-&quot;??_);_(@_)"/>
    </Style>
    <!-- Zero / dash -->
    <Style ss:ID="dash">
      <Alignment ss:Horizontal="Center"/>
      <Font ss:Color="#AAAAAA"/>
    </Style>
    <!-- Grand total -->
    <Style ss:ID="grand">
      <Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
      <NumberFormat ss:Format="_(&quot;$&quot;* #,##0.00_);_(&quot;$&quot;* (#,##0.00);_(&quot;$&quot;* &quot;-&quot;??_);_(@_)"/>
    </Style>
    <Style ss:ID="grandlabel">
      <Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
    </Style>
    <!-- Approved badge -->
    <Style ss:ID="approved">
      <Font ss:Bold="1" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#276749" ss:Pattern="Solid"/>
      <Alignment ss:Horizontal="Center"/>
    </Style>
    <!-- Target row -->
    <Style ss:ID="target">
      <Font ss:Italic="1" ss:Color="#555555"/>
      <Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/>
      <NumberFormat ss:Format="_(&quot;$&quot;* #,##0.00_);_(&quot;$&quot;* (#,##0.00);_(&quot;$&quot;* &quot;-&quot;??_);_(@_)"/>
    </Style>
    <Style ss:ID="targetlabel">
      <Font ss:Italic="1" ss:Color="#555555"/>
      <Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/>
    </Style>
  </Styles>

  <Worksheet ss:Name="Budget Proposal">
    <Table ss:DefaultColumnWidth="90">
      <Column ss:Width="200"/>
      <Column ss:Width="180"/>
      <Column ss:Width="130"/>
      <Column ss:Width="130"/>
      <Column ss:Width="130"/>

      <!-- Title row -->
      <Row ss:Height="32">
        <Cell ss:StyleID="title" ss:MergeAcross="4">
          <Data ss:Type="String"><?= htmlspecialchars($proposal['title'], ENT_XML1, 'UTF-8') ?></Data>
        </Cell>
      </Row>
      <!-- Subtitle row -->
      <Row ss:Height="18">
        <Cell ss:StyleID="subtitle" ss:MergeAcross="4">
          <Data ss:Type="String">Budget Proposal — Generated <?= htmlspecialchars($today, ENT_XML1, 'UTF-8') ?><?= !empty($proposal['client_name']) ? ' — Client: ' . htmlspecialchars($proposal['client_name'], ENT_XML1, 'UTF-8') : '' ?></Data>
        </Cell>
      </Row>

      <!-- Approved tier banner (if applicable) -->
      <?php if (!empty($proposal['approved_tier'])): ?>
      <Row ss:Height="20">
        <Cell ss:StyleID="approved" ss:MergeAcross="4">
          <Data ss:Type="String">APPROVED: <?= strtoupper(htmlspecialchars($proposal['approved_tier'], ENT_XML1, 'UTF-8')) ?> TIER — $<?= number_format((float)$proposal['budget_' . $proposal['approved_tier']]) ?></Data>
        </Cell>
      </Row>
      <?php endif; ?>

      <!-- Blank row -->
      <Row ss:Height="8"><Cell ss:MergeAcross="4"/></Row>

      <!-- Column headers -->
      <Row ss:Height="36">
        <?= xCell('Vendor', 'String', 'colhdr') ?>
        <?= xCell('Category', 'String', 'colhdr') ?>
        <?= xCell("Good\n\$" . number_format($budgetGood, 0), 'String', 'colhdr') ?>
        <?= xCell("Better\n\$" . number_format($budgetBetter, 0), 'String', 'colhdr') ?>
        <?= xCell("Best\n\$" . number_format($budgetBest, 0), 'String', 'colhdr') ?>
      </Row>

      <!-- Data rows -->
      <?php foreach ($sheetRows as $sr): ?>
        <?php if ($sr['type'] === 'cat_header'): ?>
      <Row>
        <Cell ss:StyleID="cathdr" ss:MergeAcross="4">
          <Data ss:Type="String"><?= htmlspecialchars($sr['category'] ?: 'Uncategorized', ENT_XML1, 'UTF-8') ?></Data>
        </Cell>
      </Row>

        <?php elseif ($sr['type'] === 'vendor'): ?>
          <?php $r = $sr['row']; ?>
      <Row>
        <?= xCell($r['vendor_name'], 'String', 'vname') ?>
        <?= xCell($r['category'], 'String') ?>
        <?php if ($r['good_amount'] > 0): ?>
          <?= xNum($r['good_amount'], 'money') ?>
        <?php else: ?>
          <?= xCell('—', 'String', 'dash') ?>
        <?php endif; ?>
        <?php if ($r['better_amount'] > 0): ?>
          <?= xNum($r['better_amount'], 'money') ?>
        <?php else: ?>
          <?= xCell('—', 'String', 'dash') ?>
        <?php endif; ?>
        <?php if ($r['best_amount'] > 0): ?>
          <?= xNum($r['best_amount'], 'money') ?>
        <?php else: ?>
          <?= xCell('—', 'String', 'dash') ?>
        <?php endif; ?>
      </Row>

        <?php elseif ($sr['type'] === 'cat_total'): ?>
      <Row>
        <Cell ss:StyleID="cattotal">
          <Data ss:Type="String">    <?= htmlspecialchars($sr['category'], ENT_XML1, 'UTF-8') ?> Total</Data>
        </Cell>
        <?= xEmpty(1, 'cattotal') ?>
        <?= xNum($sr['good'], 'cattotal') ?>
        <?= xNum($sr['better'], 'cattotal') ?>
        <?= xNum($sr['best'], 'cattotal') ?>
      </Row>
      <!-- Spacer -->
      <Row ss:Height="6"><Cell ss:MergeAcross="4"/></Row>

        <?php endif; ?>
      <?php endforeach; ?>

      <!-- Grand total -->
      <Row ss:Height="22">
        <Cell ss:StyleID="grandlabel" ss:MergeAcross="1">
          <Data ss:Type="String">GRAND TOTAL</Data>
        </Cell>
        <?= xNum($grandGood, 'grand') ?>
        <?= xNum($grandBetter, 'grand') ?>
        <?= xNum($grandBest, 'grand') ?>
      </Row>

      <!-- Budget target -->
      <Row>
        <Cell ss:StyleID="targetlabel" ss:MergeAcross="1">
          <Data ss:Type="String">Budget Target</Data>
        </Cell>
        <?= xNum($budgetGood, 'target') ?>
        <?= xNum($budgetBetter, 'target') ?>
        <?= xNum($budgetBest, 'target') ?>
      </Row>

      <!-- AI Rationale if present -->
      <?php if (!empty($proposal['ai_rationale'])): ?>
      <Row ss:Height="8"><Cell ss:MergeAcross="4"/></Row>
      <Row>
        <Cell ss:MergeAcross="4">
          <Data ss:Type="String">Strategy: <?= htmlspecialchars($proposal['ai_rationale'], ENT_XML1, 'UTF-8') ?></Data>
        </Cell>
      </Row>
      <?php endif; ?>

    </Table>
  </Worksheet>
</Workbook>
