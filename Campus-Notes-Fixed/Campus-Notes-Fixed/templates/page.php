<?php
// Page template. Every dynamic value goes through e() (issue 4).
declare(strict_types=1);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Campus Notes</title>
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}.inline{display:inline}.body{white-space:pre-wrap}</style>
</head>
<body>
<h1>Campus Notes</h1>
<?php if ($message !== ''): ?><p role="status"><?= e($message) ?></p><?php endif; ?>

<?php if (!$user): ?>
<h2>Register or sign in</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <label>Email <input name="email" type="email" maxlength="254" required></label>
  <label>Password <input name="password" type="password" minlength="12" maxlength="72" required></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<p>Signed in as <?= e($user['email']) ?> | Role: <?= e($user['role']) ?></p>
<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <button name="action" value="logout">Log out</button>
</form>

<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="id" value="<?= e($editing['id']) ?>">
  <label>Title <input name="title" value="<?= e($editing['title']) ?>" maxlength="120" required></label>
  <label>Body <textarea name="body" required><?= e($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>

<h2>Create a note</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <label>Title <input name="title" maxlength="120" required></label>
  <label>Body <textarea name="body" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>

<h2>My notes</h2>
<?php foreach ($notes as $note): ?>
<article>
  <h3><?= e($note['title']) ?></h3>
  <p class="body"><?= e($note['body']) ?></p>
  <a href="?edit=<?= e($note['id']) ?>">Edit</a>
  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= e($note['id']) ?>">
    <button name="action" value="delete">Delete</button>
  </form>
</article>
<?php endforeach; ?>
<?php if (!$notes): ?><p>No notes yet.</p><?php endif; ?>
<?php endif; ?>
</body></html>
