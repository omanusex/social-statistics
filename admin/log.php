<?php
/**
 * Audit log of administrator actions (admins only).
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require(true);

$perPage = 50;
$total = AuditLog::count();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) input('page', '1')));
$entries = AuditLog::latest($perPage, ($page - 1) * $perPage);

$pageTitle = t('admin.nav_log');
$activeAdmin = 'log';
require __DIR__ . '/../includes/admin_header.php';
?>
<p class="text-muted"><?= e(t('log.help')) ?></p>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th><?= e(t('imports.date')) ?></th><th><?= e(t('log.action')) ?></th><th><?= e(t('imports.by')) ?></th><th><?= e(t('log.details')) ?></th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($entries as $a): ?>
                    <tr class="<?= $a['action'] === 'login_failed' ? 'table-warning' : '' ?>">
                        <td class="small text-nowrap"><?= e($a['created_at']) ?></td>
                        <td><span class="badge text-bg-light"><?= e($a['action']) ?></span></td>
                        <td class="small"><?= e($a['user_name'] ?? '–') ?></td>
                        <td class="small"><?= e($a['details']) ?></td>
                        <td class="small text-muted"><?= e($a['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($pages > 1): ?>
    <nav class="mt-3" aria-label="<?= e(t('common.pages')) ?>"><ul class="pagination pagination-sm flex-wrap">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= url('admin/log.php', ['page' => $p]) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
