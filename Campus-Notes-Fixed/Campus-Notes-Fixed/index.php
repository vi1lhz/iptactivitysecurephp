<?php
// Web controller: handles form actions, then renders the page template.
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

$message = '';
$uid = current_user_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_with_csrf();               // issue 5: POST + CSRF token + origin check
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $err = register_user((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        $message = $err ?? 'Account created. You can sign in now.';
    } elseif ($action === 'login') {
        if (login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: /');
            exit;
        }
        $message = 'Invalid email or password.'; // generic, no echo of input
    } elseif ($action === 'logout') {
        logout_user();
        header('Location: /');
        exit;
    } elseif ($uid === null) {
        http_response_code(401);
        $message = 'Please sign in.';
    } elseif ($action === 'save') {
        // issue 7: owner comes from the session, not the form
        $err = validate_note($_POST['title'] ?? null, $_POST['body'] ?? null);
        if ($err === null) {
            create_note($uid, $_POST['title'], $_POST['body']);
            header('Location: /');
            exit;
        }
        $message = $err;
    } elseif ($action === 'update') {
        $id  = positive_int($_POST['id'] ?? null);
        $err = validate_note($_POST['title'] ?? null, $_POST['body'] ?? null);
        if ($id === null || $err !== null) {
            $message = $err ?? 'Invalid note.';
        } elseif (update_note($id, $uid, $_POST['title'], $_POST['body'])) {
            header('Location: /');
            exit;
        } else {
            http_response_code(404);
            $message = 'Note not found.';
        }
    } elseif ($action === 'delete') {
        $id = positive_int($_POST['id'] ?? null);
        if ($id !== null && delete_note($id, $uid)) {
            header('Location: /');
            exit;
        }
        http_response_code(404);
        $message = 'Note not found.';
    } else {
        http_response_code(400);
        $message = 'Unknown action.';
    }
}

$user    = current_user();
$notes   = [];
$editing = null;
if ($user) {
    $notes = list_notes((int) $user['id']);
    $editId = positive_int($_GET['edit'] ?? null);
    if ($editId !== null) {
        $editing = find_note($editId, (int) $user['id']); // issue 3: only own notes
        if (!$editing) {
            $message = 'Note not found.';
        }
    }
}

require __DIR__ . '/templates/page.php';
