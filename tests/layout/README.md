# PDF layout check

Renders the report PDF on a test WordPress site through the same code as **Export PDF**, then checks every page
at the pixel level. Run it after any change to `templates/report-pdf.php`, `includes/class-cleanup-report-pdf.php` or the
report fields.

```bash
tests/layout/run.sh            # all fixtures
tests/layout/run.sh long       # one fixture
```

Exit code is the number of problems (0 = pass). Pages are saved as PNGs in `tests/layout/out/<fixture>/`
(`page-N.png`, plus `overview.png` with all pages side by side).

## Requirements

- macOS with Xcode command line tools (the checker uses PDFKit; tools are compiled on first run into `.build/`).
- `composer install` done locally (the deploy copies `vendor/`).
- SSH access to a **test** site with WP-CLI. The script copies the plugin over the site's copy
  (`rsync --delete`, files in `.distignore` left out) and creates/overwrites three reports, so never point it at a
  live site.

| Variable | Default | |
|---|---|---|
| `CRG_SSH_HOST` | `iaditya` | ssh host of the test site |
| `CRG_WP_PATH` | `site/public_html` | WordPress root on that host |
| `CRG_NO_DEPLOY` | unset | `1` = don't copy the plugin, test what's on the site |

## Fixtures

Each fixture finds or creates its report by title and resets all its fields, so reruns are repeatable. The two
screenshots are mock Defender scans (no real site data); they're uploaded to the media library if missing.

| Fixture | Report title | What it catches |
|---|---|---|
| `sample` | Sample – malware cleanup report | The full, realistic report: the one to look at for design. Anonymised. |
| `minimal` | Layout test – minimal | Nothing filled in: empty sections, "0 of 0", intros with nothing under them. |
| `long` | Layout test – long | Every field at its worst: 150-character client name, long URL, base64 payload and 70-character slug with no spaces, 25 components, 20 pending actions, 6 screenshots. |

## Checks (`tools/layoutcheck.swift`)

| Check | Fails when |
|---|---|
| OVERFLOW | ink in the left/right margin of a content page (text or a table wider than the page) |
| CLIP | content running into the footer |
| HEADING | a section heading in the bottom 18% of a page |
| STRANDED | a heading that is the last thing on a page, its content on the next |
| OVERLAP | two lines of text drawn on top of each other (any page, cover included) |
| OUTSIDE | cover text beyond the page's safe area (cut off at the edge) |
| GLANCE | the "At a glance" summary spilling onto a second page |
| GAP *(warning)* | a page that ends above 70%: expected before an image or short table that can't be split |
