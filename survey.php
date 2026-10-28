<?php
/**
 * Online questionnaire for the local study. Answers are anonymous:
 * no name, email or IP address is stored with a response.
 */
require __DIR__ . '/includes/bootstrap.php';

$isOpen = Settings::get('survey_open') === '1';
$platforms = SurveyRepository::surveyPlatforms();
$errors = [];
$old = ['platforms' => []];

if ($isOpen && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = [
        'age_group'      => $_POST['age_group'] ?? '',
        'gender'         => $_POST['gender'] ?? '',
        'platforms'      => array_values(array_filter((array) ($_POST['platforms'] ?? []), 'is_string')),
        'favourite'      => $_POST['favourite'] ?? '',
        'main_purpose'   => $_POST['main_purpose'] ?? '',
        'daily_hours'    => $_POST['daily_hours'] ?? '',
        'late_night_use' => $_POST['late_night_use'] ?? '',
    ];

    if (($_POST['website'] ?? '') !== '') {
        // Hidden "honeypot" field: only automated bots fill it in
        redirect('local-study.php');
    }
    if (!empty($_SESSION['survey_done'])) {
        $errors[] = 'survey.err_twice';
    } elseif (empty($_POST['consent'])) {
        $errors[] = 'survey.err_consent';
    } else {
        $errors = SurveyRepository::add($old, 'online');
    }

    if (!$errors) {
        $_SESSION['survey_done'] = true;
        flash('success', t('survey.thanks'));
        redirect('local-study.php');
    }
}

/** Renders a group of radio buttons for one question. */
function radio_group(string $name, array $options, string $labelPrefix, array $old): void
{
    echo '<div class="option-grid">';
    foreach ($options as $value) {
        $id = $name . '-' . preg_replace('/[^a-z0-9]/i', '', $value);
        $checked = ($old[$name] ?? '') === (string) $value ? 'checked' : '';
        echo '<div class="form-check"><input class="form-check-input" type="radio" required name="' . e($name) . '" id="' . e($id) . '" value="' . e($value) . '" ' . $checked . '>'
           . '<label class="form-check-label" for="' . e($id) . '">' . e(t($labelPrefix . $value)) . '</label></div>';
    }
    echo '</div>';
}

$pageTitle = t('survey.title');
$activeNav = 'study';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="page-header">
            <h1><?= e(t('survey.title')) ?></h1>
            <p><?= e(t('survey.lead')) ?></p>
        </div>

        <?php if (!$isOpen): ?>
            <div class="alert alert-info"><?= e(t('survey.closed')) ?></div>
        <?php elseif (!empty($_SESSION['survey_done'])): ?>
            <div class="alert alert-success"><?= e(t('survey.err_twice')) ?> <a href="<?= url('local-study.php') ?>"><?= e(t('study.see_results')) ?></a></div>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert"><ul class="mb-0">
                    <?php foreach ($errors as $error): ?><li><?= e(t($error)) ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <form method="post" class="card">
                <div class="card-body px-4">
                    <?= csrf_field() ?>
                    <div class="d-none" aria-hidden="true">
                        <label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="question">
                        <legend>1. <?= e(t('survey.q_age')) ?></legend>
                        <?php radio_group('age_group', SurveyRepository::AGE_GROUPS, 'survey.age.', $old); ?>
                    </fieldset>
                    <fieldset class="question">
                        <legend>2. <?= e(t('survey.q_gender')) ?></legend>
                        <?php radio_group('gender', SurveyRepository::GENDERS, 'survey.gender.', $old); ?>
                    </fieldset>
                    <fieldset class="question">
                        <legend>3. <?= e(t('survey.q_platforms')) ?></legend>
                        <div class="option-grid">
                            <?php foreach ($platforms as $p): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="platforms[]" id="pl-<?= e($p['slug']) ?>" value="<?= e($p['slug']) ?>" <?= in_array($p['slug'], $old['platforms'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pl-<?= e($p['slug']) ?>"><?= e($p['name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset class="question">
                        <legend>4. <?= e(t('survey.q_favourite')) ?></legend>
                        <select class="form-select" name="favourite" id="favourite" required aria-label="<?= e(t('survey.q_favourite')) ?>" style="max-width: 320px">
                            <option value=""><?= e(t('survey.choose')) ?></option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= e($p['slug']) ?>" <?= ($old['favourite'] ?? '') === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>
                    <fieldset class="question">
                        <legend>5. <?= e(t('survey.q_purpose')) ?></legend>
                        <?php radio_group('main_purpose', SurveyRepository::PURPOSES, 'survey.purpose.', $old); ?>
                    </fieldset>
                    <fieldset class="question">
                        <legend>6. <?= e(t('survey.q_hours')) ?></legend>
                        <?php radio_group('daily_hours', SurveyRepository::HOURS, 'survey.hours.', $old); ?>
                    </fieldset>
                    <fieldset class="question">
                        <legend>7. <?= e(t('survey.q_late')) ?></legend>
                        <?php radio_group('late_night_use', ['1', '0'], 'survey.late.', $old); ?>
                    </fieldset>

                    <div class="form-check my-4">
                        <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
                        <label class="form-check-label" for="consent"><?= e(t('survey.consent')) ?></label>
                    </div>
                    <button class="btn btn-primary btn-lg"><i class="bi bi-send"></i> <?= e(t('survey.submit')) ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
