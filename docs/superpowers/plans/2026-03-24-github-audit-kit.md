# ParkHub PHP GitHub Audit Kit Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the ParkHub PHP repository with a state-of-the-art GitHub Copilot and GitHub Security audit kit.

**Architecture:** Reuse the existing GitHub footprint, add standardized Copilot instructions and custom agents, and strengthen repository automation with multi-language CodeQL, dependency review, Copilot setup steps, and modern Dependabot coverage.

**Tech Stack:** Laravel/PHP, React/Vite, npm, Composer, Playwright, GitHub Actions, CodeQL, Dependabot, GitHub Copilot coding agent.

---

## Chunk 1: Audit Guidance Files

### Task 1: Add Copilot and AGENTS guidance

**Files:**
- Create: `.github/copilot-instructions.md`
- Create: `.github/instructions/backend.instructions.md`
- Create: `.github/instructions/frontend.instructions.md`
- Create: `.github/instructions/tests.instructions.md`
- Create: `.github/instructions/workflows.instructions.md`
- Create: `AGENTS.md`

- [ ] Add repository-wide audit guidance with PHP, frontend, and CI/CD expectations.
- [ ] Add path-specific instructions for backend, frontend, tests, and workflow files.
- [ ] Add root `AGENTS.md` with repo layout, commands, and security rules.

### Task 2: Add custom GitHub Copilot agents

**Files:**
- Create: `.github/agents/repo-auditor.agent.md`
- Create: `.github/agents/security-auditor.agent.md`
- Create: `.github/agents/release-readiness.agent.md`

- [ ] Define custom agents for broad audit, security-first review, and release readiness.

## Chunk 2: GitHub Automation

### Task 3: Upgrade GitHub automation

**Files:**
- Modify: `.github/workflows/codeql.yml`
- Create: `.github/workflows/dependency-review.yml`
- Create: `.github/workflows/copilot-setup-steps.yml`
- Modify: `.github/dependabot.yml`

- [ ] Expand CodeQL to PHP and JavaScript/TypeScript.
- [ ] Add dependency review for pull requests.
- [ ] Add Copilot setup steps that install PHP/Composer and Node dependencies.
- [ ] Expand Dependabot coverage for Composer, npm, and Actions.
