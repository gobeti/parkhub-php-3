Apply these instructions when working in `app/**`, `bootstrap/**`, `config/**`, `database/**`, `routes/**`, and `tests/**` for backend changes.

- Prioritize authn/authz correctness, validation, migration safety, and auditability.
- Flag controller or service paths that trust client-supplied role, state, or pricing data.
- Require explicit validation and negative-path coverage for writes.
- Review schema and migration changes for rollback safety, data retention, and production impact.
- Treat privacy, GDPR, and account-management paths as high-risk.
