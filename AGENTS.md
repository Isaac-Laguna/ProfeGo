# AGENTS.md

## Stack and structure

- HTMl, CSS, JS strict mode and PHP.

This is not limited to the mentioned ones; if you believe a new technology is necessary, tell it to the user.

## Conventions

- The UI must be in Spanish.
- Code in English and simple.
- File and directory names must be in English.
- Only necesary comments are allowed.

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
