# Public redesign QA

## Automated verification

- `php artisan test --compact --no-ansi`: see final task report for exact total. `PublicRedesignTest` covers the shared shell, removal of language UI, article server-rendered content and controls, archives, search, quiz, and 404.
- `php artisan view:cache`: Blade compilation passed during implementation.
- `npm.cmd run build`: Vite build passed. Public layout references only the public CSS/JS entry points. Admin assets remain separate.
- Local HTTP smoke checks returned 200 for `/`, `/cari`, `/kategori`, `/login`, `/register`, `/forgot-password`, published demo articles, quiz, video, learning path, course, lesson, topic, tag, author, and editorial policy. `/akun` redirects guests to login and a missing URL returns 404.
- The published demo article HTML had one H1, one canonical, server-rendered body text, quiz CTA, and no legacy blue/indigo/purple/violet utility classes. SEO tests cover JSON-LD and robots behavior.

## Browser verification pending

The in-app browser was unavailable during this task. Visual and interaction results below require manual browser review. Do not treat source and HTTP checks as a visual pass.

| Viewport | Pages to inspect | Checks |
| --- | --- | --- |
| 1440 / 1280 | Home, article, archive, course | Header fit, logo size, type hierarchy, sidebar, footer |
| 1024 / 768 | Article, search, course, account | Grid collapse, TOC, card spacing, no horizontal scroll |
| 430 / 390 / 360 | All page families | One-column cards, readable type, menu panel, form controls, no clipping |

For each width, test the database-driven header links, search, account menu, contact link when configured, footer links, keyboard focus, mobile menu Escape, Back/Forward with `wire:navigate`, and loading indicator. On article pages also test TOC anchors, code copy and highlighting, YouTube play on click, bookmark, share, feedback, Q&A form, quiz CTA, previous/next, newsletter, and related cards. On learning pages test quiz radio/checkbox selection, progress, result, lesson completion, and continue learning. On auth pages test validation and password reset flow. Check image fallbacks, contrast in hover/disabled/error states, and reduced motion.

## Route checklist

- Home: `/`
- Article: published slug route
- Search: `/cari`
- Category index/detail: `/kategori`, `/kategori/{slug}`
- Tag/topic/author: `/tag/{slug}`, `/topik/{slug}`, `/author/{slug}`
- Video/quiz/path/course/lesson: `/video/{slug}`, `/kuis/{slug}`, `/belajar/{slug}`, `/kelas/{slug}`, `/kelas/{slug}/pelajaran/{lesson}`
- Reader: `/akun`, `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`
- Published static pages: `/about`, `/privacy-policy`, `/editorial-policy`, `/contact`, `/terms` when their page record is published
- Errors: 404, 403, 500

The `/tentang` URL is not a registered static route; the existing About route is `/about`.
