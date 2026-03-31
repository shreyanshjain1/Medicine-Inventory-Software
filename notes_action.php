<?php
require_once 'includes/common.php';
require_login();
require_permission('notes.view');

if (!request_is_post()) {
    redirect('dashboard.php');
}

verify_csrf_or_fail();

$action = trim((string) ($_POST['action'] ?? ''));
$redirectUrl = trim((string) ($_POST['redirect_url'] ?? 'dashboard.php'));

if ($action === 'add') {
    require_permission('notes.add');
    $targetType = trim((string) ($_POST['target_type'] ?? ''));
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $noteText = trim((string) ($_POST['note_text'] ?? ''));
    if ($targetType === '' || $targetId <= 0 || $noteText === '') {
        set_flash('error', 'Note text is required.');
    } elseif (!add_note_record($conn, $targetType, $targetId, $noteText)) {
        set_flash('error', 'Unable to save the note.');
    } else {
        set_flash('success', 'Note added successfully.');
    }
    redirect($redirectUrl);
}

$noteId = (int) ($_POST['note_id'] ?? 0);
$note = db_fetch_one($conn, 'SELECT * FROM notes WHERE id = ? LIMIT 1', 'i', [$noteId]);
if (!$note) {
    set_flash('error', 'Note not found.');
    redirect($redirectUrl);
}

if (!can_manage_note_record($note)) {
    set_flash('error', 'You do not have permission to manage that note.');
    redirect($redirectUrl);
}

if ($action === 'update') {
    $noteText = trim((string) ($_POST['note_text'] ?? ''));
    if ($noteText === '') {
        set_flash('error', 'Note text is required.');
    } elseif (!update_note_record($conn, $noteId, $noteText)) {
        set_flash('error', 'Unable to update the note.');
    } else {
        set_flash('success', 'Note updated successfully.');
    }
    redirect($redirectUrl);
}

if ($action === 'delete') {
    if (!delete_note_record($conn, $noteId)) {
        set_flash('error', 'Unable to delete the note.');
    } else {
        set_flash('success', 'Note deleted successfully.');
    }
    redirect($redirectUrl);
}

set_flash('error', 'Invalid note action.');
redirect($redirectUrl);
