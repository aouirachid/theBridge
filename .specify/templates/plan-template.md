# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command.

## Summary

[Extract the primary requirement and technical approach from the feature specification.]

## Technical Context

**Language/Version**: PHP 8.4 and TypeScript [confirm installed versions]

**Primary Dependencies**: Laravel 13, Inertia 3 React, Wayfinder, Pest 5

**Storage**: [database, cache, object storage, or N/A]

**Testing**: Pest unit tests for Actions; Pest feature tests for HTTP integration;
[component/browser coverage or N/A with rationale]

**Target Platform**: [web/runtime environment]

**Project Type**: Laravel/Inertia React web application

**Architecture**: [Form Request -> thin controller -> Action; list justified exceptions]

**Frontend**: [Inertia pages/components, Wayfinder routes, and loading/error states]

**Security**: [authentication, authorization, sensitive data, abuse/rate limiting,
input/output safety, and dependency audit concerns]

**Performance Goals**: [measurable goals or N/A with rationale]

**Constraints**: [latency, memory, availability, compatibility, or N/A]

**Scale/Scope**: [expected users, records, requests, screens, or workflows]

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Framework conventions**: Installed Laravel, Inertia React, React, Pest, and
  Wayfinder versions are confirmed; comparable project patterns have been inspected.
- **Security boundary**: Actors, authentication, authorization, validated input,
  sensitive data, output safety, abuse controls, and security failure cases are mapped.
- **Action-first design**: Each business use case has a single-purpose Action; controllers
  only map HTTP input/output and contain no business logic.
- **Form Request boundary**: Every user-controlled mutation/input endpoint has a dedicated
  Form Request and passes only validated or explicitly safe data to its Action.
- **Layered tests**: Every Action behavior has a direct Pest unit test and every HTTP
  behavior has a Pest feature integration test; frontend-only behavior has suitable
  component or browser coverage.
- **Operational quality**: Query cost, pagination, transactions, race protection,
  external-call resilience, and safe error handling are addressed where applicable.

Any failed gate MUST be resolved before Phase 0 or documented in Complexity Tracking
with explicit approval.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
`-- tasks.md
```

### Source Code (repository root)

Replace or extend this tree with the concrete files used by the feature. Remove unused
directories from the delivered plan.

```text
app/
|-- Actions/
|-- Http/Controllers/
|-- Http/Requests/
|-- Models/
`-- Policies/

resources/js/
|-- actions/
|-- components/
|-- pages/
`-- routes/

tests/
|-- Feature/
|-- Unit/
`-- Browser/
```

**Structure Decision**: [List the real files/directories and explain any deviation.]

## Use-Case Mapping

| Use Case | Form Request | Controller | Action | Unit Test | Feature Test |
|----------|--------------|------------|--------|-----------|--------------|
| [use case] | [path] | [path] | [path] | [path] | [path] |

## Security Design

| Boundary/Risk | Control | Verification |
|---------------|---------|--------------|
| [actor, input, data, abuse, or dependency risk] | [policy/control] | [test/check] |

## Complexity Tracking

> Fill ONLY when a Constitution Check violation requires explicit justification.

| Violation | Why Needed | Simpler Compliant Alternative Rejected Because | Approval |
|-----------|------------|-----------------------------------------------|----------|
| [violation] | [specific need] | [reason] | [owner/date] |
