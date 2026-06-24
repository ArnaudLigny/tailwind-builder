# Copilot Instructions

This repository contains a Composer package that provides a `tailwind:build` command.
Goal: compile Tailwind CSS using the standalone binary that matches the current OS.

## Project scope

- Package type: `library`
- Compatibility: Composer 2.x
- Main command: `tailwind:build`
- Approach: generic package with a standalone CLI entrypoint

## Required architecture

- `bin/tailwind-builder`: standalone CLI entrypoint
- `src/Command/TailwindBuildCommand.php`: option parsing, build orchestration
- `src/Binary/TailwindBinary.php`: binary resolution, download, and cache
- `src/Platform/PlatformDetector.php`: OS/arch mapping to binary name
- `src/Config/BuildOptions.php`: user option normalization/validation

If PlatformDetector cannot map the current OS/arch to a known binary name, throw a RuntimeException listing the detected OS and arch, the set of supported platforms, and a note that `--platform` can be used to force a specific binary name.

Do not move responsibilities between classes without a clear need.

## Code rules

- Use `declare(strict_types=1);` in every PHP file.
- Mark all classes final unless they are explicitly designed for extension (e.g., abstract base classes or classes documented as extension points).
- Keep functions short and explicit.
- Avoid hidden side effects.
- Do not introduce heavy dependencies without justification.
- Preserve compatibility with existing CLI options.
- A breaking change is any of: removing a supported option, changing the type or accepted values of an option, or changing a default value that affects output. Any such change requires an explicit request and a README update.

## `tailwind:build` command rules

- Support existing options: `--output`, `--watch`, `--minify`, `--config`, `--tailwind-version`, `--platform`, `--bin-path`.
- Default Tailwind version: `v4.3.0` (unless the value is overridden in a dedicated constants file, e.g., `src/Config/Defaults.php`, or passed via `--tailwind-version` at runtime).
- Return actionable errors and correct exit codes.

## Tailwind binary management

- Resolution priority: `--bin-path` > local cache > GitHub Releases download.
- If `--bin-path` is provided, validate that the file exists and is executable before use. If not, throw a RuntimeException with the path and the specific failed check (not found / not executable).
- If the GitHub Releases download fails, throw a descriptive RuntimeException that includes the attempted URL, the HTTP status code (if available), and a suggestion to use `--bin-path` to provide the binary manually. Do not silently fall back to any other source.
- Local cache directory: `.cache/tailwind/<version>/...`.
- Keep a download lock to prevent concurrent race conditions.
- Use a file-based lock in the cache directory. If the lock cannot be acquired within 30 seconds, throw a RuntimeException with a message indicating a possible stale lock and its file path. Do not silently block indefinitely.
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
- When these constraints conflict, prioritize in this order: (1) functional correctness, (2) never breaking the main flow, (3) minimal changes, (4) no cross-class responsibility moves.
- Update `README.md` if a CLI option, default, or behavior changes.
- Never break the main flow: `vendor/bin/tailwind-builder` and `composer tailwind:build -- ...`.

## Example priorities when proposing code

1. Functional correctness
2. CLI compatibility
3. IO/network robustness
4. Readability
5. Performance
