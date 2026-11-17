# ProfeGo Constitution

Non-negotiable principles for all work on this repository. Each principle states the rule and how compliance is verified.

**C1. Stack simplicity.** The platform SHALL use only PHP, HTML, CSS, vanilla JavaScript ES Modules, and MySQL on USBWebServer 8.6.5, with no framework, external dependency, or build step. *Verifiable by: change-set and dependency inspection.*

**C2. Specification primacy.** Code SHALL implement only behavior approved in an active specification; any change to approved behavior requires a revised, explicitly re-approved specification before implementation. *Verifiable by: requirement-to-change traceability review.*

**C3. Separation of logic and interface.** Request handling, validation, domain logic, persistence, and rendering SHALL live in separate server-side modules; page files remain thin entry points and templates carry no business logic. *Verifiable by: static dependency-direction review.*

**C4. Testing policy.** Tests SHALL use the project's native tooling (`node --test`, CLI PHP assertion scripts) with no added framework; a passing claim SHALL cite an executed run; tests that write data SHALL clean up after themselves. *Verifiable by: test transcripts, exit codes, and zero-residue assertions.*

**C5. User data protection.** Every untrusted input and state-changing operation SHALL be protected server-side through validation, prepared statements, password hashing, CSRF verification, and output escaping; credentials, tokens, and secrets SHALL NOT appear in client-side code, responses, or logs. *Verifiable by: static security review plus the security test layers.*

**C6. Languages.** User-facing text SHALL be in Spanish; code identifiers, filenames, comments, and technical documentation SHALL be in English, except `README.md`, which SHALL be Spanish. *Verifiable by: copy and change-set inspection.*
