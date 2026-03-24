# ParkHub PHP Copilot Instructions

You are reviewing and modifying production code. Prioritize correctness, security, privacy, migration safety, and release risk.

Core review behavior:
- Find bugs, vulnerabilities, regressions, and missing tests before commenting on style.
- Cite exact files whenever you report a finding.
- Prefer the smallest safe fix first, then mention stronger follow-up hardening.
- If evidence is missing, say `Not verifiable from repository contents`.

Backend focus:
- Treat all request input, uploaded files, headers, and route parameters as untrusted.
- Look for authz gaps, CSRF issues, insecure validation, SQL/query mistakes, secret leakage, unsafe logging, and migration risk.

Frontend focus:
- Flag XSS, unsafe client storage, broken auth flows, accessibility regressions, and missing error/loading states.

Workflow focus:
- Require explicit least-privilege `permissions`.
- Flag unsafe deployment triggers, secret exposure, shell interpolation risk, and weak third-party action hygiene.
