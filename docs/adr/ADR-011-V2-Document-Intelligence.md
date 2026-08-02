# ADR-011: Isolated, Versioned Document Intelligence

## Status

Accepted

## Context

Bangla, English, and mixed public documents range from native PDFs to noisy scans and complex tables. The development environment may have only CPUs or heterogeneous lab GPUs.

## Decision

Document processing runs asynchronously outside web requests. Native text extraction is first, followed by CPU-first Tesseract `ben+eng`. A low-confidence page may receive one enhanced second pass; remaining uncertainty goes to manual review. Layout and table extraction are versioned separately from text.

Every extraction records artifact checksum, engine and model versions, language decision, preprocessing recipe, per-page confidence, evidence coordinates, timestamps, and outcome. Workers have no application secrets, tools, or unrestricted network access. Future vision/language models begin in shadow mode and must emit evidence references or abstain.

Heterogeneous lab computers may join as independent capability-advertising queue workers. Jobs are assigned to one compatible worker at a time; unrelated GPUs are not treated as pooled VRAM. Distributed training remains optional and cannot become a runtime dependency.

## Consequences

The pipeline can improve providers without rewriting document or review domains and can operate without a high-end private GPU. Queue orchestration, artifact versioning, parser isolation, and evaluation datasets become required infrastructure.

## V2 Impact

This replaces v1 processing placeholders incrementally. Existing document records and versions remain the source of truth.
