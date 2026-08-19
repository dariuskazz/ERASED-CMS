# ERASED CMS — Capabilities & Functions

A complete inventory of what ERASED CMS can do today, compiled directly from the codebase (routes, core engine functions, and the platform subsystems) rather than from marketing copy. Where something is configured but not yet functionally wired up (e.g. payment checkout), that's called out explicitly.

Plain-procedural PHP 8.3, no framework. Four account roles: **Administrator**, **Editor**, **Writer**, **User**.

---

## 1. Content & Publishing

- **Posts and Pages** — a single `content` table serves both, distinguished by `type`. Writers can create and edit only their own; Editors and Administrators can edit/publish/delete anything.
- **Drafts and publishing** — every save can be kept as a draft or published; Writers can save drafts but need Editor/Admin to actually publish.
- **Revisions** — every edit snapshots the previous title/body into a `revisions` table before overwriting, with a full revision history browsable from Publishing.
- **Scheduling** — set a future `publish_at` timestamp; content only appears once that time passes.
- **Access levels per content item** — Everyone / Registered users / Active members / Paid access.
- **Categories & tags** — free-text category and comma-separated tags per item, with public archive links generated automatically (`/posts?category=...`, `/posts?tag=...`).
- **Per-content SEO** — SEO title, meta description, and canonical URL, independent of the site-wide defaults.
- **Redirects** — a redirects table with configurable status codes (visible under Publishing → Redirects).
- **Page templates** — a `page_template` field per content item (e.g. sidebar layout).
- **Featured media** — attach a media item as a post/page's featured image.
- **Homepage post ordering** — drag-to-reorder which published posts appear first on the homepage (`/admin/homepage/reorder`), independent of publish date.
- **Multi-language content** — each content item carries a `language_code`.
- **WordPress import** — upload a WXR/XML export; posts and pages, HTML body, dates, slugs, and publish status are imported automatically.

## 2. Media Library & Galleries

- **Media Library** — drag-and-drop upload for images, video (mp4/webm/ogg), and PDFs, with per-file alt text and captions, a searchable/filterable browser (images / videos / other), and direct file URLs.
- **Secure media serving** — files are served through hashed URLs (`/media/{hash}`), with strict MIME/extension checking on upload and automatic SVG sanitization (strips inline scripts and `onerror` handlers) available as a toggle.
- **Video streaming** — dedicated byte-range (`Range:` header) streaming endpoint for video playback/seeking.
- **Photo Galleries** — group any set of uploaded photos into a named, sluggable public gallery (`/gallery/{slug}`) with per-photo captions, a chosen cover image, draft/published status, and a public lightbox. Optionally shown in site navigation.

## 3. Public Site

- **Homepage** — driven by the Homepage Studio / Layout Studio system (section 10) with automatic fallback to a flat legacy renderer if no published layout exists.
- **Posts index / search** — `/posts` lists published posts with free-text search (title/excerpt/body/category/tags) and category/tag filtering.
- **Custom 404 page** — optional custom image for the not-found page.
- **Comments** — public comment form on content (when enabled), with optional math or Cloudflare Turnstile CAPTCHA, spam heuristics (e.g. repeated-character detection), and moderation before anything appears.
- **Announcement bar** — a dismissible site-wide banner (static or scrolling marquee), with custom icon/colors/link, audience targeting (everyone / guests / logged-in users / admins only), and a scheduled show-from/show-until window.
- **Maintenance mode** — hides the public front-end from non-admin visitors.
- **Custom CSS** — a free-text field injected site-wide without touching source files.
- **Newsletter subscribe/unsubscribe** — public subscribe form with double-opt-in-style token confirmation and a one-click unsubscribe link.

## 4. Comments Moderation

- Queue with counts for All / Approved / Pending / Spam, full-text search across author/email/body/post title.
- Per-comment approve / unapprove / mark spam / delete, plus bulk actions on a multi-select.
- Admin can reply directly to a comment thread from the moderation screen (posted as an already-approved reply).

## 5. Users, Roles & Permissions

Four roles, each a fixed permission bundle (`role_permissions()` in `app/bootstrap.php`):

| Role | Can do |
|---|---|
| **User** | Log in, read permitted content. No admin/content capabilities. |
| **Writer** | Create posts, edit/delete only their own, manage media. Drafts require Editor/Admin to publish. |
| **Editor** | Full content lifecycle (create/edit/publish/delete, any author), media, comments, publishing tools. |
| **Administrator** | Everything above plus users, languages, memberships, payments, packages, import, backups, security, settings. |

- **Account management** — create/edit accounts, set role, activate/deactivate, force a password reset, enable email two-factor authentication (admin accounts only).
- **Self-protection** — an admin cannot demote or deactivate their own account.
- **Password policy** — configurable minimum length and character-class requirements (uppercase/lowercase/number/symbol), enforced on every password set.

## 6. Authentication & Account Security

- **Login** — email + password, with rate limiting and automatic temporary IP lockout after repeated failures (both thresholds/windows configurable).
- **Two-factor email verification** — for admin accounts: a six-digit, single-use, 10-minute code emailed at login.
- **Forgot / reset password** — tokenized reset link (60-minute expiry, rate-limited requests), and resetting a password revokes every other active session for that account.
- **Session management (Security Center)** — view every active session with IP and last-activity time; force-logout a single user or force-logout everyone at once.
- **Cloudflare Turnstile** — optional bot-check widget on the login form (and comments, if enabled).
- **Audit log** — every meaningful admin action (`audit('...')`) is recorded with actor, IP, and timestamp; visible in Security Center.
- **Login history** — successes and failures logged separately with IP/user-agent, browsable in Security Center.

## 7. Appearance & Theming

Two entirely independent theming surfaces — changing one never touches the other:

- **Admin Panel Theme** (`/admin/themes`) — 4 built-in themes (Dark Green, Dark Blueprint - Cyanotype, Light Blueprint - Cyanotype, Ops Deck) plus any custom uploaded admin theme package.
- **Website Theme** (`/admin/appearance/website-theme`) — 3 built-ins (Dark, Dark Green, Light Grey) plus any uploaded website theme package. Controls the public site's colors/fonts/spacing only — structural layout (nav, homepage grid, article/comment markup) is shared.
- **Custom theme upload** — a ZIP with a `package.json` manifest (`type: "theme"`, `theme_scope: "admin"|"website"`) plus a `.css` asset file, installed through the same secure Package Engine pipeline as any other package. Live preview before committing.
- **Branding & Identity** — separate logo uploads for dark vs. light/grey themes, favicon, configurable logo width/height, and a toggle to show the site title next to the logo.

## 8. Homepage Studio & Layout Studio (Page Builder)

- **ERASED Studio** (`/admin/studio`) is the unified hub: Layout, Navigation, Theme, Typography & SEO, and Media as tabs of one screen, each tab the same real, already-working page embedded chrome-free (not a separate reimplementation) - so everything below is reachable either directly or through Studio's Layout tab.
- **Visual drag-and-drop editor** for the homepage: a 3-region grid (left/center/right), each region holding one or more "containers," each container holding one or more side-by-side blocks (100% / 50-50 / 3×33% layouts).
- **6 built-in section types**: Feature Grid, Featured content, Latest posts, Categories, Popular tags, Latest comments — Feature Grid has fully editable rich content (headline, subtitle, CTA text/URL, accent color, background style, alignment) through the Inspector panel; the other 5 are fixed content blocks. Installed packages can add further section types (rich or plain) through the same picker — see Plugin-provided blocks below.
- **Plugin-provided blocks** — an installed package can declare its own homepage block (`homepage_blocks` manifest field) with its own rendering service; it appears in the same picker/palette as the built-ins and renders live via the package's own code (see Package Engine, section 12).
- **Draft/publish workflow** — edits autosave as a draft (with optimistic-concurrency revision checks), a separate Publish action pushes them live; Undo/Redo history within the editor session.
- **Live Preview tab** — see the actual rendered page (including plugin blocks) inside the editor before publishing, at Desktop/Tablet/Mobile viewport widths.
- **Per-container options** — visibility toggle, hide-on-mobile / hide-on-desktop, scheduled show-from/show-until window, background color, padding, duplicate, delete.
- **Capability-aware hide-not-delete** — if a block's owning package or required capability becomes unavailable, its saved placement stays intact but is simply not shown, so nothing is ever silently lost.
- **Website Layout & Look settings** — column preset (1/2/3 columns), sidebar widths, column gap, max page width, widget gap, sticky sidebars.
- **Per-profile homepage layouts** — each Website Profile (section 14) can have its own independent homepage layout, auto-seeded from a starter preset matching its website type (or cloned from the default layout) the first time it's activated.

## 9. Settings

Four tabs under `/admin/settings`:

- **General** — site title, tagline, website language, timezone, date format, header/footer text.
- **Publishing & Content** — homepage content source, posts page, posts-per-page, default comment/CAPTCHA behavior, public registration toggle.
- **SEO & Social** — default SEO title/description, GitHub/X/YouTube social links.
- **Advanced** — announcement bar (full config), custom CSS, maintenance mode.

Plus dedicated screens for **Email**, **Branding**, **Website Theme**, **Website Profiles**, **Languages**, **Payments**, **Users**, **Security** — each documented in its own section here.

## 10. Multilingual / Translations

- **Any number of languages** can be added by ISO code (e.g. `de`, `pt-br`), each with an English name and native name, RTL flag support.
- **Separate website and admin-panel language selection**, with an option to sync them together.
- **Browser language auto-detection** for visitors (optional).
- **Per-key translation editor** — every UI string (grouped as "site" or "admin") is editable with the English fallback shown alongside, searchable in-page.
- **Translation coverage tracking** — a per-language percentage-complete stat, and site-wide overall coverage.
- **Export** — download any language's translation file as JSON.
- **Language switcher** — optional visitor-facing selector in the public navigation.
- **Language Packs are real installable packages** (`type: "language"`, Package Engine, section 18) - install/enable/disable/uninstall a language the same way as a theme or plugin; a **Translation Base generator** produces the starter JSON for a new language, and any installed language can be exported as a ready-to-share ZIP. Ukrainian ships as the first real installed language pack.

## 11. Email & Newsletter

- **Two transports**: PHP `mail()` or SMTP (host/port/security/username/password).
- **Configurable from-name/from-email** and a test-send button that doesn't require leaving the settings screen.
- **Newsletter** — subscriber list (active/total counts), compose-and-send to all active subscribers with an automatically appended unsubscribe link, campaign history (subject, recipient count, sent/failed counts, timestamp).
- Password-reset and two-factor emails route through the same configured transport.

## 12. Payments (configuration layer)

- **5 provider slots**: Stripe, PayPal, Vipps MobilePay, Klarna, and manual bank transfer — pick one active provider at a time, each with its own credential fields (stored, secrets never re-displayed after saving).
- **Test / Live environment toggle**, configurable currency, statement descriptor, and success/cancel/webhook URLs.
- **"Validate required fields"** action checks that all required credentials for the chosen provider are filled in (does not contact the provider).
- **Transaction log & export** — a `payment_transactions` table with CSV/TXT export (recent 50 or full history).
- **Honest scope note baked into the UI itself**: this screen stores provider settings for all 5 providers, but only manual bank transfer has a real checkout wired to it (via the `erased.commerce` package, section 20) — the other 4 providers' credentials are stored, not live; checkout refuses rather than fakes acceptance for any of them.
- **Ships as `erased.payments`, a real installed package** (Package Engine, section 18) rather than a hardcoded core screen - the same `/admin/payments` URL and settings keys, now owned by a package; a site that hasn't installed it sees a short placeholder pointing at Packages instead of a bare 404.

## 13. Memberships

- Create membership plans (name, price, currency, billing interval: month/year/one-time).
- Manually grant a membership to any user account against a plan (a "manual" provider reference, for cases without a live payment gateway).
- Foundation for gating content at the "Active members" access level (section 1).

## 14. Website Profiles

- A **website profile** is a named, switchable bundle of site-identity settings: site name, tagline, accent color, admin theme, website theme, header layout, admin-nav label, SEO description, footer text — plus its own independent homepage layout.
- **11 starter website types** ship out of the box: News Portal, Blog, Business, Portfolio, Gallery, Video, Web Shop, Community, Documentation/Wiki, Landing Page, and Custom — each with a recommended-modules list and (for several) a dedicated homepage section preset.
- **Draft profiles** can be created and fully edited before ever going live.
- **Activation snapshots** the previously-active configuration automatically, so any activation is reversible — restoring a snapshot is one click.
- **Preview** — see how site chrome (header, nav, footer, accent color) would look with a draft profile active, without publishing anything.
- Switching profiles never touches content or installed packages — only the identity/appearance bundle.

## 15. Security Center

Five tabs under `/admin/security`:

- **Dashboard** — an automated security scanner with a 0–100 score, one-click "Apply Safe Fix" for common issues (enable WAF, enable IP lockout, strengthen password policy), plus the recent audit trail.
- **Login & Sessions** — rate-limit and IP-lockout thresholds, password policy, session inactivity timeout, active-session list with force-logout, active IP lockouts with manual unlock, full login history log.
- **Site & Upload Protection** — CAPTCHA on comments, security headers (CSP/HSTS/X-Frame-Options/X-Content-Type-Options) toggle, strict upload MIME/extension checking, automatic SVG sanitization, plus a file-permission/directory audit panel.
- **WAF & Monitoring** — web application firewall toggle (SQLi/XSS/path-traversal filtering), bot/scraper filtering, admin IP allowlist, IP blacklist, a blocked-threats log, and a live security-event stream.
- **Advanced & Recovery** — Emergency Lockdown (blocks all non-admin logins), Read-Only Mode (freezes all database mutations site-wide), the Developer Mode toggle (section 17), plus quick links to email 2FA management and the Backup Center.
- **Cloudflare integration** — trust `CF-Connecting-IP` for accurate visitor IPs behind Cloudflare, Cloudflare Turnstile CAPTCHA keys, and an edge-cache purge API (zone ID + token) with auto-purge-on-publish and a manual "purge now" button.

## 16. Import & Backups

- **WordPress import** — see section 1.
- **Database and media backups** — one-click full SQL dump paired with a ZIP of uploaded media; download, restore (with an explicit overwrite-confirmation, quote-aware SQL parsing, and a real success/failure report rather than a silent best-effort), or delete any backup archive from the list. Automatic retention (configurable count, default 10) prunes old backups; backup files are permission-locked (not world-readable) on disk.

## 17. Developer Tools

- **`/admin/developer`** — a read-only Developer Mode screen (off by default, gated behind a dedicated setting plus admin permission) showing: installed/enabled packages and their dependencies, every registered capability and which package currently resolves it, every registered service, all Homepage Studio blocks with live visibility status, the active Website Profile's capability-vs-recommended-module status, and the platform's documented lifecycle event names.

---

## 18. The Package / Plugin Engine

The extensibility backbone underneath most of the above. A **package** is a ZIP file containing a `package.json` manifest plus (optionally) PHP code, assets, and a lifecycle handler.

- **6 package types**: `module`, `theme`, `language`, `website-type`, `homepage-preset`, `widget`.
- **Manifest fields**: id, type, name, version, minimum-CMS-version required, author, description, dependencies, `provides` (capabilities this package offers), `requires_capabilities` (what it needs to run), `services` (classes it exposes), `homepage_blocks` (homepage sections it registers), and theme-specific fields (`theme_scope`, `assets`).
- **Full lifecycle**: install → enable → disable → update → uninstall (with a choice to keep or delete its data) → rollback on a failed install/update.
- **Dependency resolution** — install order respects declared dependencies; missing or circular dependencies are rejected before anything touches disk.
- **Secure ZIP handling** — path-traversal and unsafe-path rejection during archive inspection, isolated staging before anything is promoted to a live install, automatic backup-and-restore if an update fails partway, and a bounded-copy extraction path that rejects a decompression-bomb-style entry (declared size lying about real inflated size) mid-write rather than after.
- **File-integrity checks** — a SHA-256 manifest of every file is recorded the moment a package is installed or updated; "Verify integrity now" on the package detail page re-hashes on demand and reports drift (changed/missing/unexpected files) - scoped deliberately to integrity, not cryptographic signatures or publisher trust, since every package today is first-party.
- **Capability enforcement** — a package `requires_capabilities` an unmet capability is rejected outright at install/enable time (not just validated and ignored).
- **Verified against a real attack payload**: a ZIP with a smuggled PHP file installs harmlessly — the file lands on disk but is confirmed unreachable and never executed through any code path.

## 19. Platform Foundation (for plugin/package authors)

The lower-level machinery packages plug into — mostly invisible to a site owner, essential for anyone writing a package:

- **Capability Resolver** — packages declare capabilities they `provide` and `require`; the resolver tracks which capability is currently satisfied by which active package, enforced at install/enable time.
- **Service Container** — a package can declare PHP classes as "services" (`services` manifest field: file + class + shared/not-shared); other code resolves them by id through a shared per-request container, instantiated via reflection from inside the package's own sandboxed directory.
- **Event System** — six lifecycle events fire on real state changes: `package.installed`, `package.updated`, `package.enabled`, `package.disabled`, `package.uninstalled`, `capability.provider.changed`. Any code (including a future plugin) can listen for these.
- **Homepage Block Registry** — the single source of truth for "what homepage section types exist," combining the 6 built-ins with anything a package registers; supports capability-aware filtering that hides (never deletes) a block whose requirement isn't currently met.

## 20. What isn't built yet

Documented explicitly here for accuracy, not glossed over:

- **ERASED Commerce** — products (physical, digital with purchase-gated file delivery, and admin-managed subscription periods), storefront, cart, checkout (manual bank transfer only), orders, coupons, a category tree with a storefront category rail, per-product view stats, and a flat tax/shipping model are built; collections/brands/variants, invoices/returns/reviews, automated recurring billing, and live Stripe/PayPal/Vipps/Klarna API integration remain unbuilt.
- **Sitemap.xml, RSS/Atom feeds, and robots.txt** — none of these exist today.
- **Public plugin routes** — a package can register its own admin-panel routes/menu/permissions and can be resolved through the service container for a hardcoded public route (as ERASED Commerce's storefront is), but there is no generic `public_routes` manifest field yet - deferred until a second package genuinely needs one.
- **Real license authenticity validation for paid packages** — a package can declare `pricing`/be gated on a locally-activated key before it can be enabled, but ERASED CMS has no license server; validating a key is genuine (not just present) is a documented extension seam (`LicenseGate`) for a real commercial distributor to implement, not something core does itself.

---

*Compiled by direct source inspection (`routes/`, `app/bootstrap.php`, `app/Packages`, `app/Capabilities`, `app/Container`, `app/Events`, `app/Homepage`, `app/LayoutStudio`, `app/Website`) rather than from `ROADMAP.md` narrative alone — cross-check that file for what's mid-flight versus finished.*
