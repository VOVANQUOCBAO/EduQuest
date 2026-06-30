---
name: Academic Precision
colors:
  surface: '#fbf8ff'
  surface-dim: '#dad9e3'
  surface-bright: '#fbf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f2fc'
  surface-container: '#eeedf7'
  surface-container-high: '#e8e7f1'
  surface-container-highest: '#e3e1eb'
  on-surface: '#1a1b22'
  on-surface-variant: '#444653'
  inverse-surface: '#2f3037'
  inverse-on-surface: '#f1f0fa'
  outline: '#757684'
  outline-variant: '#c4c5d5'
  surface-tint: '#3755c3'
  primary: '#00288e'
  on-primary: '#ffffff'
  primary-container: '#1e40af'
  on-primary-container: '#a8b8ff'
  inverse-primary: '#b8c4ff'
  secondary: '#006a61'
  on-secondary: '#ffffff'
  secondary-container: '#86f2e4'
  on-secondary-container: '#006f66'
  tertiary: '#611e00'
  on-tertiary: '#ffffff'
  tertiary-container: '#872d00'
  on-tertiary-container: '#ffa583'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dde1ff'
  primary-fixed-dim: '#b8c4ff'
  on-primary-fixed: '#001453'
  on-primary-fixed-variant: '#173bab'
  secondary-fixed: '#89f5e7'
  secondary-fixed-dim: '#6bd8cb'
  on-secondary-fixed: '#00201d'
  on-secondary-fixed-variant: '#005049'
  tertiary-fixed: '#ffdbce'
  tertiary-fixed-dim: '#ffb59a'
  on-tertiary-fixed: '#380d00'
  on-tertiary-fixed-variant: '#802a00'
  background: '#fbf8ff'
  on-background: '#1a1b22'
  surface-variant: '#e3e1eb'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  title-lg:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 32px
  sidebar-width: 280px
---

## Brand & Style

This design system is built for the modern educational environment, balancing the gravity of academia with the efficiency of high-performance SaaS. The brand personality is professional, organized, and supportive, designed to reduce cognitive load for educators and students alike.

The aesthetic leans into **Modern Corporate** with a focus on functional clarity. It utilizes a structured information hierarchy, generous whitespace, and a clean "layered" interface where content sits on distinct elevated planes to guide the user's focus. The emotional response should be one of confidence and calm, transforming complex data into actionable insights.

## Colors

The palette is anchored by **Professional Blue**, conveying authority and trust. **Soft Teal** serves as the secondary accent, used for success states, progress indicators, and interactive highlights to provide a refreshing contrast to the primary blue. 

The background uses a cool **Light Gray** to reduce screen glare during long periods of use, while pure white is reserved for high-priority "Surface" cards. Semantic colors (Red for alerts, Amber for warnings) should follow the same saturation levels as the primary palette to maintain visual harmony.

## Typography

**Inter** is the sole typeface for this design system, chosen for its exceptional legibility in data-dense environments. 

- **Headlines:** Use tighter letter-spacing and semi-bold weights to create a strong visual anchor for page titles.
- **Body Text:** Standard weight for readability; use `body-md` for general content and `body-sm` for secondary descriptions or metadata.
- **Labels:** Use `label-md` for section headers within sidebars or small all-caps headers to differentiate from interactive body text.
- **Data:** For numeric values in tables, ensure tabular lining figures are used to maintain vertical alignment.

## Layout & Spacing

This design system employs a **Fluid Grid** model with fixed-width sidebars for navigation. 

- **Desktop (1440px+):** 12-column grid with 24px gutters. The sidebar is fixed at 280px, while the main content area expands.
- **Tablet (768px - 1024px):** 8-column grid. The sidebar collapses into an icon-only rail or hides behind a hamburger menu.
- **Mobile (<768px):** 4-column grid. Margins reduce to 16px. Cards become full-width.

Spacing follows a strict 4px/8px baseline rhythm. All vertical margins between sections should use `xl` (32px) to maintain a feeling of openness.

## Elevation & Depth

Depth is conveyed through **Tonal Layers** and **Ambient Shadows**. 

1. **Level 0 (Background):** `#f8fafc`. Flat.
2. **Level 1 (Cards/Sidebar):** White background. Shadow: `0px 1px 3px rgba(0,0,0,0.1), 0px 1px 2px rgba(0,0,0,0.06)`. This is the standard state for content containers.
3. **Level 2 (Hover States/Modals):** Shadow: `0px 10px 15px -3px rgba(0,0,0,0.1), 0px 4px 6px -2px rgba(0,0,0,0.05)`. Used when a user interacts with a card or when a dialog takes priority.

Borders are kept minimal; use a `1px` stroke in `#e2e8f0` for Level 1 elements to ensure definition against the light gray background.

## Shapes

The design system uses a consistent **Rounded** language to soften the "institutional" feel of school software.

- **Standard Components (Buttons, Inputs):** `0.5rem` (8px).
- **Cards & Containers:** `1rem` (16px).
- **Large Sections (Hero Areas):** `1.5rem` (24px).

Interactive elements like Checkboxes and Radio buttons should maintain a `0.25rem` radius to feel cohesive with the larger elements while remaining distinct.

## Components

### Buttons & CTAs
- **Primary:** Solid `#1e40af` with white text. High emphasis.
- **Secondary:** Outline in `#1e40af` or solid `#0d9488` for positive actions (e.g., "Submit Grade").
- **Ghost:** No background, blue text. Used for less frequent actions in headers.

### Navigation Sidebar
- Background: White with a right-hand border in `#e2e8f0`.
- Icons: 24px, 1.5pt stroke weight. Active state uses a vertical blue bar (4px wide) on the left and a subtle teal background tint.

### Modern Tables
- **Header:** Light gray background (`#f1f5f9`), all-caps `label-sm` typography.
- **Rows:** White background with a `1px` bottom border.
- **Hover State:** Apply a very subtle scale effect (1.005x) and change background to `#f8fafc`.

### Cards
- Use `1rem` corner radius. Content should have `24px` internal padding (`spacing.lg`).
- Header within cards should use `title-lg`.

### Input Fields
- White background with `#e2e8f0` border. On focus, the border changes to `#1e40af` with a 3px soft blue outer glow.
- Labels sit above the field in `label-sm` weight.

### Chips & Badges
- Used for status (e.g., "Present", "Late"). Use high-contrast backgrounds with low-opacity fills (e.g., Success: Teal text on 10% opacity teal background).