<?php
declare(strict_types=1);

namespace ErasedExample;

use PDO;

/**
 * The whole plugin in one file, on purpose - this package exists to be read,
 * not to be clever. It shows the smallest real slice of the Plugin API: a
 * migration that runs on install, a permission that gates the screen, an
 * admin_routes entry that resolves through the service container, and an
 * admin_menu entry that adds a nav group.
 */
final class AdminRoute
{
    private readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function handle(string $id = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        if ($id !== '' && str_ends_with($path, '/delete')) {
            $this->delete((int)$id);
            return;
        }
        $this->list();
    }

    private function list(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $note = trim((string)($_POST['note'] ?? ''));
            if ($note === '') {
                flash('error', 'Enter a note.');
                redirect('/admin/example/notes');
            }
            $statement = $this->pdo->prepare('INSERT INTO example_notes (note) VALUES (:note)');
            $statement->execute([':note' => $note]);
            audit('example.note.create', ['note' => $note]);
            flash('success', 'Note added.');
            redirect('/admin/example/notes');
        }

        $rows = $this->pdo->query('SELECT id, note, created_at FROM example_notes ORDER BY id DESC')->fetchAll();
        $html = '';
        foreach ($rows as $row) {
            $html .= '<div class="admin-row"><div class="admin-row-body">'
                .'<div class="admin-row-title">'.e((string)$row['note']).'</div>'
                .'<div class="admin-row-meta">'.e((string)$row['created_at']).'</div>'
                .'</div><div class="admin-row-actions">'
                .'<form method="post" action="/admin/example/notes/'.(int)$row['id'].'/delete" onsubmit="return confirm(&quot;Delete this note?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn ghost">Delete</button></form>'
                .'</div></div>';
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET EX-01 &middot; EXAMPLE</p><h1>Example Notes</h1>'
            .'<p>A minimal working example of the Plugin API: one migration, one permission, one admin screen.</p></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Add a note</h2></div><div class="panel-body">'
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><div class="fgrid"><div class="fslot"><label>Note<input name="note" required placeholder="Write something"></label></div></div>'
            .'<button class="btn" type="submit" style="margin-top:14px">Add note</button></form>'
            .'</div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All notes</h2></div><div class="panel-body"><div class="admin-row-list">'
            .($html !== '' ? $html : '<div class="admin-row-empty">No notes yet.</div>').'</div></div></div>';
        layout('Example Notes', $body, true);
    }

    private function delete(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/example/notes');
        }
        verify_csrf();
        $statement = $this->pdo->prepare('DELETE FROM example_notes WHERE id = :id');
        $statement->execute([':id' => $id]);
        audit('example.note.delete', ['id' => $id]);
        flash('success', 'Note deleted.');
        redirect('/admin/example/notes');
    }
}
