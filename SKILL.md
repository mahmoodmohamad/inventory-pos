---
name: laravel-saas-pair-programmer
description: Senior Laravel architecture, implementation, review, and debugging workflow for the Inventory POS SaaS project for Egyptian automotive spare-parts shops. Use when planning or changing Laravel code, database schema, tenancy, inventory, sales, payments, tests, UI, or deployment in this repository.
---

# Laravel SaaS Pair-Programming Skill

Use this skill together with the repository's root `CLAUDE.md`. `CLAUDE.md` contains the full product specification, domain invariants, architecture, roadmap, and current project status. This skill defines the repeatable execution procedure.

## Non-negotiable priorities

In descending order:

1. Tenant isolation and security.
2. Database and inventory integrity.
3. Financial correctness.
4. Authorization and auditability.
5. Maintainability.
6. Performance.
7. User experience.

Never trade a higher-priority invariant for implementation convenience.

## Language and level

- Explain in Egyptian Arabic.
- Keep all code and identifiers in English.
- Assume intermediate Laravel knowledge.
- Explain non-obvious decisions, risks, and framework behavior without teaching basic syntax unnecessarily.

## Required workflow for every task

### A. Establish the actual state

1. Read `CLAUDE.md` and this skill.
2. Inspect `git status` before changing files.
3. Inspect relevant models, migrations, requests, policies, services, routes, Livewire components, tests, and configuration.
4. Check installed dependency versions in lock files.
5. Search the repository for existing implementations before adding new abstractions.
6. Treat logs and source code as evidence; do not infer success.

If repository access is unavailable, ask for the smallest useful set of files and command output. Do not fabricate repository contents.

### B. Define one bounded step

Before coding, state:

- Goal.
- Current behavior/problem.
- Proposed design.
- Exact files expected to change.
- Security/data-integrity implications.
- Tests that will prove the change.

Do not combine roadmap steps. For material changes or changes to existing behavior, obtain explicit approval before implementation.

### C. Implement safely

- Provide complete code for every changed/new file, with exact paths.
- Use Git Bash-compatible commands.
- Follow existing project conventions.
- Prefer Form Requests, Policies, Eloquent relationships, focused Services/Actions, transactions, and Pest.
- Keep controllers and Livewire components thin.
- Avoid unnecessary dependencies and premature abstractions.
- Never silently modify unrelated files.
- Never trust request-provided `shop_id`.
- Do not make `shop_id` mass assignable.
- Do not bypass tenant scopes for ordinary application behavior.
- Do not use destructive migration operations without explicit approval.

### D. Verify

1. Run the narrowest relevant test first.
2. Run related feature/schema/architecture tests.
3. Run broader tests and quality commands when appropriate:

```bash
php artisan test
composer quality
vendor/bin/pint --test
```

4. Inspect the actual output.
5. If anything fails, diagnose the first meaningful cause before editing.
6. Ask the user to paste output when they must run commands locally.
7. Stop and wait for confirmation before the next step.

Never claim success without command output or other reliable evidence.

## Tenant isolation checklist

For every tenant-owned model/table/relationship, verify:

- `shop_id` exists and is assigned from trusted `TenantContext`.
- The model uses the fail-closed tenant scope and ownership trait, unless a documented exception applies.
- Missing tenant context fails closed.
- `shop_id` cannot be changed after creation.
- Cross-tenant writes are rejected even if global scopes are bypassed.
- Child records carry `shop_id`.
- Composite foreign keys reference `(shop_id, id)` and use `RESTRICT`.
- Tenant-aware validation and policies protect related IDs.
- Cross-tenant access returns 404.
- Direct database-level integrity is covered by MySQL tests.
- Jobs restore tenant context and cache keys include tenant identity where applicable.

`User` is the deliberate global-scope exception because authentication occurs before tenant context is established. Use explicit tenant-aware queries and authorization for users.

## Inventory and WAC checklist

All inventory writes go through the dedicated inventory service and one transaction.

- Lock product rows with `lockForUpdate()`.
- Write an immutable `stock_movements` row.
- Update cached `products.stock_qty` atomically.
- Use decimal-safe arithmetic; never PHP floats for money.
- Purchase receipt recalculates WAC.
- Sale snapshots `avg_cost` to the sale item and leaves average cost unchanged.
- Sale return uses the original sale item's cost snapshot and enters WAC as incoming stock.
- Purchase return uses current average cost and leaves average cost unchanged.
- Opening balance requires a unit cost.
- Negative-stock behavior follows `shops.settings.allow_negative_stock`.
- Test negative stock followed by a purchase, rollback, and cost snapshots.

## Sales and ledger checklist

- Completed invoices are immutable.
- Corrections use cancellation, returns, and compensating entries.
- Invoice numbers are generated per shop using a locked `shop_sequences` row.
- Enforce `UNIQUE(shop_id, invoice_number)`.
- Resolve retail/wholesale price from the customer tier.
- Manual price override is independently authorized on the server.
- Snapshot product name, applied unit price, and unit cost.
- Record every payment and support partial payment.
- Credit sales and payments create immutable customer ledger entries.
- Keep cached balances reconcilable with ledger history.
- Test transaction rollback across invoice, payment, stock, and ledger writes.

## Version-sensitive decisions

Before relying on a Laravel, PHP, Livewire, Tailwind, Flux, or package API:

1. Inspect the installed version.
2. Verify current official documentation/release notes.
3. Cite or link the official reference in the explanation when useful.
4. Do not upgrade packages unless explicitly asked or approved.

## Debugging protocol

When a test or command fails:

1. Quote the relevant error accurately.
2. Identify the failing test/file and operation.
3. Inspect the migration/model/configuration involved.
4. Form a hypothesis tied to evidence.
5. Propose the smallest safe fix.
6. Avoid unrelated refactoring.
7. Re-run the focused failing test.
8. Expand verification only after the focused test passes.

For the currently recorded blocker, investigate the missing `customer_ledgers` table before any sales service implementation. Inspect customer migrations, `migrate:status`, the ledger model, and `SalesSchemaTest`; do not assume a table rename is the fix.

## Completion response format

For each completed step, report:

- What changed.
- Why the design is safe.
- Files changed.
- Commands run.
- Actual test/quality results.
- Remaining risks or follow-up work.
- The single next step, but do not start it until the user confirms.

## Stop conditions

Stop and ask before proceeding if:

- A required migration is missing or has an unexpected state.
- A tenant boundary cannot be proven.
- A financial calculation is ambiguous.
- Existing behavior may be broken.
- A destructive database operation appears necessary.
- A test fails for an unresolved reason.
- The requested change conflicts with a recorded domain invariant.
