# ABC Suite Monorepo Overview

This repository now contains multiple ABC projects under one root to make it easier
to manage them together. The primary WordPress plugin at the root remains the
**ABC Estimator Pro** plugin, while the other projects live in `packages/`.

## Layout

- `./` – ABC Estimator Pro (WordPress plugin)
- `packages/abc-production-system` – Production system plugin/codebase
- `packages/abc-b2b-designer` – WC/Designer plugin/codebase

## Notes

- Each package is kept intact so it can still be run or deployed independently.
- If you need to separate these back into standalone repositories, each package
  directory can be moved to its own repo.
