<!--
Sync Impact Report
- Version change: template -> 1.0.0
- Modified principles: initial ratification; no prior principles existed
- Added principles:
  - I. Laravel and Inertia Conventions
  - II. Security by Default
  - III. Action-First Application Architecture
  - IV. Form Requests Define the Input Boundary
  - V. Layered, Mandatory Testing
- Added sections:
  - Technology and Security Constraints
  - Development Workflow and Quality Gates
- Removed sections: none; template placeholders were resolved
- Templates:
  - updated: .specify/templates/plan-template.md
  - updated: .specify/templates/spec-template.md
  - updated: .specify/templates/tasks-template.md
  - reviewed, no change required: .specify/templates/constitution-template.md
- Durable project rules recorded:
  - .ai/rules/actions.md
  - .ai/rules/controllers-requests.md
  - .ai/rules/unit-feature.md
- Follow-up TODOs: none
-->
# The Bridge Constitution

## Core Principles

### I. Laravel and Inertia Conventions
All implementation MUST use the idioms and supported APIs of the installed Laravel,
Inertia React, React, Pest, and related package major versions. Before adding a new
pattern, contributors MUST inspect comparable code and reuse the project's established
structure, components, helpers, and naming. Laravel framework features MUST be
preferred over custom infrastructure. Inertia pages MUST use the established React
patterns for navigation, forms, validation errors, loading states, and server-provided
authorization capabilities. Frontend calls to Laravel routes MUST use Wayfinder rather
than hardcoded application URLs. New dependencies require explicit approval.

Rationale: framework-native, version-correct patterns reduce defects, maintenance cost,
and competing implementations of the same concern.

### II. Security by Default
Security controls are non-negotiable and MUST be enforced server-side. Every protected
operation MUST authenticate and authorize through Laravel middleware, policies, gates,
or a Form Request. Client-side visibility checks are usability aids, never authorization.
Code MUST use validated input, safe mass-assignment allowlists, parameterized queries,
escaped or sanitized output, CSRF protection, and secure file validation as applicable.
Secrets MUST remain in environment-backed configuration; sensitive values and personal
data MUST NOT be committed or logged in plaintext. Sensitive stored values MUST be
encrypted when appropriate. Public, authentication, bulk, and costly external-call paths
MUST use the project's rate-limiting controls when abuse or exhaustion is realistic.
Security-relevant failure paths MUST be covered by tests.

Rationale: security must be part of each feature's design and verification, not a later
hardening phase.

### III. Action-First Application Architecture
Each business use case MUST be implemented in a single-purpose Action class. Actions
own business rules, persistence coordination, transactions, domain events, and calls to
external boundaries. Dependencies MUST be injected, and external boundaries SHOULD be
represented by contracts where substitution improves isolation or testability.

Controllers MUST remain thin HTTP adapters. A controller method may bind route models,
receive an authorized and validated Form Request, map validated data into the Action's
input, invoke the Action, and translate its result into an Inertia response, redirect, or
other HTTP response. Controllers MUST NOT contain business decisions, query orchestration,
or multi-step persistence workflows. Actions MUST NOT depend on an HTTP Request or
construct HTTP responses. Action naming and invocation methods MUST match the existing
project convention.

Rationale: explicit use-case boundaries make business behavior reusable, independently
testable, and separate from delivery concerns.

### IV. Form Requests Define the Input Boundary
Every endpoint that accepts or mutates user-controlled data MUST use a dedicated Laravel
Form Request for validation. The Form Request MUST contain request-specific validation
and SHOULD contain request-specific authorization when that is the clearest policy
boundary. Controllers MUST pass only `validated()` or explicitly selected `safe()` data
to Actions; `$request->all()` MUST NOT feed business logic or mass assignment. Form
Requests MUST normalize input only when normalization is an HTTP-input concern. Business
rules that require domain state belong in the Action, while policies and gates remain the
source of truth for reusable authorization rules.

Rationale: one explicit input boundary prevents unvalidated or unauthorized data from
reaching domain behavior.

### V. Layered, Mandatory Testing
Every business behavior change MUST include a Pest unit test that invokes the Action
directly, without routing through a controller. The test MUST isolate external boundaries
with fakes or mocks where appropriate and MUST cover successful behavior, business-rule
failures, and meaningful edge cases. Every HTTP behavior change MUST also include a Pest
feature test that exercises the real route and verifies the integration of middleware,
authentication, authorization, Form Request validation, controller mapping, Action
resolution, persistence, redirects, and Inertia responses as applicable.

Feature tests MUST NOT duplicate every Action-level permutation; they prove that the HTTP
layers are wired correctly. Unit tests MUST NOT replace authorization, validation, or
response assertions at the HTTP boundary. Frontend-specific behavior MUST receive the
narrowest suitable component or browser coverage when PHP feature tests cannot prove it.
Tests MUST be written or updated with the behavior, and all relevant tests MUST pass
before work is considered complete.

Rationale: distinct Action and HTTP suites provide fast business feedback and confidence
that the full request path works without conflating the two responsibilities.

## Technology and Security Constraints

- The application stack is PHP 8.4, Laravel 13, Inertia 3 with React, Wayfinder, and
  Pest 5. Installed versions MUST be reconfirmed before using version-sensitive APIs.
- Existing project structure and shared rules take precedence where multiple valid
  Laravel or Inertia approaches exist, unless the existing pattern is insecure or
  incorrect.
- Eloquent queries MUST avoid N+1 access and unbounded loading. Expensive database and
  external operations MUST document their expected cost, pagination, caching, queueing,
  timeout, retry, or locking strategy as relevant.
- Database writes that form one business operation MUST be atomic. Actions MUST use
  transactions or concurrency controls when partial writes or races could violate an
  invariant.
- Error handling MUST expose safe user-facing outcomes while preserving useful,
  non-sensitive diagnostic context. Sensitive inputs, credentials, tokens, and personal
  data MUST NOT appear in logs or exception messages.
- Dependencies MUST pass the project's audit checks before release. A known exploitable
  vulnerability blocks release unless an approved mitigation is documented.

## Development Workflow and Quality Gates

1. Specifications MUST identify security boundaries, actors, authorization rules,
   validation failures, sensitive data, abuse risks, and measurable acceptance outcomes.
2. Plans MUST map each use case to its Form Request, thin controller, Action, Action unit
   test, and HTTP feature test. Any exception MUST be recorded in Complexity Tracking.
3. Implementation MUST start from the required tests, then proceed through the input
   boundary and Action to the controller and Inertia UI. Existing conventions and
   version-specific documentation MUST be checked before editing.
4. Reviews MUST reject business logic in controllers, HTTP dependencies in Actions,
   unvalidated Action input, client-only authorization, hardcoded application URLs in
   Inertia code, missing security controls, or missing required test layers.
5. Before completion, contributors MUST run the narrowest relevant Pest suites, then all
   affected tests. Modified PHP MUST be formatted with the project Pint command; affected
   frontend code MUST pass the configured formatting, linting, type, and build checks.
6. Failing tests, unresolved security concerns, or skipped mandatory checks block
   completion. If a check cannot run because of an environment problem, the exact
   failure MUST be reported and the work MUST NOT be represented as verified.

## Governance

This constitution is the highest-priority engineering policy for The Bridge. Feature
specifications, implementation plans, task lists, reviews, and code changes MUST include
an explicit Constitution Check. Conflicts with lower-level guidance are resolved in favor
of this document, while stricter security requirements always prevail.

Amendments require a documented proposal, review of affected templates and runtime
guidance, explicit project-owner approval, a migration plan for incompatible changes,
and an updated Sync Impact Report. Constitution versions follow semantic versioning:
MAJOR for incompatible principle removals or redefinitions, MINOR for new principles or
materially expanded obligations, and PATCH for non-semantic clarifications. Compliance
MUST be reviewed during planning and again before completion. Any justified exception
MUST be narrow, time-bounded, recorded in the plan's Complexity Tracking table, and
approved before implementation.

**Version**: 1.0.0 | **Ratified**: 2026-08-08 | **Last Amended**: 2026-08-08
