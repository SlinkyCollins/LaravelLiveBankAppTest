# Copilot Project Instructions

## Purpose
When asked to create, update, or review `README.md`, prioritize accuracy over polish.
The README must reflect the real repository, not assumptions.

## Core README rules
- Never invent features, scripts, commands, dependencies, integrations, APIs, or deployment targets.
- Only document behavior that can be inferred from the codebase, config files, package manifests, docs, tests, Docker files, CI files, or examples.
- If something appears likely but is not confirmed, mark it as `TODO: verify`.
- Keep the README practical, direct, and easy to scan.
- Prefer concrete commands over vague prose.
- Prefer repository truth over generic best practices.

## Before writing a README
Inspect the repository first. Check, when present:
- root files such as `package.json`, `pyproject.toml`, `requirements.txt`, `Cargo.toml`, `go.mod`, `pom.xml`
- framework configs
- Docker-related files
- CI workflows
- environment example files
- source entry points
- scripts
- test files
- existing docs
- example usage files

## What to extract from the repo
Infer and document only what is supported by evidence in the repo:
- project name
- one-sentence purpose
- main features
- tech stack
- installation steps
- prerequisites
- environment variables
- development commands
- build commands
- test commands
- run/start commands
- deployment notes
- usage examples
- project structure
- known limitations
- license

## README writing style
- Write in clear technical English.
- Be concise, but not shallow.
- Avoid marketing fluff.
- Avoid saying "powerful", "robust", "seamless", "cutting-edge", or similar filler unless the repo itself uses that wording intentionally.
- Use bullet lists only when they improve scanning.
- Use fenced code blocks for commands and config examples.
- Use section headings that are predictable and reusable.
- Keep command examples copy-paste friendly.
- Use relative paths when referencing repo files.

## Output quality rules
- The opening paragraph must explain what the project does in plain English.
- Include a "Features" section only if features can be identified from the codebase.
- Include "Prerequisites" only if there are actual runtime or tooling requirements.
- Include "Environment Variables" only if variables are present in code, config, or docs.
- Include "Usage" only if a real usage flow can be inferred.
- Do not include badges unless they already exist or are explicitly requested.
- Do not include screenshots unless they already exist or are explicitly requested.
- Do not include architecture diagrams unless explicitly requested.

## Accuracy safeguards
When updating an existing README:
- preserve correct project-specific content
- remove stale claims
- rewrite ambiguous sections
- keep examples aligned with the current codebase
- check that scripts and commands match actual files

## Missing information policy
If essential information cannot be confirmed:
- state the uncertainty briefly
- add `TODO: verify` where needed
- do not fill gaps with guesses

## Preferred README section order
Use this order unless the repo clearly needs a different one:
1. Title
2. Overview
3. Features
4. Tech Stack
5. Project Structure
6. Prerequisites
7. Installation
8. Configuration
9. Usage
10. Available Scripts / Commands
11. Development
12. Testing
13. Deployment
14. Troubleshooting
15. Roadmap or Limitations
16. Contributing
17. License

## When asked to generate README.md
Follow this workflow:
1. Inspect the repository
2. Extract only verified facts
3. Draft using the README template if available
4. Mark uncertain items as `TODO: verify`
5. Re-check commands and filenames
6. Write or update `README.md`

## When asked to review README.md
Return:
- inaccurate claims
- missing high-value sections
- unclear setup steps
- broken or suspicious commands
- exact revised text