# 11 Deployment Guide

## V1 Local Environment

Use Docker or Laravel Sail for PHP, MySQL, Redis, Meilisearch, and mail testing.

## Deployment Principles

- Environment variables configure services.
- Database migrations run during controlled releases.
- Queue workers are supervised.
- Storage is backed up.

## V2 Expansion Notes

Add separate workers for OCR, indexing, analytics, and public API workloads.

