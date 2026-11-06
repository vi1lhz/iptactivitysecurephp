<?php
// Note data access. Every query is parameterized (issue 1)
// and scoped to the owner from the session (issues 3, 7, 9).
declare(strict_types=1);

const TITLE_MAX = 120;
const BODY_MAX  = 10000;

/** Returns an error message, or null if the input is valid. */
function validate_note(mixed $title, mixed $body): ?string
{
    if (!is_string($title) || !is_string($body)) {
        return 'Title and body must be text.';
    }
    $t = trim($title);
    if ($t === '' || mb_strlen($t) > TITLE_MAX) {
        return 'Title must be 1 to ' . TITLE_MAX . ' characters.';
    }
    if (trim($body) === '' || mb_strlen($body) > BODY_MAX) {
        return 'Body must be 1 to ' . BODY_MAX . ' characters.';
    }
    return null;
}

function list_notes(int $ownerId): array
{
    $stmt = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

function find_note(int $id, int $ownerId): ?array
{
    $stmt = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $ownerId]);
    return $stmt->fetch() ?: null;
}

function create_note(int $ownerId, string $title, string $body): void
{
    $stmt = db()->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
    $stmt->execute([$ownerId, trim($title), $body]);
}

/** Returns true only if a note owned by $ownerId was changed. */
function update_note(int $id, int $ownerId, string $title, string $body): bool
{
    if (!find_note($id, $ownerId)) {
        return false;
    }
    $stmt = db()->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([trim($title), $body, $id, $ownerId]);
    return true;
}

function delete_note(int $id, int $ownerId): bool
{
    $stmt = db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $ownerId]);
    return $stmt->rowCount() === 1;
}

function positive_int(mixed $v): ?int
{
    if (is_int($v)) {
        return $v > 0 ? $v : null;
    }
    if (is_string($v) && ctype_digit($v) && strlen($v) < 10 && (int) $v > 0) {
        return (int) $v;
    }
    return null;
}
