<?php
require_once 'includes/common.php';
require_login();
require_permission('history.view');

$type = trim((string) ($_GET['type'] ?? $_POST['type'] ?? 'out'));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

if (request_is_post()) {
    verify_csrf_or_fail();
    require_permission('reversals.manage');
    $action = trim((string) ($_POST['action'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($reason === '') {
        $errors[] = 'A reason is required for reversal or void actions.';
    } else {
        if ($type === 'out' && $action === 'void_out') {
            void_out_record($conn, $id, $reason, $errors);
        } elseif ($type === 'return' && $action === 'void_return') {
            void_return_record($conn, $id, $reason, $errors);
        } elseif ($type === 'in' && $action === 'void_in') {
            $inRecord = fetch_in_record_detail($conn, $id);
            if (!$inRecord) {
                $errors[] = 'IN record not found.';
            } else {
                $source = $inRecord['source_type'] ?? 'regular';
                $source = $source === 'outsourced' ? 'outsourced' : 'regular';
                void_in_batch($conn, $source, (int) ($inRecord['inventory_ref_id'] ?? 0), $reason, $errors);
            }
        }

        if (!$errors) {
            set_flash('success', 'Transaction voided successfully.');
            redirect('transaction_detail.php?type=' . urlencode($type) . '&id=' . $id);
        }
    }
}

$record = null;
$title = 'Transaction Detail';
$targetType = '';

if ($type === 'out') {
    $record = fetch_out_record_detail($conn, $id);
    $title = 'OUT Transaction Detail';
    $targetType = 'out_record';
} elseif ($type === 'return') {
    $record = fetch_return_record_detail($conn, $id);
    $title = 'RETURN Transaction Detail';
    $targetType = 'return_record';
} else {
    $type = 'in';
    $record = fetch_in_record_detail($conn, $id);
    $title = 'IN Transaction Detail';
    $targetType = 'in_log';
}

if (!$record) {
    set_flash('error', 'Transaction record not found.');
    redirect('dashboard.php');
}

$notes = fetch_notes($conn, $targetType, $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php
    $actions = [];
    if (user_can('corrections.manage') && in_array($type, ['out', 'return'], true)) {
        $actions[] = '<a class="btn btn-primary" href="edit_record.php?type=' . urlencode($type) . '&id=' . $id . '">Correct Record</a>';
    }
    render_app_nav($conn, $title, 'View transaction details, notes, correction actions, and reversible status in one place.', 'Transaction Detail', $actions);
    ?>
    <section class="card form-page-card">
        <?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?php echo h($error); ?></div><?php endforeach; ?></div><?php endif; ?>
        <div class="batch-meta-grid">
            <?php foreach ($record as $key => $value): ?>
                <?php if (is_array($value) || in_array($key, ['password'], true)) {
                    continue;
                } ?>
                <div class="batch-meta-box"><span class="batch-meta-label"><?php echo h(ucwords(str_replace('_', ' ', (string) $key))); ?></span><strong><?php echo h((string) $value); ?></strong></div>
            <?php endforeach; ?>
        </div>
        <?php if (user_can('reversals.manage') && ($record['record_status'] ?? 'active') === 'active'): ?>
            <form method="post" class="void-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="type" value="<?php echo h($type); ?>">
                <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                <input type="hidden" name="action" value="<?php echo h($type === 'out' ? 'void_out' : ($type === 'return' ? 'void_return' : 'void_in')); ?>">
                <div class="field"><label for="reason">Void / Reverse Reason</label><textarea id="reason" name="reason" rows="3" placeholder="Required reason"></textarea></div>
                <button class="btn btn-danger" type="submit">Void / Reverse Transaction</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card notes-card">
        <div class="section-header"><div><h2 class="section-title">Transaction Notes</h2><p class="section-subtitle">Remarks attached directly to this transaction record.</p></div></div>
        <?php if (user_can('notes.add')): ?>
            <form method="post" action="notes_action.php" class="note-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="target_type" value="<?php echo h($targetType); ?>">
                <input type="hidden" name="target_id" value="<?php echo (int) $id; ?>">
                <input type="hidden" name="redirect_url" value="<?php echo h('transaction_detail.php?type=' . $type . '&id=' . $id); ?>">
                <div class="field"><label for="note_text">Add Note</label><textarea id="note_text" name="note_text" rows="4"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Note</button>
            </form>
        <?php endif; ?>
        <div class="notes-list">
            <?php if ($notes): foreach ($notes as $note): ?>
                <div class="note-item">
                    <div class="note-meta"><strong><?php echo h((string) $note['created_by']); ?></strong><span><?php echo h(format_datetime($note['created_at'] ?? '')); ?></span></div>
                    <div class="note-body"><?php echo nl2br(h((string) $note['note_text'])); ?></div>
                    <?php if (can_manage_note_record($note)): ?>
                        <div class="inline-actions"><a class="btn btn-soft btn-mini" href="note_edit.php?id=<?php echo (int) $note['id']; ?>&redirect=<?php echo urlencode('transaction_detail.php?type=' . $type . '&id=' . $id); ?>">Edit</a><form method="post" action="notes_action.php"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>"><input type="hidden" name="redirect_url" value="<?php echo h('transaction_detail.php?type=' . $type . '&id=' . $id); ?>"><button class="btn btn-outline btn-mini" type="submit">Delete</button></form></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; else: ?>
                <div class="empty-state">No notes yet for this transaction.</div>
            <?php endif; ?>
        </div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
