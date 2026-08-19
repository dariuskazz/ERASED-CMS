<?php
declare(strict_types=1);

namespace ErasedContactForm\Frontend;

/**
 * Renders the [contact_form] shortcode. Deliberately reuses the site's own
 * generic input/textarea/button/.card/.grid styling (public-site.css)
 * rather than shipping bespoke CSS - this form should look like a native
 * part of the page, not a plugin widget bolted on.
 *
 * The honeypot field and POST target mirror the comment form's own
 * anti-spam convention exactly (public/index.php's /comments/submit) so
 * both forms are consistent for a returning visitor and for anyone reading
 * the page source.
 */
final class ContactFormBlock
{
    public function render(): string
    {
        $name = '';
        $email = '';
        if (function_exists('logged_in') && logged_in() && function_exists('current_user')) {
            $user = current_user();
            $name = (string)($user['display_name'] ?? '');
            $email = (string)($user['email'] ?? '');
        }

        // Start time recorded server-side in the session, not a client
        // hidden field - a hidden field's value can be trivially forged by
        // a bot to always pass the "not submitted too fast" check.
        $_SESSION['contact_form_started'] = time();
        $returnTo = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        return '<form class="card contact-form" method="post" action="/contact-form/submit">'
            . '<input type="hidden" name="csrf" value="' . csrf() . '">'
            . '<input type="hidden" name="return_to" value="' . e($returnTo) . '">'
            . '<label class="hp-field" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">Website<input name="website" tabindex="-1" autocomplete="off"></label>'
            . '<div class="grid">'
            . '<label>Name<input name="name" maxlength="190" value="' . e($name) . '" required></label>'
            . '<label>Email<input type="email" name="email" maxlength="190" value="' . e($email) . '" required></label>'
            . '</div>'
            . '<label>Subject<input name="subject" maxlength="190"></label>'
            . '<label>Message<textarea name="message" minlength="3" maxlength="5000" required></textarea></label>'
            . (function_exists('erased_comment_captcha_widget') ? erased_comment_captcha_widget() : '')
            . '<button type="submit">Send message</button>'
            . '</form>';
    }
}
