# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[fix]** `Read` tool returns empty `{}` for `src/Plugin.php` even though the file exists and is readable — use `Bash` with `cat src/Plugin.php` as the fallback. Retrying `Read` with different `limit`/`offset` combinations will not help; the failure is consistent across all parameter variants.
- **[gotcha]** When `Read` returns `{}` (empty object, no error message) on a PHP file in `src/`, do not retry with different parameters — switch to `Bash cat` immediately. Confirmed: `Grep` and `Bash cat` both work on files that `Read` silently fails on.
