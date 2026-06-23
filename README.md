A Composer package that compiles CSS using the Tailwind standalone binary matching the current OS.

## Installation

```bash
composer require aligny/tailwind-builder
```

## Usage

```bash
vendor/bin/tailwind-builder
```

Example with options:

```bash
vendor/bin/tailwind-builder assets/tailwind.css \
  --output=public/styles.css \
  --minify \
  --tailwind-version=v4.3.0
```

> `vendor/bin/tailwind-build` remains available as an alias to `vendor/bin/tailwind-builder`.

### Install globally

To install the command globally, you can use:

```bash
composer global require aligny/tailwind-builder
composer global config bin-dir --absolute
```

## Options

- `input` (argument): source CSS path. Default: `assets/tailwind.css`
- `--output|-o`: compiled CSS path. Default: `<path>/styles.css`
- `--watch|-w`: watch mode
- `--minify|-m`: minification
- `--config|-c`: Tailwind config path (mainly for v3)
- `--tailwind-version`: Tailwind version (default `v4.3.0`)
- `--platform`: platform override (`auto`, `linux-x64`, `linux-arm64`, `macos-x64`, `macos-arm64`, `windows-x64`, etc.)
- `--bin-path`: explicit path to a local binary (skips download)
- `--checksum`: expected binary SHA-256 (hex or `sha256:` prefix)
- `--insecure-skip-checksum-verification`: disables checksum verification (not recommended)

## Notes

- The binary is downloaded from Tailwind GitHub Releases and cached in `.cache/tailwind/<version>/`.
- By default, the package verifies the binary SHA-256 using the digest exposed by the GitHub Releases API; if metadata is unavailable, the command fails to avoid running an unverified binary.
- You can explicitly provide a hash with `--checksum` (useful in restricted/offline environments).
- During concurrent execution, a lock file prevents simultaneous downloads.
