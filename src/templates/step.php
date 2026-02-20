<?php
declare(strict_types=1);
$stepData = FormSteps::getStep($step);
if (empty($stepData)) {
    header('Location: ?step=1');
    exit;
}
$savedData = $_SESSION['quote_data'] ?? [];
$errors    = $errors ?? [];
?>

<div class="step-block">
    <h1 class="step-title"><?= htmlspecialchars($stepData['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="step-description"><?= htmlspecialchars($stepData['description'], ENT_QUOTES, 'UTF-8') ?></p>

    <form method="POST" action="" class="step-form" novalidate>
        <input type="hidden" name="action" value="submit_step">
        <input type="hidden" name="current_step" value="<?= $step ?>">

        <?php foreach ($stepData['fields'] as $field): ?>
            <?php
            $name      = $field['name'];
            $label     = $field['label'];
            $type      = $field['type'];
            $current   = $savedData[$name] ?? '';
            $hasError  = isset($errors[$name]);
            ?>

            <div class="field-group <?= $hasError ? 'field-group--error' : '' ?>">
                <label class="field-label" for="<?= htmlspecialchars($name) ?>">
                    <?= htmlspecialchars($label) ?>
                    <?php if ($field['required']): ?><span class="required-mark" aria-hidden="true">*</span><?php endif; ?>
                </label>

                <?php if ($type === 'select'): ?>
                    <select name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($name) ?>" class="field-select <?= $hasError ? 'field-select--error' : '' ?>">
                        <option value="">-- Select --</option>
                        <?php foreach ($field['options'] as $val => $optLabel): ?>
                            <option value="<?= htmlspecialchars((string)$val) ?>" <?= $current === (string)$val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($optLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'radio'): ?>
                    <div class="radio-group" role="radiogroup">
                        <?php foreach ($field['options'] as $val => $optLabel): ?>
                            <label class="radio-option">
                                <input type="radio" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars((string)$val) ?>" <?= $current === (string)$val ? 'checked' : '' ?>>
                                <span class="radio-label-text"><?= htmlspecialchars($optLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($type === 'checkbox_group'): ?>
                    <?php
                    $currentArr = is_array($current) ? $current : array_filter(explode(',', (string)$current));
                    ?>
                    <div class="checkbox-group">
                        <?php foreach ($field['options'] as $val => $optLabel): ?>
                            <label class="checkbox-option">
                                <input type="checkbox" name="<?= htmlspecialchars($name) ?>[]" value="<?= htmlspecialchars((string)$val) ?>" <?= in_array((string)$val, $currentArr) ? 'checked' : '' ?>>
                                <span class="checkbox-label-text"><?= htmlspecialchars($optLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($type === 'email'): ?>
                    <input type="email" name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars((string)$current) ?>" class="field-input <?= $hasError ? 'field-input--error' : '' ?>" autocomplete="email" maxlength="<?= $field['max'] ?? 255 ?>">

                <?php else: ?>
                    <input type="text" name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars((string)$current) ?>" class="field-input <?= $hasError ? 'field-input--error' : '' ?>" maxlength="<?= $field['max'] ?? 255 ?>">
                <?php endif; ?>

                <?php if ($hasError): ?>
                    <p class="field-error"><?= htmlspecialchars($errors[$name]) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <?php if ($step > 1): ?>
                <a href="?step=<?= $step - 1 ?>" class="btn btn--secondary">Back</a>
            <?php endif; ?>

            <?php if ($step < FormSteps::count()): ?>
                <button type="submit" class="btn btn--primary">Continue</button>
            <?php else: ?>
                <button type="submit" class="btn btn--primary">Review and Submit</button>
            <?php endif; ?>
        </div>
    </form>
</div>
