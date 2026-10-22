# AGENTS.md

## Project overview

This academic project is an online and in-person tutoring platform.

The platform connects students with qualified tutors across different subjects and educational levels.

Students can search for tutors and book tutoring sessions. Tutors can manage their availability and conduct sessions online or in person, as applicable.

## Stack and architecture

* Runtime environment: USBWebServer 8.6.5.
* Backend: PHP.
* Frontend: HTML, CSS, and JavaScript.
* Database: use the database engine and configuration already established by the project.
* JavaScript ES Modules are allowed.
* Prefer native browser and PHP capabilities when they meet the requirements.
* Follow the existing project architecture. Do not introduce a new architecture without a clear need.
* Organize code by responsibility and feature when appropriate.
* Keep modules cohesive and avoid circular dependencies.
* Separate presentation, application logic, and backend responsibilities.
* Centralize shared functionality when doing so improves maintainability.
* Avoid unnecessary abstractions, files, and dependencies.
* Do not introduce a framework, external dependency, or build step without first explaining its purpose, benefits, and alternatives and obtaining user approval.

## Conventions

* All user-facing interface text must be in Spanish.
* Use English for code identifiers, filenames, directory names, code comments, and technical documentation.
* Keep code and technical Markdown clear, simple, and concise.
* Write comments only when they clarify non-obvious behavior or important decisions.
* Use descriptive names that communicate intent.
* Avoid global variables unless necessary.
* Follow the existing formatting and naming conventions when modifying established code.
* Reuse existing components and utilities when appropriate instead of duplicating logic.
* The README.md file must be written in Spanish.
* You can only read but not modify `database/ProfeGo.sql` it is the database structure.
* Preserve the existing design and responsive behavior.

## Design and responsive behavior

The Figma project contains Desktop and Mobile designs.

Use Figma as a visual reference, not as a strict technical specification.

* Reproduce the intended visual structure, typography, spacing, colors, components, and interaction states.
* Implement responsive behavior for the supported screen sizes.
* Use the Mobile design to determine how the interface adapts to smaller screens.
* Do not assume every Desktop element must remain visible on Mobile.
* Do not simply scale down the Desktop layout.
* Use appropriate CSS techniques, including media queries, Flexbox, and Grid.
* Do not redesign established interfaces without user authorization.
* Preserve existing design decisions unless a change is required by the task or approved by the user.

## Security and data handling

* Enforce authentication and authorization on the server for protected operations.
* Never rely solely on frontend validation or hidden interface elements for access control.
* Validate and appropriately handle all untrusted input on the server.
* Use prepared statements for database queries.
* Store passwords using appropriate password-hashing functions.
* Do not expose credentials, tokens, or other secrets in client-side code or public files.
* Protect state-changing requests against CSRF where applicable.
* Handle user-generated content safely to prevent injection and cross-site scripting.
* Follow the principle of least privilege when accessing user data and location information.
* Do not introduce security-sensitive behavior without understanding its impact on existing authentication, sessions, and permissions.

## Development workflow

Before modifying code:

1. Read this file.
2. Read MEMORY.md if it exists.
3. Read docs/constitution.md.
4. Read the active specification in spec/.
5. Inspect the relevant existing code and tests.
6. Identify the requirements and constraints relevant to the task.

When implementing a task:

* Make only the changes needed to satisfy the request.
* Avoid unrelated refactoring and redesign.
* Follow the active specification and established architecture.
* Preserve existing functionality unless the task requires changing it.
* Avoid circular dependencies and unnecessary coupling.
* Do not assume that a feature is complete without verifying its relevant behavior.

When finishing a task:

* Run the relevant tests when possible.
* Report which checks were run and whether they passed.
* If a check could not be run, explain why.
* Summarize the changes and any relevant limitations.
* Update MEMORY.md only when meaningful project state, decisions, unresolved issues, or useful findings have changed.
* Keep MEMORY.md at or below 50 lines.
* Do not duplicate documentation unnecessarily.
* Never store passwords, tokens, credentials, or other sensitive information in MEMORY.md.

## Instructions and conflicts

* Follow all applicable higher-priority instructions.
* Distinguish genuine conflicts from instructions that can be satisfied together.
* If a material conflict prevents a safe or correct implementation, stop the affected work and explain the conflict before proceeding.
* Ask for clarification when the requirements are ambiguous and the ambiguity materially affects the implementation.
* Do not stop for minor ambiguities that can be resolved safely using the established project conventions.
* Do not modify AGENTS.md unless the user explicitly authorizes the change.

## Documentation

* Read docs/constitution.md and the active specification in spec/ before modifying code.
* Treat the active specification as the source of requirements for the feature being implemented.
* If documentation conflicts with the current implementation, identify the discrepancy rather than silently assuming which is correct.
* Keep technical documentation in English, except README.md, which must be in Spanish.
* Do not create or modify documentation unrelated to the requested task unless necessary to keep it accurate.

## Testing

* JavaScript tests may use Node.js's built-in test runner when appropriate.
* Command: `node --test`.
* This command does not replace PHP, integration, database, or browser testing.
* Run the relevant checks available in the project.
* Do not claim that tests passed unless they were actually executed successfully.
* Do not add a testing framework or dependency without user approval.

## Dependencies and tooling

* Ask before adding external dependencies, frameworks, or build steps.
* Explain why the addition is needed, its benefits, and reasonable alternatives.
* Do not introduce tooling solely for stylistic preference.
* Preserve compatibility with the existing USBWebServer environment unless a change is approved.

## Scope and communication

* Do only what the user requested.
* You may suggest relevant improvements, but distinguish suggestions from implemented changes.
* Do not perform broad refactoring, redesign, or unrelated cleanup without authorization.
* Communicate important assumptions, limitations, risks, and unresolved issues clearly.
