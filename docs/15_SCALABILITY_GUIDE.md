# 15 Scalability Guide

## V1 Scaling Strategy

Keep the app monolithic but modular. Use queues for slow tasks and indexes for search-heavy workflows.

## V2 Scaling Strategy

Split workloads by responsibility: web, queue, search indexing, OCR, analytics, and public API.

