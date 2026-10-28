<?php
/**
 * Change your own password; admins can also add and remove other accounts.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = Auth::require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'password') {
        $hash = Database::value('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            flash('danger', t('users.err_current'));
        } elseif (!Auth::passwordIsStrong($new)) {
            flash('danger', t('users.err_weak'));
        } elseif ($new !== ($_POST['password_confirm'] ?? '')) {
            flash('danger', t('users.err_match'));
        } else {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            AuditLog::write('password_change', 'Changed own password');
            flash('success', t('users.password_changed'));
        }
    }

    if ($action === 'add' && Auth::isAdmin()) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = ($_POST['role'] ?? '') === Auth::ROLE_ADMIN ? Auth::ROLE_ADMIN : Auth::ROLE_EDITOR;
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', t('install.err_email'));
        } elseif (!Auth::passwordIsStrong($password)) {
            flash('danger', t('users.err_weak'));
        } elseif (Database::value('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('danger', t('users.err_exists'));
        } else {
            Database::query('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            AuditLog::write('user_add', "Added $role $email");
            flash('success', t('users.added'));
        }
    }

    if ($action === 'delete' && Auth::isAdmin()) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $me['id']) {
            flash('danger', t('users.err_self'));
        } else {
            $email = Database::value('SELECT email FROM users WHERE id = ?', [$id]);
            Database::query('DELETE FROM users WHERE id = ?', [$id]);
            AuditLog::write('user_delete', "Deleted user $email");
            flash('success', t('users.deleted'));
        }
    }
    redirect('admin/users.php');
}

$users = Auth::isAdmin() ? Database::all('SELECT id, name, email, role, last_login_at, created_at FROM users ORDER BY id') : [];

$pageTitle = t('admin.nav_users');
$activeAdmin = 'users';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3">
    <?php if (Auth::isAdmin()): ?>
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= e(t('users.list')) ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th><?= e(t('users.name')) ?></th><th><?= e(t('login.email')) ?></th><th><?= e(t('users.role')) ?></th><th><?= e(t('users.last_login')) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= e($u['name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><span class="badge text-bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= e(t('role.' . $u['role'])) ?></span></td>
                                <td class="small"><?= e($u['last_login_at'] ?? '–') ?></td>
                                <td class="text-end">
                                    <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                                        <form method="post" data-confirm="<?= e(t('users.confirm_delete', ['email' => $u['email']])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <button class="btn btn-sm btn-link text-danger p-0" aria-label="<?= e(t('common.delete')) ?>"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="card-header"><?= e(t('users.add')) ?></div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label" for="u-name"><?= e(t('users.name')) ?></label><input class="form-control" id="u-name" name="name" required></div>
                <div class="col-md-6"><label class="form-label" for="u-email"><?= e(t('login.email')) ?></label><input class="form-control" id="u-email" name="email" type="email" required></div>
                <div class="col-md-6">
                    <label class="form-label" for="u-role"><?= e(t('users.role')) ?></label>
                    <select class="form-select" id="u-role" name="role">
                        <option value="editor"><?= e(t('role.editor')) ?></option>
                        <option value="admin"><?= e(t('role.admin')) ?></option>
                    </select>
                    <div class="form-text"><?= e(t('users.role_help')) ?></div>
                </div>
                <div class="col-md-6"><label class="form-label" for="u-pass"><?= e(t('login.password')) ?></label><input class="form-control" id="u-pass" name="password" type="password" required autocomplete="new-password"><div class="form-text"><?= e(t('users.password_rule')) ?></div></div>
                <div class="col-12"><button class="btn btn-primary"><i class="bi bi-person-plus"></i> <?= e(t('users.add')) ?></button></div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="col-xl-5">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="card-header"><?= e(t('users.change_password')) ?></div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="cur"><?= e(t('users.current_password')) ?></label><input class="form-control" type="password" id="cur" name="current_password" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label" for="new"><?= e(t('users.new_password')) ?></label><input class="form-control" type="password" id="new" name="new_password" required autocomplete="new-password"><div class="form-text"><?= e(t('users.password_rule')) ?></div></div>
                <div class="mb-3"><label class="form-label" for="conf"><?= e(t('users.password_confirm')) ?></label><input class="form-control" type="password" id="conf" name="password_confirm" required autocomplete="new-password"></div>
                <button class="btn btn-primary"><i class="bi bi-key"></i> <?= e(t('users.change_password')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
