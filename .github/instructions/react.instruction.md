---
applyTo: '**/*.{tsx,jsx}'
---

# GitHub Copilot Instructions — React + Tailwind + Flowbite + Font Awesome Pro (CDN)

**Purpose:** Guide Copilot to generate React UI components styled with Tailwind, using Flowbite for interactive pieces and Font Awesome Pro for icons.
(These instructions assume the required CDNs are already added to the app entry point; do not emit `<script>`/`<link>` tags inside components.)

## Project assumptions
- Framework: **React** (functional components + hooks).
- Build/tooling: Create React App, Vite, or similar.
- The following CDNs are already present in the project's HTML entry (do not generate these tags inside components):
  - Tailwind CDN: `https://cdn.tailwindcss.com`
  - Flowbite JS CDN: `https://unpkg.com/flowbite/dist/flowbite.min.js`
  - Font Awesome Pro CDN
  - Main HTML at `index.dev.html`

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
