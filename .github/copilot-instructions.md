# Copilot Instructions

This repository contains a Composer package that provides a `tailwind:build` command.
Goal: compile Tailwind CSS using the standalone binary that matches the current OS.

## Project scope

- Package type: `library`
- Compatibility: Composer 2.x
- Main command: `tailwind:build`
- Approach: generic package with a standalone CLI entrypoint

## Required architecture

- `bin/tailwind-build`: standalone CLI entrypoint
- `src/Command/TailwindBuildCommand.php`: option parsing, build orchestration
- `src/Binary/TailwindBinary.php`: binary resolution, download, and cache
- `src/Platform/PlatformDetector.php`: OS/arch mapping to binary name
- `src/Config/BuildOptions.php`: user option normalization/validation

Do not move responsibilities between classes without a clear need.

## Code rules

- Use `declare(strict_types=1);` in every PHP file.
- Prefer final classes where appropriate.
- Keep functions short and explicit.
- Avoid hidden side effects.
- Do not introduce heavy dependencies without justification.
- Preserve compatibility with existing CLI options.

## `tailwind:build` command rules

- Support existing options: `--output`, `--watch`, `--minify`, `--config`, `--tailwind-version`, `--platform`, `--bin-path`.
- Default Tailwind version: `v4.3.0` (unless explicitly changed by the maintainer).
- Return actionable errors and correct exit codes.

## Tailwind binary management

- Resolution priority: `--bin-path` > local cache > GitHub Releases download.
- Local cache directory: `.cache/tailwind/<version>/...`.
- Keep a download lock to prevent concurrent race conditions.
- On Windows, handle the `.exe` suffix correctly.
- Do not remove existing cache without an explicit action.

## Tests

- Add/adapt tests for every behavior change.
- Prefer deterministic unit tests.
- Prioritize coverage for:
  - platform-to-binary mapping
  - option normalization
  - binary resolution error cases

## Change quality

- Make minimal and targeted changes.
- Avoid broad refactors without an explicit request.
- Update `README.md` if a CLI option, default, or behavior changes.
- Never break the main flow: `vendor/bin/tailwind-build` and `composer tailwind:build -- ...`.

## Example priorities when proposing code

1. Functional correctness
2. CLI compatibility
3. IO/network robustness
4. Readability
5. Performance
