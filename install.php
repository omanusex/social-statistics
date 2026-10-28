<?php
/**
 * One-time installer: creates the database and tables, loads reference data,
 * creates the first administrator and (optionally) the demo questionnaire data.
 * After it finishes, storage/installed.lock stops it from running again.
 */
define('SKIP_INSTALL_CHECK', true);
require __DIR__ . '/includes/bootstrap.php';

$lockFile = STORAGE_PATH . '/installed.lock';
$installed = is_file($lockFile);
$errors = [];
$form = ['name' => 'Administrator', 'email' => '', 'demo' => '1'];

/** Runs every statement of a .sql file. */
function run_sql_file(PDO $pdo, string $file): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'name'  => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'demo'  => isset($_POST['demo']) ? '1' : '0',
    ];
    $password = $_POST['password'] ?? '';

    if ($form['name'] === '')                                  $errors[] = t('install.err_name');
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL))    $errors[] = t('install.err_email');
    if (!Auth::passwordIsStrong($password))                    $errors[] = t('users.err_weak');
    if ($password !== ($_POST['password_confirm'] ?? ''))       $errors[] = t('users.err_match');

    if (!$errors) {
        try {
            $server = Database::connect(false);
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . Database::name() . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = Database::conn();
            run_sql_file($pdo, ROOT_PATH . '/database/schema.sql');
            run_sql_file($pdo, ROOT_PATH . '/database/seed.sql');

            Database::query(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
                [$form['name'], $form['email'], password_hash($password, PASSWORD_DEFAULT)]
            );
            $adminId = (int) $pdo->lastInsertId();
            AuditLog::write('install', 'Database installed', $adminId);

            if ($form['demo'] === '1') {
                $importer = new DataImporter($adminId);
                $parsed = $importer->parseSurvey(ROOT_PATH . '/database/templates/sample_survey.csv', 'csv');
                $importer->saveSurvey($parsed['records'], 'sample_survey.csv (demo)', count($parsed['errors']), true);
                Settings::set('survey_is_demo', '1');
            }

            foreach (['uploads', 'backups'] as $dir) {
                if (!is_dir(STORAGE_PATH . "/$dir")) {
                    mkdir(STORAGE_PATH . "/$dir", 0775, true);
                }
            }
            file_put_contents($lockFile, date('c'));
            flash('success', t('install.done'));
            redirect('admin/login.php');
        } catch (Throwable $ex) {
            $errors[] = t('install.err_db') . ' ' . $ex->getMessage();
        }
    }
}

$pageTitle = t('install.title');
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="page-header">
            <h1><?= e(t('install.title')) ?></h1>
            <p><?= e(t('install.intro')) ?></p>
        </div>

        <?php if ($installed): ?>
            <div class="alert alert-info">
                <?= e(t('install.already')) ?>
                <a href="<?= url('index.php') ?>"><?= e(t('nav.home')) ?></a>
            </div>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0">
                    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <p class="text-muted small"><?= e(t('install.db_info', ['db' => Database::name()])) ?></p>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="name"><?= e(t('users.name')) ?></label>
                            <input class="form-control" id="name" name="name" value="<?= e($form['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email"><?= e(t('login.email')) ?></label>
                            <input class="form-control" id="email" name="email" type="email" value="<?= e($form['email']) ?>" required autocomplete="username">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="password"><?= e(t('login.password')) ?></label>
                                <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="password_confirm"><?= e(t('users.password_confirm')) ?></label>
                                <input class="form-control" id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
                            </div>
                            <div class="form-text"><?= e(t('users.password_rule')) ?></div>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="demo" name="demo" value="1" <?= $form['demo'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="demo"><?= e(t('install.demo')) ?></label>
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-database-check"></i> <?= e(t('install.submit')) ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
