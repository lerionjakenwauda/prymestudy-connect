# Publishing PrymeStudy Connect packages

This document covers the registry steps for a tagged PrymeStudy Connect SDK release.

The public protocol and SDK source live in this repository. Production access to PrymeStudy remains separately approval-gated through the production-readiness and partner-certification process.

## Release order

1. Merge the prepared release commit to `main`.
2. Confirm **Connect CI** and **Connect Security** are green on the exact main commit.
3. Create and push the version tag, for example `v1.0.0`.
4. Wait for **Connect Release** to create the GitHub release and checksummed artifacts.
5. Publish npm and PyPI packages from the exact tag using **Publish Connect Packages**.
6. Submit/refresh the Composer package on Packagist using this repository.
7. Test all public install commands from empty projects.
8. Run the matching PrymeStudy server deployment and partner-certification gates before enabling any production institution.

## Git tag and GitHub release

The release workflow runs only from an immutable semantic-version tag:

```bash
git switch main
git pull --ff-only origin main
git tag -a v1.0.0 -m "PrymeStudy Connect v1.0.0"
git push origin v1.0.0
```

The tag triggers `.github/workflows/release.yml`, which verifies package versions and changelog state, reruns security/test gates, builds the PHP/TypeScript/Python artifacts, generates SHA-256 checksums, creates provenance attestations and creates the GitHub release.

Do not retag an existing release version.

## npm

Package:

```text
@prymestudy/connect
```

The TypeScript package metadata is in `sdk/typescript/package.json`.

Before the first publication:

1. Ensure the `@prymestudy` npm scope/organization exists and your npm account can publish to it.
2. Create an npm automation/granular token with only the permissions needed to publish this package.
3. Add the token to the GitHub repository Actions secret:

```text
NPM_TOKEN
```

Then run **Publish Connect Packages** in GitHub Actions for the released version.

After publication, verify from a clean directory:

```bash
mkdir connect-node-smoke && cd connect-node-smoke
npm init -y
npm install @prymestudy/connect@1.0.0
node -e "import('@prymestudy/connect').then(() => console.log('ok'))"
```

Never expose `NPM_TOKEN` to application code or package contents.

## PyPI

Package:

```text
prymestudy-connect
```

The Python package metadata is in `sdk/python/pyproject.toml`.

Before the first publication:

1. Ensure the PrymeStudy PyPI account/project ownership is ready.
2. Create a project-scoped PyPI API token.
3. Add the token to the GitHub repository Actions secret:

```text
PYPI_API_TOKEN
```

Then run **Publish Connect Packages** for the released version.

After publication, verify from a clean virtual environment:

```bash
python -m venv .venv
# activate the environment for your shell
python -m pip install --upgrade pip
pip install prymestudy-connect==1.0.0
python -c "import prymestudy_connect; print('ok')"
```

The static token can later be replaced with PyPI Trusted Publishing after the project/publisher relationship is configured.

## Packagist / Composer

Package:

```text
prymestudy/connect
```

The repository root now contains the Composer package metadata required by Packagist. The PSR-4 autoloader maps the public namespace to `sdk/php/src/`.

For the first publication:

1. Sign in to Packagist.
2. Submit:

```text
https://github.com/lerionjakenwauda/prymestudy-connect
```

3. Confirm Packagist detects the package name `prymestudy/connect`.
4. Enable the GitHub/Packagist update hook if desired so future Git tags update automatically.

After Packagist has indexed `v1.0.0`, verify from a clean project:

```bash
mkdir connect-php-smoke && cd connect-php-smoke
composer init --no-interaction --name=prymestudy/connect-smoke
composer require prymestudy/connect:^1.0
php -r "require 'vendor/autoload.php'; echo class_exists('PrymeStudy\\Connect\\ConnectClient') ? 'ok' : 'missing';"
```

## Registry smoke-test gate

Do not announce registry installation until all three commands succeed from clean external projects:

```bash
npm install @prymestudy/connect@1.0.0
composer require prymestudy/connect:^1.0
pip install prymestudy-connect==1.0.0
```

A GitHub release by itself does not prove the public registries are usable.

## Production boundary

Publishing SDK packages does not grant an institution production access.

Production still requires:

- the matching PrymeStudy server candidate to be deployed;
- Connect server CI and security gates;
- TLS/domain verification;
- sandbox integration certification;
- exact academic coverage validation;
- central PrymeStudy approval;
- a controlled production smoke test.

See `docs/PRODUCTION_READINESS.md` and the private PrymeStudy Web deployment runbook for the final go/no-go process.
