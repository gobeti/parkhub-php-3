Apply these instructions when working in `.github/workflows/**`, deployment scripts, or release automation.

- Treat CI/CD security as a blocking review area.
- Require explicit `permissions` and least privilege.
- Flag secret exposure, unsafe use of PR input, cache poisoning risk, and weak action provenance.
- Prefer deterministic setup, lockfile-based caching, and gate jobs compatible with branch protection.
