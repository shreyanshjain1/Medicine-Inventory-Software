<?php
require_once 'includes/common.php';
require_login();
require_permission('notes.view');

$noteId = (int) ($_GET['id'] ?? 0);
$redirectUrl = trim((string) ($_GET['redirect'] ?? 'dashboard.php'));
$note = db_fetch_one($conn, 'SELECT * FROM notes WHERE id = ? LIMIT 1', 'i', [$noteId]);
if (!$note || !can_manage_note_record($note)) {
    set_flash('error', 'Note not found or you do not have permission to edit it.');
    redirect($redirectUrl);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Note</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Edit Note', 'Update the note text while preserving note ownership and audit controls.', 'Note Management'); ?>
    <section class="card form-page-card">
        <form method="post" action="notes_action.php" class="note-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="note_id" value="<?php echo (int) $noteId; ?>">
            <input type="hidden" name="redirect_url" value="<?php echo h($redirectUrl); ?>">
            <div class="field">
                <label for="note_text">Note Text</label>
                <textarea id="note_text" name="note_text" rows="8"><?php echo h((string) $note['note_text']); ?></textarea>
            </div>
            <div class="form-submit-row">
                <div class="form-submit-meta">Only authorized users can edit the note. The note record stays attached to the same target.</div>
                <div class="inline-actions">
                    <a class="btn btn-outline" href="<?php echo h($redirectUrl); ?>">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save Note</button>
                </div>
            </div>
        </form>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
