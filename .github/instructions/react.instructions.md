---
applyTo: '**/*.{tsx,jsx}'
---

# GitHub Copilot Instructions — React + Tailwind + Flowbite + Font Awesome Pro (CDN)

**Purpose:** Guide Copilot to generate React UI components styled with Tailwind, using Flowbite for interactive pieces and Font Awesome Pro for icons.
(These instructions assume the required CDNs are already added to the app entry point; do not emit `<script>`/`<link>` tags inside components.)

## Project assumptions
  - Tailwind CDN: `https://cdn.tailwindcss.com`
  - Flowbite JS CDN: `https://unpkg.com/flowbite/dist/flowbite.min.js`
  - Font Awesome Pro CDN
  - Main HTML at `index.dev.html`

## React Code Contribution Instructions

Follow these guidelines when creating or modifying React components or related files:

1. **File Naming and Structure**
  - Use `.jsx` or `.tsx` extensions for React components.
  - Place components in a `components/` directory or an appropriate subfolder.
  - Name files and components using PascalCase (e.g., `MyComponent.jsx`).

2. **Component Structure**
  - Use functional components with hooks unless class components are required.
  - Export components as default unless multiple exports are needed.
  - Include PropTypes or TypeScript interfaces for props validation.

3. **Styling**
  - Use CSS Modules, styled-components, or Tailwind CSS for styling.
  - Avoid inline styles except for dynamic or one-off cases.

4. **State Management**
  - Prefer React's built-in state and context APIs for local and global state.
  - Use external state libraries (e.g., Redux, Zustand) only if necessary.

5. **Testing**
  - Write unit tests for all components using Jest and React Testing Library.
  - Place test files alongside components with a `.test.jsx` or `.test.tsx` suffix.

6. **Code Quality**
  - Follow the project's ESLint and Prettier configurations.
  - Ensure all code passes linting and formatting checks before committing.

7. **Documentation**
  - Add JSDoc or TypeScript doc comments for complex components and functions.
  - Update relevant README or documentation files if public APIs change.

8. **Pull Requests**
  - Reference related issues in your PR description.
  - Summarize changes and provide testing instructions.

---
Adhering to these instructions ensures consistency and maintainability across all React code in this repository.
- Framework initialization: `src/react/i18n.ts`
- locales folder `src/react/locales`
- locales format is JSON

## General rules for generated code
- Use **functional components** and React **hooks** (`useState`, `useEffect`, etc.).
- Use **JSX** and `className` for Tailwind utility classes.
- Keep components small, focused, and reusable.
- Prefer **semantic HTML** elements (`header`, `nav`, `main`, `section`, `footer`) in JSX.
- Do **not** include CDN `<script>` or `<link>` tags — assume they already exist.
- Export components as `default` unless requested otherwise.

## Styling & Flowbite usage
- Style with **Tailwind utility classes** (spacing, layout, typography, responsive classes).
- For interactive UI prefer **Flowbite** patterns (modals, dropdowns, navbar collapse, tooltips).
  - When an element needs Flowbite behavior, include the appropriate `data-*` attributes and ARIA attributes expected by Flowbite (describe attributes, but do not add script tags).
  - Ensure components are accessible (keyboard focus, aria labels, role attributes as appropriate).

## Icons (Font Awesome Pro)
- Use Font Awesome Pro icon **class names** in JSX (e.g. `fa-solid fa-user`) on an inline element.
- When an icon is decorative, mark it `aria-hidden="true"` and provide accessible text elsewhere if needed.
- Prefer small, semantic markup for icons (do not write full `<link>` tags).

## Accessibility & best practices
- Provide `alt` for images and visible labels for form inputs.
- Include keyboard-friendly attributes and visible focus styles (Tailwind `focus:` utilities).
- Add appropriate ARIA attributes on interactive elements (buttons, modals, dropdowns).

## Copilot prompt examples (copy/paste)
- `Create a responsive header React functional component with logo left, menu right, mobile collapse using Flowbite patterns, Tailwind classes, and Font Awesome Pro icons. Use className for styling and export default.`
- `Generate an accessible modal component using Flowbite's modal pattern. Include title, content slot, and a close button that uses a Font Awesome Pro icon. Use hooks to control visibility.`
- `Create a CardGrid component that maps an array of items to Flowbite-styled cards; include image, title, description, and an action button.`

## File conventions
- Component files: `src/react/components/ComponentName.tsx`
- Page files: `src/pages/PageName.tsx`
- Page Custom Component Files: `src/pages/PageName/ComponentName.tsx`
- Keep logic and markup clear: extract small subcomponents when repeated.
- Prefer Prop-driven components (props for data, handlers, and optional flags).

## What to avoid
- Do not output CDN `<script>` or `<link>` tags in components.
- Avoid inline styles unless necessary — prefer Tailwind utilities.
- Do not assume external CSS other than the stated CDNs.
