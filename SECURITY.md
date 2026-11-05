# Security policy

SightPress is built for blind and low-vision creators and audiences, and many
of them rely on it every day. We take security reports seriously.

## Reporting a vulnerability

Please **don't open a public issue** for a security problem. Report it privately
in one of these ways:

- Use GitHub's "Report a vulnerability" button on the **Security** tab of this
  repository (private vulnerability reporting).
- Or email `security@sightpress.org`.

Plain text is fine. Please tell us:

1. What the problem is and where it is (repository, file, route or command).
2. The steps to reproduce it, or a proof of concept.
3. What an attacker could do with it.
4. Your name or handle if you'd like credit.

You'll get an acknowledgement within 3 working days and a first assessment
within 10 working days. We'll keep you informed until it's fixed, and we'll
credit you in the release notes unless you prefer not to be named.

## Supported versions

Security fixes go to the latest minor release of each supported major version.
Before 1.0, only the latest release is supported.

## Scope

In scope: this repository's code and its default configuration. Please also
tell us about:

- Weak or missing access checks (voters, capabilities, site isolation).
- Missing CSRF protection on a state change.
- Unescaped output, or `|raw` on content that isn't sanitised.
- Secrets that reach the database, logs, exports or API responses.
- Security controls that can't be used with assistive technology. We treat
  those as security bugs too.

Out of scope: vulnerabilities in third-party packages that have no fix yet
(please report those upstream), and attacks that need an operator to install
untrusted code on purpose.

## Disclosure

We ask for a reasonable time to fix the problem before you share it publicly.
We publish an advisory with every security release.
