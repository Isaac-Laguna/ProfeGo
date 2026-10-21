# AGENTS.md

The academic project will consist of developing an online/in-person tutoring platform.

This platform will be designed to connect students with qualified tutors across various subjects and educational levels. Students will be able to search for and book tutoring sessions, while tutors will be able to manage their availability and conduct tutoring sessions—either virtually (via video conference) or in person, as applicable.

## Stack and structure

- This project uses USBWebServer 8.6.5 to run.
- HTMl, CSS, JS and PHP.
- Avoid circular dependencies.
- ES Modules are allowed.

This is not limited to the mentioned ones; if you believe a new technology or a framework is necessary, tell it to the user.

## Conventions

- The UI must be in Spanish.
- Code in English and simple.
- File and directory names must be in English.
- Only necesary comments are allowed.
- For design, see *Figma and Responsive Design*

## Rules

- Do only what you were asked for; you can suggest tough.
- If two instructions are in conflict, such as the user request, skill instruction, AGENTS.md, or any written instruction you are supposed to follow, *stop* and report to the user the conflicts.
- Be aware of your memory file named `MEMORY.md`
  - Read it when you start to know the current state of the project.
  - When you finish, consider updating it with the current state of the project, important decisions (and why), and errors to be aware of.

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
