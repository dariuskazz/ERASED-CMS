<?php
declare(strict_types=1);

namespace ErasedContactForm\Admin;

require_once __DIR__.'/../Domain/ContactSubmissionRepository.php';

use ErasedContactForm\Domain\ContactSubmissionRepository;

final class ContactSubmissionsAdminRoute
{
    private readonly ContactSubmissionRepository $submissions;

    public function __construct()
    {
        $this->submissions = new ContactSubmissionRepository(db());
    }

    public function handle(string $id = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        if ($id !== '' && str_ends_with($path, '/delete')) {
            $this->delete((int)$id);
            return;
        }
        if ($id !== '') {
            $this->view((int)$id);
            return;
        }
        $this->list();
    }

    private function list(): void
    {
        $rows = $this->submissions->all();
        $html = '';
        foreach ($rows as $row) {
            $isUnread = $row['read_at'] === null;
            $subject = trim((string)$row['subject']) !== '' ? (string)$row['subject'] : '(no subject)';
            $html .= '<a class="admin-row" href="/admin/contact-form/submissions/'.(int)$row['id'].'" style="text-decoration:none;color:inherit"><div class="admin-row-body">'
                .'<div class="admin-row-title">'.($isUnread ? '<span class="badge" style="margin-right:8px">New</span>' : '').e((string)$row['name']).' &mdash; '.e($subject).'</div>'
                .'<div class="admin-row-meta">'.e((string)$row['email']).' &middot; '.e((string)$row['created_at']).'</div>'
                .'</div></a>';
        }

        $unread = $this->submissions->unreadCount();
        $body = '<div class="title-row"><div><p class="kicker">SHEET CF-01 &middot; CONTACT FORM</p><h1>Contact form messages</h1>'
            .'<p>Messages sent through the <code>[contact_form]</code> shortcode.'.($unread > 0 ? ' <strong>'.$unread.' unread.</strong>' : '').'</p></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All submissions</h2></div><div class="panel-body"><div class="admin-row-list">'
            .($html !== '' ? $html : '<div class="admin-row-empty">No submissions yet.</div>').'</div></div></div>';
        layout('Contact form messages', $body, true);
    }

    private function view(int $id): void
    {
        $row = $this->submissions->find($id);
        if ($row === null) {
            erased_public_not_found();
            return;
        }
        $this->submissions->markRead($id);

        $subject = trim((string)$row['subject']) !== '' ? (string)$row['subject'] : '(no subject)';
        $body = '<div class="title-row"><div><p class="kicker">SHEET CF-01 &middot; CONTACT FORM</p><h1>'.e($subject).'</h1>'
            .'<p>From '.e((string)$row['name']).' &lt;'.e((string)$row['email']).'&gt; &middot; '.e((string)$row['created_at']).' &middot; '.e((string)$row['ip_address']).'</p></div>'
            .'<div><a class="btn ghost" href="/admin/contact-form/submissions">Back to list</a></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body">'
            .'<p style="white-space:pre-wrap">'.e((string)$row['message']).'</p>'
            .'<form method="post" action="/admin/contact-form/submissions/'.$id.'/delete" onsubmit="return confirm(&quot;Delete this submission?&quot;)" style="margin-top:18px"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn ghost">Delete</button></form>'
            .'</div></div>';
        layout('Contact Submission', $body, true);
    }

    private function delete(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/contact-form/submissions');
        }
        verify_csrf();
        $this->submissions->delete($id);
        audit('contact_form.submission.delete', ['id' => $id]);
        flash('success', 'Submission deleted.');
        redirect('/admin/contact-form/submissions');
    }
}
