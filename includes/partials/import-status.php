<?php
// Coloured status badge for one import row ($i)
$statusClass = ['success' => 'success', 'partial' => 'warning', 'failed' => 'danger'][$i['status']] ?? 'secondary';
?>
<span class="badge text-bg-<?= $statusClass ?>"><?= e(t('imports.status_' . $i['status'])) ?></span>
