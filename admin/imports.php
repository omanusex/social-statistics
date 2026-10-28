<?php
/**
 * History of every file upload and API sync, with the option to undo one.
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rollback') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $deleted = DataImporter::rollback($id);
    flash('success', t('imports.rolled_back', ['id' => $id, 'n' => $deleted]));
    redirect('admin/imports.php');
}

$imports = DataImporter::recent(100);

$pageTitle = t('admin.nav_imports');
$activeAdmin = 'imports';
require __DIR__ . '/../includes/admin_header.php';
?>
<p class="text-muted"><?= e(t('imports.help')) ?></p>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr>
                <th>#</th><th><?= e(t('imports.date')) ?></th><th><?= e(t('about.source')) ?></th><th><?= e(t('imports.file')) ?></th>
                <th><?= e(t('imports.inserted')) ?></th><th><?= e(t('imports.updated')) ?></th><th><?= e(t('imports.failed')) ?></th>
                <th><?= e(t('imports.status')) ?></th><th><?= e(t('imports.by')) ?></th><th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($imports as $i): ?>
                    <tr>
                        <td><?= (int) $i['id'] ?></td>
                        <td class="small text-nowrap"><?= e($i['created_at']) ?></td>
                        <td><?= e($i['source_name']) ?> <span class="badge text-bg-light"><?= e(t('imports.type_' . $i['type'])) ?></span></td>
                        <td class="small"><?= e($i['filename'] ?? '–') ?></td>
                        <td><?= (int) $i['rows_inserted'] ?></td>
                        <td><?= (int) $i['rows_updated'] ?></td>
                        <td><?= (int) $i['rows_failed'] ?></td>
                        <td>
                            <?php require __DIR__ . '/../includes/partials/import-status.php'; ?>
                            <?php if ($i['message']): ?>
                                <i class="bi bi-info-circle text-muted" title="<?= e($i['message']) ?>" data-bs-toggle="tooltip"></i>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($i['user_name'] ?? '–') ?></td>
                        <td class="text-end">
                            <?php if ($i['rows_now'] > 0): ?>
                                <form method="post" class="d-inline" data-confirm="<?= e(t('imports.confirm_rollback', ['n' => $i['rows_now']])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="rollback">
                                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i> <?= e(t('imports.rollback')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$imports): ?><tr><td colspan="10" class="text-muted p-3"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el)));
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
