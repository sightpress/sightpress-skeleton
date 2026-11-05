# Contributing

Thank you for helping. SightPress is a powerful CMS for everyone, designed with
blind and low-vision creators and audiences in mind. Contributions are licensed
under **GPL-2.0-or-later**, like the rest of the project.

## Before you start

- Read the rules in `CLAUDE.md` at the workspace root. They apply to every
  change, human or AI-assisted.
- Accessibility can't be turned off. A change that breaks the axe or pa11y gate,
  or that needs a mouse or sight, won't be merged.
- Every UI string is translated into all 11 launch locales in the same change.
  Machine drafts stay `state="needs-review-translation"` until a fluent
  reviewer checks them.
- Never loosen a quality gate, a performance budget or a test to get CI green.

## Making a change

1. Open an issue first for anything bigger than a small fix.
2. Work in a branch. Keep the change small and focused.
3. Run the quality gates in the repository you changed:

   ```bash
   composer install
   composer qa              # PHPUnit, PHPStan, PHP-CS-Fixer, Rector (dry run), Deptrac
   composer audit
   ```

   If you touched templates, also run the Playwright + axe suite
   (`npx playwright test`). If you touched a list, a query or front-end assets,
   check the performance budgets.
4. A change that spans repositories lands in dependency order: `plugin-core`,
   then `core`, then plugins, then `skeleton`.
5. Open a pull request that says what changed and why, and how you tested it
   with a keyboard and a screen reader where it touches the interface.

## Code of conduct

Be kind, be patient, and assume good faith. Describe things in words, not only
in images or screenshots, so everyone can take part.
