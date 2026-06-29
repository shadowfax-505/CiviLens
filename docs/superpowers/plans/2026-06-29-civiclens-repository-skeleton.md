# CivicLens Repository Skeleton Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a CivicLens Enterprise v1.0 documentation repository that can evolve cleanly into v2.

**Architecture:** The repository is documentation-first. `.ai/` stores AI memory and process rules, `docs/` stores the engineering handbook, `docs/v2/` stores future upgrade work, and `prompts/` stores reusable AI development prompts.

**Tech Stack:** Markdown, Mermaid, Laravel planning artifacts, MySQL planning artifacts, Codex prompt templates.

---

### Task 1: Create Foundation Structure

**Files:**
- Create: `README.md`
- Create: `docs/MASTER_INDEX.md`
- Create: `.ai/MASTER_MEMORY.md`

- [x] **Step 1: Create the directory tree**

Run: `mkdir -p .ai docs/adr docs/api docs/database docs/diagrams/erd docs/diagrams/c4 docs/modules docs/sprints docs/v2 prompts/Codex prompts/Planning prompts/Templates prompts/Testing`

- [x] **Step 2: Add top-level repository documentation**

Create `README.md` with the project summary, repository map, version strategy, and start-here links.

- [x] **Step 3: Add master index**

Create `docs/MASTER_INDEX.md` with links to all core handbook files and support areas.

### Task 2: Create V1 Handbook

**Files:**
- Create: `docs/00_PROJECT_CONSTITUTION.md` through `docs/30_DOMAIN_MODEL.md`

- [x] **Step 1: Add one focused starter document per handbook chapter**

Each file includes purpose, initial v1 content, and v2 expansion notes where relevant.

### Task 3: Create V2 Upgrade Area

**Files:**
- Create: `docs/v2/README.md`
- Create: `docs/v2/V2_UPGRADE_PATH.md`
- Create: `docs/v2/V2_BACKLOG.md`

- [x] **Step 1: Keep future work separate**

Store advanced AI, public API, semantic search, geospatial, and monitoring ideas in `docs/v2/`.

