# Besofton Insights — public design system

The public site uses the homepage's warm editorial direction. CMS pages keep their own `app.css` and `app.js`; public pages load `public.css` and `public.js` through the shared layout. The homepage loads `public-home.js` only when its featured carousel needs it. Prism languages load only when article code blocks exist.

## Tokens

| Token | Value | Use |
| --- | --- | --- |
| `--public-bg` | `#f7f3eb` | Page background |
| `--public-surface` | `#fffdf9` | Cards and forms |
| `--public-text` | `#0a0a0a` | Primary text |
| `--public-muted` | `#57534e` | Supporting text |
| `--public-border` | `#ddd6c8` | Dividers and outlines |
| `--public-accent` | `#e8af4b` | Badges, buttons, rules |
| `--public-accent-hover` | `#f3c36d` | Button hover |
| `--public-dark` | `#0b0b0b` | Dark panels |

Gold is used for emphasis and controls. Small text on cream uses dark brown (`#825006`) rather than gold. Calculated contrast ratios: primary text/background 17.89:1, muted/background 6.89:1, kicker/background 6.12:1, gold button text/background 9.46:1, white/dark panel 19.68:1. These are token checks; they do not replace browser contrast QA for every rendered state.

## Type and layout

- Font: Outfit with Arial fallback. Body starts at 17px; long-form article body is 19px desktop and 18px mobile with 1.78 line height.
- Page and article titles use responsive clamps, heavy weight, tight tracking, and readable line height. Article content stays within 820px while its full layout allows a sticky TOC sidebar.
- The public shell is up to 1260px wide. Discovery cards use three columns, then two, then one. Course and account grids collapse on smaller screens.
- Breakpoints: 1200px and 1100px progressively compact desktop navigation; below 980px the header uses the mobile menu. Article/course layouts change at 900px, and content cards become one column at 530px.

## Reusable components

| Component | Class / file | Use |
| --- | --- | --- |
| Header and footer | `public.partials.site-header`, `site-footer` | Every public and reader page |
| Article card | `public.partials.card` | Search, archives, related posts |
| Hero | `.public-page-hero` | Listings and detail pages |
| Card / panel | `.public-card`, `.public-panel` | Discovery and account |
| Button | `.public-button`, `.public-outline-button` | Primary and secondary actions |
| Form | `.public-form-stack`, `.public-field`, `.public-input` | Auth, search, Q&A |
| Badge / chip | `.public-badge`, `.public-chip` | Category, content type, filters |
| Empty state | `.public-empty` | Collections with no records |
| Article prose | `.prose-besofton` | Server-rendered rich content |
| Steps / progress | `.public-step`, `.public-progress` | Path, course, lesson, quiz |

The header and footer read menu links from the menu database and only render external social/contact links when configured. `HeaderNavigationSeeder` conservatively adds the five product destinations (Insights, Topik, Belajar, Kelas, Video) and deactivates only the original default Cari menu item. The header uses those database links, falling back to the same five routes when the header menu is empty. Search is a separate action linking to `/cari`. The public language switch is removed; localization infrastructure is untouched. Mobile navigation uses Alpine, locks background scrolling, and closes on Escape or navigation. Internal links retain `wire:navigate`.

The discovery routes `/topik`, `/belajar`, `/kelas`, and `/video` use the existing topic, learning path, course, and video records. They show published content with pagination and exclude drafts and soft deleted records. Each page has its own title, description, and canonical URL.

## Content and accessibility

The article keeps its SEO head, Article and BreadcrumbList JSON-LD, server-rendered body, sources, quiz CTA, feedback, author, previous/next, Q&A, related content, and newsletter. The TOC is progressive enhancement: the article remains readable without JavaScript. YouTube frames load on interaction. Code copy buttons and syntax highlighting initialize after Livewire navigation without duplicate controls.

Controls have visible focus outlines, semantic labels, and minimum targets near 44px. Reduced motion is respected. Images have responsive sizing; card images use lazy loading and article hero imagery keeps eager priority.
