<?php
/**
 * Administrator sign-in.
 */
require __DIR__ . '/../includes/bootstrap.php';

if (Auth::user()) {
    redirect('admin/index.php');
}

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $error = Auth::attempt($email, (string) ($_POST['password'] ?? ''), $config['security']);
    if ($error === null) {
        redirect('admin/index.php');
    }
}

$pageTitle = t('login.title');
require __DIR__ . '/../includes/header.php';
?>
<div class="card login-card">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <span class="brand-mark" style="width:3rem;height:3rem;background:var(--brand);color:#fff;font-size:1.4rem"><i class="bi bi-shield-lock"></i></span>
            <h1 class="h4 mt-3 mb-1"><?= e(t('login.title')) ?></h1>
            <p class="text-muted small mb-0"><?= e(t('login.lead')) ?></p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= e(t($error, ['minutes' => $config['security']['lock_minutes']])) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email"><?= e(t('login.email')) ?></label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="form-label" for="password"><?= e(t('login.password')) ?></label>
                <div class="input-group">
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="<?= e(t('login.show')) ?>"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> <?= e(t('login.submit')) ?></button>
        </form>
    </div>
</div>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
