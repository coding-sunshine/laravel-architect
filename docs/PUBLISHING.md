# Publishing Laravel Architect

This guide covers tagging a release and publishing the package to Packagist.

## Prerequisites

- Maintainer access to [coding-sunshine/laravel-architect](https://github.com/coding-sunshine/laravel-architect) on GitHub
- Packagist account linked to the GitHub repository
- Composer and Git configured locally

## Release Checklist

1. **Update version and changelog**
   - Bump version in `composer.json` (`version` field)
   - Add release notes to `CHANGELOG.md` under a new `## [x.y.z] - YYYY-MM-DD` section

2. **Run quality checks**
   ```bash
   composer test
   vendor/bin/pint --test
   vendor/bin/phpstan analyse
   ```

3. **Commit and push**
   ```bash
   git add .
   git commit -m "Release v1.0.0"
   git push origin main
   ```

4. **Create a Git tag**
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```
   Use semantic versioning: `vMAJOR.MINOR.PATCH` (e.g. `v1.0.0`, `v1.1.0`, `v2.0.0`).

5. **Publish on Packagist**
   - If the package is not yet on Packagist: go to [packagist.org](https://packagist.org), submit the repository URL, and complete the form
   - If the package already exists: Packagist will auto-update from GitHub when you push a new tag (if auto-update is enabled). Otherwise, use “Update” on the package page

6. **Verify**
   ```bash
   composer show coding-sunshine/laravel-architect
   ```
   Install in a fresh project:
   ```bash
   composer require --dev coding-sunshine/laravel-architect
   ```

## GitHub Releases (optional)

- On GitHub, go to **Releases** → **Draft a new release**
- Choose the tag you pushed (e.g. `v1.0.0`)
- Paste the relevant part of `CHANGELOG.md` as the description
- Publish the release

## After Publishing

- Announce the release (e.g. Twitter, Laravel News, project README)
- Update the Packagist badge in `README.md` if needed (version is usually automatic)
