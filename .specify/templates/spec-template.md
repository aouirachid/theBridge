# Feature Specification: [FEATURE NAME]

**Feature Branch**: `[###-feature-name]`

**Created**: [DATE]

**Status**: Draft

**Input**: User description: "$ARGUMENTS"

## User Scenarios & Testing *(mandatory)*

User stories MUST be prioritized and independently testable. Each story must describe a
complete slice of user value that can be implemented, verified, and demonstrated without
requiring lower-priority stories.

### User Story 1 - [Brief Title] (Priority: P1)

[Describe the user journey in plain language.]

**Why this priority**: [Explain its value and priority.]

**Independent Test**: [Explain how to verify the story and the value it delivers.]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]
2. **Given** [initial state], **When** [action], **Then** [expected outcome]

---

### User Story 2 - [Brief Title] (Priority: P2)

[Describe the user journey in plain language.]

**Why this priority**: [Explain its value and priority.]

**Independent Test**: [Explain how to verify the story independently.]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]

---

### User Story 3 - [Brief Title] (Priority: P3)

[Describe the user journey in plain language.]

**Why this priority**: [Explain its value and priority.]

**Independent Test**: [Explain how to verify the story independently.]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]

---

[Add more prioritized user stories as needed.]

### Edge Cases

- What happens at each relevant boundary condition?
- How does the system handle expected operational failures?
- What happens when the actor is unauthenticated or lacks permission?
- How does the system reject malformed, unexpected, duplicate, or hostile input?
- Could retries, concurrent requests, or repeated submissions violate an invariant?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST [specific capability].
- **FR-002**: System MUST [specific validation or state behavior].
- **FR-003**: Users MUST be able to [key interaction].
- **FR-004**: System MUST [data or integration requirement].
- **FR-005**: System MUST [failure or recovery behavior].

Mark costly unresolved choices explicitly, for example:

- **FR-006**: System MUST authenticate users via [NEEDS CLARIFICATION: method unknown].
- **FR-007**: System MUST retain user data for [NEEDS CLARIFICATION: period unknown].

### Security and Data Requirements *(mandatory)*

- **SR-001**: System MUST identify which actors may perform each protected operation.
- **SR-002**: System MUST define validation and safe failure behavior for every
  user-controlled input.
- **SR-003**: System MUST identify sensitive or personal data and define how it is
  protected in storage, transit, responses, and logs.
- **SR-004**: System MUST define throttling or abuse controls for public,
  authentication, bulk, or costly operations, or state why they are not applicable.
- **SR-005**: System MUST define the expected unauthorized, forbidden, validation,
  conflict, and not-found outcomes that apply to the feature.

### Key Entities *(include if feature involves data)*

- **[Entity 1]**: [What it represents and its key attributes.]
- **[Entity 2]**: [What it represents and its relationships.]

## Success Criteria *(mandatory)*

Success criteria MUST be measurable and technology-agnostic.

### Measurable Outcomes

- **SC-001**: [Measurable user outcome.]
- **SC-002**: [Measurable performance or reliability outcome.]
- **SC-003**: [Measurable correctness or completion outcome.]
- **SC-004**: [Measurable business or support outcome.]

## Assumptions

- [Reasonable default about users or environment.]
- [Explicit scope boundary.]
- [Existing system or security mechanism that will be reused.]
- [Dependency on an existing service or dataset.]
