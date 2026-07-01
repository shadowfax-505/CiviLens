# 15 Scalability Guide

## V1 Scaling Strategy

Keep the app monolithic but modular. Use queues for slow tasks and indexes for search-heavy workflows.

Sprint 13 part 1 keeps production scaling simple: one PHP-FPM web service, supervised queue workers, a scheduler loop, MySQL, Redis, and Nginx. The Civic Integrity Engine is deterministic and can run from the scheduler while datasets are modest; at larger volumes, run it as a queue-backed or isolated command workload with the same rule services and run-history table.

## V2 Scaling Strategy

Split workloads by responsibility: web, queue, search indexing, OCR, analytics, and public API.
