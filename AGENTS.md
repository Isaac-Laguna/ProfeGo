# AGENTS.md

## Figma y diseño responsive

El proyecto Figma contiene diseños Desktop y Mobile.

El diseño de Figma debe utilizarse como referencia visual, no como una especificación técnica perfecta.

El agente debe:

- reproducir la estructura visual general;
- respetar tipografías, tamaños, espaciados, colores, componentes, estados hover y demás decisiones visibles en Figma;
- implementar comportamiento responsive;
- utilizar el diseño Mobile como referencia para determinar cómo cambia la interfaz en pantallas pequeñas;
- no asumir que todos los elementos de Desktop deben permanecer visibles en Mobile;
- si un elemento aparece en Desktop pero no en Mobile, considerar que puede existir una decisión intencional de ocultarlo;
- evitar simplemente escalar toda la interfaz de Desktop;
- utilizar CSS media queries, flexbox, grid y otras técnicas apropiadas para implementar el comportamiento responsive.

El diseño puede contener inconsistencias o decisiones imperfectas debido a limitaciones de tiempo del equipo. No debe realizarse un rediseño general del proyecto sin autorización.

El objetivo es implementar el diseño existente, no sustituirlo por un diseño nuevo.
