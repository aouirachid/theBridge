---
paths:
  - 'app/Http/{Controllers,Requests}/**'
---

# Controllers Requests

## Thin controllers and Form Request boundaries
Controllers only map route/Form Request input to an Action and map its result to an HTTP/Inertia response. User-controlled input uses dedicated Form Requests, and only validated() or explicitly selected safe() data may reach Actions.
