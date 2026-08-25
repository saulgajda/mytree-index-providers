# Changelog

## 0.2.0

- Added parish discovery mode for Geneteka and Metryki-Wołyń.
- Added common `AvailableParish` model (`mytree.available-parish.v1`).
- Geneteka: region-scoped parish discovery and optional all-region discovery.
- Geneteka: fallback discovery from the `parishes` member of `getAct.php` JSON.
- Metryki-Wołyń: discovery from the public `Zawartość` table with entry/index coverage metadata.
- CLI discovery output formats: table, JSON, JSONL; optional `--save` and `--refresh`.
- Added tests for discovery parsers and provider-level discovery.

## 0.1.0

- Initial Geneteka and Metryki-Wołyń acquisition providers.
