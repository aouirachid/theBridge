---
paths:
  - 'app/Actions/**'
---

# Actions

## Actions own business use cases
Implement each business use case in a single-purpose Action with injected dependencies. Actions own business rules and persistence coordination, must not accept HTTP Request objects, and must not return HTTP responses.
