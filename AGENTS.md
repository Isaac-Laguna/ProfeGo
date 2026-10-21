# AGENTS.md

The academic project will consist of developing an online/in-person tutoring platform.

This platform will be designed to connect students with qualified tutors across various subjects and educational levels. Students will be able to search for and book tutoring sessions, while tutors will be able to manage their availability and conduct tutoring sessions—either virtually (via video conference) or in person, as applicable.

## Stack and structure

- This project uses USBWebServer 8.6.5 to run.
- PHP handles server-side rendering, sessions, and backend operations.
- JavaScript handles browser interactions.
- ES modules allow for the separation of frontend logic.
- API PHP files receive requests and return responses.
- JavaScript services centralize HTTP communication.
- Shared components are reused whenever it makes sense.
- Avoid circular dependencies.
- ES Modules are allowed.

This is not limited to the mentioned ones; see *Technologies Handling* to more information.

## Conventions

- The UI must be in Spanish. Incluiding:
  - Labels and buttons.
  - Validation messages.
  - Notifications.
  - Error messages displayed in the interface.
  - Help texts and confirmations.
- Code and Markdown in English and simple.
- File and directory names must be in English.
- Only necesary comments are allowed.
- For design, see *Figma and Responsive Design*

## Rules

- Do only what you were asked for; you can suggest tough.
- If two instructions are in conflict, such as the user request, skill instruction, AGENTS.md, or any written instruction you are supposed to follow, *stop* and report to the user the conflicts.
- The `README.md` file is the only file that has to be in Spanish (because users actively see it).
- Read the `docs/constitution.md` file and the active spec `spec/NNN-name-` before modifying code.
- You should not modify `AGENTS.md` (this file.)
- Avoid global variables unless necessary.

### Memory

Be aware of your memory file named `MEMORY.md`

- Read it when you start to know the current state of the project.
- Update `MEMORY.md` only when there is relevant information worth preserving.
- Do not record information that is already documented in README.md, AGENTS.md, or the specifications, unless it is useful for summarizing the current state.
- Keep the file brief. Maximum 50 lines; summarize or erase information that no longer is of use.
- If something becomes a permanent rule, suggest moving it to the `AGENTS.md` file instead of keeping it on memory.
- Do not ever save sensible data, such as passwords, tokens, etc.

### Technologies Handling

- Prioritize the native capabilities of PHP, JavaScript, HTML, and CSS.
- Do not introduce frameworks without authorization.
- Do not install dependencies or add build tools without approval.
- If a library can simplify complex functionality, explain the benefits, alternatives, and maintenance costs before proposing it.

### Security

- Verify authentication and authorization on the backend.
- Do not rely solely on frontend validations.
- Use prepared statements for database access.
- Do not store passwords in plain text.
- Do not expose credentials in public files.
- Validate data received by the server.
- Protect operations that modify data against CSRF, where applicable.
- Avoid inserting user-provided content as HTML without sanitization or secure handling.
- Request only the necessary location permissions and handle cases where they are denied.

## Testing

- JavaScript tests may use Node.js's built-in test runner when appropriate.
- Command: node --test.
- This command does not replace PHP, integration, database, or browser testing.
- Run the relevant checks available in the project.
- Do not claim that tests passed unless they were actually executed successfully.
- Do not add a testing framework or dependency without user approval.

## Others

### Figma and Responsive Design

The Figma project contains both Desktop and Mobile designs.

The Figma design should be used as a visual reference, not as a perfect technical specification.

You must:

- Reproduce the overall visual structure;
- Follow the typography, font sizes, spacing, colors, components, hover states, and other visual decisions shown in Figma;
- Implement responsive behavior;
- Use the Mobile design as a reference to determine how the interface changes on smaller screens;
- Not assume that all Desktop elements must remain visible on Mobile;
- If an element appears on Desktop but not on Mobile, consider that there may be an intentional decision to hide it;
- Avoid simply scaling down the entire Desktop interface;
- Use CSS media queries, Flexbox, Grid, and other appropriate techniques to implement responsive behavior.

The design may contain inconsistencies or imperfect decisions due to the team's time constraints. A general redesign of the project must not be carried out without authorization.

The goal is to implement the existing design, not to replace it with a new one.
