# Incident: erased.no admin panel unstyled after core update (2026-08-19)

## Summary

Applying a core-update package to `erased.no` (real cPanel/LiteSpeed shared hosting, account-root
document layout — not the `public/`-as-docroot layout the local podman dev container uses) left the
site in a broken state: the public homepage and admin panel rendered with little to no CSS applied.
Recovery took several passes because two unrelated problems were stacked on top of each other.

## Root causes

1. **`public/assets` and `public/assets/editor` directory permissions were `750` instead of `755`.**
   PHP pages still rendered (PHP executes as the account owner via suexec, which has full access
   regardless), but the web server's static-file-serving path runs under a more restricted context
   that needs "other" read+execute on every directory it walks through. With `750`, any direct
   request for a file under `public/assets/` (CSS, JS, images) silently fell through the site's
   own rewrite chain into `index.php`, which correctly treated the request as an unrecognized
   route and served the ordinary "Page not found" page — a response that looks like a normal 404,
   not an obvious permissions error, which is why this took a while to isolate.

   Confirmed via the server's error log (`Metrics → Errors` in cPanel, or an `error_log` file at the
   account root): repeated `[HTAccess] Failed to open [.../public/assets/.htaccess]: Permission
   denied` entries pointed at the right directory well before the fix was identified.

   **Fix**: set `public/assets` and `public/assets/editor` to `755`. No other directory needed
   changing — the rest of the tree (`app/`, `routes/`, `config/`, etc.) is only ever read by PHP
   itself, which was never affected.

2. **`public/assets/admin-design-system.css` itself was a truncated leftover from the original
   incident.** Once directory permissions were fixed, the site started actually serving the file
   that existed on disk — which turned out to be cut short partway through (roughly the first
   4KB of a ~103KB file, ending right after the built-in theme color-variable declarations, before
   any of the actual layout/component rules). This meant color variables were defined but nothing
   that used them for layout ever loaded, which is why the page showed *some* color/font styling
   (bleeding through from a separately-loaded theme package stylesheet) while everything else was
   unstyled and misaligned.

   **Fix**: replaced the file with the complete, current version from the source tree.

## How this was actually diagnosed

Trial-and-error on the live site (permission guesses, cache-busted URLs, browser DevTools Network
tab inspection of the actual request/response for `admin-design-system.css`) went in circles for a
while because the two root causes produced overlapping symptoms and each fix only partially
resolved things. The turning point was comparing a full file/permission snapshot pulled from the
live server (via a zip export from cPanel File Manager) against the local source tree — that
surfaced the `750` permissions directly, since cPanel's own permissions dialog only showed the
parent `public/` folder (`755`, correct) and it wasn't obvious the subdirectories needed checking
individually.

**For next time**: if a similar "admin panel loads but has zero styling, public site is partially
styled" symptom shows up again on `erased.no`, check in this order:
1. Directly request the CSS URL in a fresh incognito window (rules out browser/CDN caching).
2. If that 404s or errors, check the server error log for `HTAccess ... Permission denied` lines —
   don't just check the immediate parent folder's permissions in the File Manager UI, check every
   directory in the path down to the file.
3. If the CSS *does* load standalone but the admin page still looks wrong, check the file's actual
   byte size against the source tree — a truncated file is a separate failure mode from a
   permissions/serving failure and looks similar from the outside.

## Known minor residual issue

A handful of `content:"▾"` values (small dropdown-arrow characters, used in a few disclosure
widgets) got mis-encoded to `content:"â–¾"` during a manual copy/paste into cPanel's file editor —
a charset mismatch (the editor appears to save as Latin-1 rather than UTF-8 for pasted content in
some cases). Purely cosmetic — affects only the glyph shown in a few small toggle arrows, not
layout or functionality. Not yet fixed; safe to leave as-is or clean up whenever convenient.
