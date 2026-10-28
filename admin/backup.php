<?php
/**
 * Database backup download (admins only) and restore instructions.
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $sql = BackupService::dump();
    $name = 'social_stats-backup-' . date('Ymd-His') . '.sql';
    // Keep a copy on the server as well (storage/ is not reachable from the web)
    file_put_contents(STORAGE_PATH . '/backups/' . $name, $sql);
    AuditLog::write('backup', "Backup $name (" . round(strlen($sql) / 1024) . ' KB)');

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

$backups = array_reverse(glob(STORAGE_PATH . '/backups/*.sql') ?: []);

$pageTitle = t('admin.nav_backup');
$activeAdmin = 'backup';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= e(t('backup.create')) ?></div>
            <div class="card-body">
                <p><?= e(t('backup.help')) ?></p>
                <form method="post">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary"><i class="bi bi-download"></i> <?= e(t('backup.download')) ?></button>
                </form>
                <?php if ($backups): ?>
                    <p class="small text-muted mt-3 mb-1"><?= e(t('backup.on_server')) ?></p>
                    <ul class="small mb-0">
                        <?php foreach (array_slice($backups, 0, 5) as $file): ?>
                            <li><?= e(basename($file)) ?> (<?= round(filesize($file) / 1024) ?> KB)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= e(t('backup.restore')) ?></div>
            <div class="card-body">
                <ol class="mb-0">
                    <li><?= e(t('backup.r1')) ?></li>
                    <li><?= e(t('backup.r2')) ?></li>
                    <li><?= e(t('backup.r3')) ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
