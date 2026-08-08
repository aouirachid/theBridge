---
paths:
  - 'tests/{Unit,Feature}/**'
---

# Unit Feature

## Separate Action and HTTP test layers
Every business behavior needs a Pest unit test that invokes its Action directly. Every HTTP behavior needs a Pest feature test covering route, middleware, auth, Form Request validation, controller mapping, Action resolution, persistence, and response integration as applicable.
