# SightPress

A powerful multi-site, multi-language CMS for everyone, designed with blind and
low-vision content creators and audiences who use assistive technology in mind.
Written in modern, object-oriented PHP. Every site comes
with a profile, a logo and favicons, generated legal pages (about us, contact,
accessibility, privacy, terms of use) and built-in SEO and AIO (AI
optimisation). Licensed GPL-2.0-or-later.

> **Status:** pre-M0. The steps below describe the target setup.

This repository is the **deployable project** (`sightpress/skeleton`). It holds
only the application shell, its configuration and Docker support. The product
itself comes from Composer packages: `sightpress/core` and the official plugins.

## Install

```bash
composer create-project sightpress/skeleton my-site && cd my-site
```

Or, with Docker for development:

```bash
git clone https://github.com/sightpress/sightpress-skeleton my-site && cd my-site
docker compose up -d        # Postgres, Mailpit, MinIO (development)
composer install
```

When `composer install` finishes, it runs `php bin/console sightpress:install`,
which asks for:

1. **System setup:** environment (prod or dev), database, mail, media storage
   and delivery, the super-admin account, and which installed plugins sites may
   use.
2. **Your first site:** name, domain, languages and fallbacks, owner, site
   profile (who runs the site and how to reach them), logo and favicon (or a
   generated one), theme preset, media hostname, which plugins to turn on, and
   an optional home page. The five legal pages are created from the profile; review them before
   launch.

The installer works with screen readers: one plain-text question at a time,
defaults stated in words, numbered choices and progress shown as text.

- Re-run or resume it at any time with `php bin/console sightpress:install`.
- Add more sites later with `php bin/console sightpress:site:create`, or in
  Admin → Sites.
- In CI or Docker builds (no terminal), the hook skips without failing. For an
  unattended install, pass answers as options or `SIGHTPRESS_*` environment
  variables. Give secrets through environment variables or `*_FILE` variables,
  never as command-line options.
- Set `SIGHTPRESS_SKIP_INSTALL=1` to skip the hook.

### Media storage and CDN

Every site has an accessible media library. Its files can be kept on this
server's filesystem or in cloud storage (any S3-compatible service, Google Cloud
Storage, Azure Blob), and served by this server or through a CDN. The installer
can set up AWS (S3 + CloudFront), Cloudflare (R2 and its CDN), Google Cloud CDN, Azure Front
Door, Bunny.net, DigitalOcean Spaces or Fastly **through the provider's API**: it
creates or reuses the bucket and CDN, requests the certificate, prints any DNS
records to add, and tests everything before switching over. Any other CDN works
with the generic option.

- Give provider credentials as environment variables (for example
  `CLOUDFLARE_API_TOKEN` or `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`) or
  `*_FILE` variables. The installer prints the minimum permissions they need.
- Preview the changes without making them with `--cdn-plan`.
- Set up or change a CDN later with `php bin/console sightpress:cdn:setup`, or in
  Admin → System → Storage and CDN.

See `PLAN.md` §3.4 for the full specification.

### Email and notifications

SightPress always sends the email an install needs (invitations, password
resets, security alerts and system alerts), using the mail settings from the
installer.

Everything else people hear about (a page to review, a comment to moderate, a
new form submission) comes from the official **notifications** plugin. Turn it
on per site in Admin → Plugins. It sends by email, and also by browser push or
text message once those are set up. Each person chooses what reaches them, and
how, in their notification preferences.

- **Browser push** needs no provider account: turning it on generates the keys
  it needs. It requires HTTPS.
- **Text messages** need an SMS provider. The plugin can connect Twilio,
  Vonage, Amazon SNS, Infobip, Sinch, Brevo, Bird or OVHcloud; give the
  credentials as environment variables (for example `TWILIO_ACCOUNT_SID` and
  `TWILIO_AUTH_TOKEN`, or `SIGHTPRESS_SMS_DSN`) or `*_FILE` variables. Each site
  can limit which notifications use SMS and how many it sends each month.
- Both are off by default. Set them up with
  `php bin/console sightpress:notifications:setup`, or in Admin → System →
  Notifications; the installer lists this command as a next step.

See `PLAN.md` §5.14 for the full specification.

## Finding images

The official **image-finder** plugin lets creators search free and paid image
providers from the media library and the editors, with every result described
in words, and add the image they choose to the site with its credit and
licence. Turn it on per site in Admin → Plugins.

- **Openverse** and **Wikimedia Commons** need no account and work as soon as
  the plugin is on.
- **Pexels** and **Pixabay** need a free API key; **Shutterstock**, **Adobe
  Stock** and **Getty Images/iStock** need your own paid account. Give keys as
  environment variables or `*_FILE` variables, then connect them with
  `php bin/console sightpress:image-finder:setup`, or in Admin → System →
  Image providers; the installer lists this command as a next step.
- Each site chooses its providers, which licences it accepts, and a monthly
  spending limit for paid images.

See `PLAN.md` §5.18 for the full specification.

## Docker

- `compose.yaml` runs the development services: PostgreSQL, Mailpit and MinIO
  (S3-compatible storage).
- `Dockerfile` builds the production image (PHP with OPcache and preloading,
  the web server, and the background worker and scheduler as separate services
  in `compose.prod.yaml`). The image build never runs the installer; run
  `php bin/console sightpress:install` in the container, or give the answers as
  `SIGHTPRESS_*` environment variables and Docker secrets (`*_FILE`).

## Upgrade

```bash
composer update "sightpress/*"
php bin/console doctrine:migrations:migrate
```

Your project holds only configuration, so updating the core and plugins never
conflicts with local files. Read the release notes for any change to copy into
the skeleton files themselves (Docker or config).

## Plugins

The official plugins are installed by default and off on every new site. Turn
them on per site in Admin → Plugins. Remove one with `composer remove
sightpress/plugin-<id>`, or add a third-party plugin with `composer require`.

## Security and performance

SightPress is secure and fast by default: strict security headers and Content
Security Policy, CSRF on every change, passkeys and two-step verification, no
third-party scripts or trackers, HTTP caching with precise purges, and
performance budgets checked in CI. Before going live, run
`php bin/console sightpress:security:check`, which reports each check as
"Passed" or "Failed".

To report a vulnerability, follow `SECURITY.md` (added in M0); please don't
open a public issue. See `PLAN.md` §3.5 (security) and §3.6
(performance) for the full specification.
