=== Cleanup Report Generator ===
Contributors: aditya
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 2.1.0
Requires PHP: 7.4
License: GPLv2 or later

Create branded cleanup/health reports from the WP Admin. Export as PDF (uses Dompdf if available, or falls back to browser print-to-PDF).

== Description ==
Malware cleanup reports for members: staff fill in a report in wp-admin, a Buddylead reviews it, and the member gets a branded PDF.

- Reports post type with a review workflow (Draft, Submitted, Needs revision, Approved, Completed)
- Follow-ups and Defender checklists, editable in Reports › Settings, with links built from the report's website URL
- Plugin and theme check that flags abandoned, closed and vulnerable items, plus a self-building plugin list
- Branding: name, logos, primary and status colours, light or dark report theme, footer
- PDF export with Dompdf, bundled with the plugin; without it, a print view the browser can save as PDF

Works on any WordPress site: nothing in the code points at a particular site, and everything the plugin defines is prefixed (classes `Cleanup_Report_*`, constants `CLEANUP_REPORT_*`, hooks `cleanup_report_*`). Stored settings and report fields keep their original `crg_` names so existing reports carry over.

== Installation ==
1. Upload the zip in Plugins › Add New › Upload Plugin, and activate it. Use the `cleanup-report-generator-<version>.zip` attached to the GitHub release (it includes Dompdf), not a copy of the repository.
2. Set the branding in Reports › Settings › Branding and pick Buddyleads in Reports › Settings › Review.
3. Add a report in Reports › Add New; preview it or download the PDF from the report's screen.

== Dompdf ==
Dompdf ships inside the plugin in `vendor-prefixed/`, moved into the plugin's own namespace (`CleanupReportGenerator\Vendor\`), so another plugin that bundles a different Dompdf version can't clash with it. Nothing needs installing on the server.

To build from source, run `composer install` (needs PHP 8 and Composer on the build machine). It installs Dompdf, then Strauss (in `tools/strauss`, build-only) copies it into `vendor-prefixed/` and re-namespaces it. Then zip the plugin without the files listed in `.distignore`, for example with `wp dist-archive .`. The GitHub workflow in `.github/workflows/build-zip.yml` does the same on every pull request and attaches the zip to each published release.

== Changelog ==

= 2.1.0 =
* Security: report fields (crg_*) are protected meta, so WordPress's generic custom-field paths (hidden custom-field inputs on save, Quick Edit, add-meta, XML-RPC) can't change them; previously a writer could mark their own report approved that way. Only Buddyleads and administrators can change a report's author. Approved and completed reports can't be deleted by writers. The plugin list CSV neutralises cells that would run as spreadsheet formulas. The PDF renderer no longer follows redirects when fetching images.
* Performance: one-time upgrades run from a single autoloaded version number (no extra queries on every admin page) with caches primed and a lock; the checklist settings aren't autoloaded on the front end; the plugin check sends requests in batches of 20 and caches only the fields it uses (e.g. WooCommerce's vulnerability list: ~21 KB instead of the full response); a logo with nothing to trim isn't rescanned on every PDF; PDF rendering raises the memory limit for large screenshots.
* Compatibility: explicit flags for html_entity_decode() and CSV functions, so behaviour is the same on PHP 7.4 to 8.5.
* Reinstall and Replace lists: the folder can be typed in brackets, e.g. "Slider Revolution (revslider) – no licence provided", so no switching to the Code tab. The old <code>…</code> form still works (the plugin check's buttons use it); "(Pro)"-style edition words stay in the name.
* "Reinstall these plugins & themes" has its own Why column for the reason after the dash, like "Replace these plugins & themes" (it was squeezed in after the folder name).
* "Security blocklists (delist request)" and "Additional sites on this hosting account" are text areas (one item per line); line breaks show in the report.
* The default Verification text no longer names a third-party scanner brand ("Front-end scan with external malware scanners such as VirusTotal"). Existing reports have that one line replaced once; nothing else in them changes.
* History box on every report: who did what and when. It notes when the report was created, edits (by section, e.g. "Edited Findings, Defender", merged when the same person saves again within 15 minutes), title changes, each review step with the note written for it (submitted, sent back, resubmitted, approved, marked completed), PDF downloads, and moving to or restoring from the Trash. Kept with the report (last 200 entries); not shown in the PDF.
* Save & review box replaces WordPress's Publish box on reports: one Save button that says what it will do ("Save draft", "Submit for review", "Send back for revision", "Approve", "Mark completed"), Move to Trash, and no WordPress status, visibility or publish date to confuse things. WordPress's status now follows the review status (Draft and Needs revision = Draft, Submitted = Pending, Approved and Completed = Published); existing reports are brought in line once, without changing their modified date. After a review step the notice says what happened ("Report approved."), and the list no longer shows "— Draft" / "— Pending" next to titles.
* PDF download waits for approval: until a report is approved, the Download PDF buttons are replaced by a note and the download link is refused; Preview stays available. Administrators can turn this off in Settings › Review.
* Reports that aren't approved yet are marked with a diagonal watermark ("NOT FINAL" by default, editable by administrators in Settings › Review) in the browser preview and, if downloaded early, on every PDF page.
* Admin CSS and JS are cache-busted by file time, so changes show without a hard refresh.
* Ready for any site: classes, constants and hooks use a full `cleanup_report` prefix (WordPress coding standards reject the old 3-letter `CRG_`, and Plugin Check couldn't detect it). Class files renamed to match (`includes/class-cleanup-report-*.php`). The PDF image filter is now `cleanup_report_pdf_allowed_hosts`. Stored option and meta names are unchanged.
* Dompdf and its libraries are bundled under the plugin's own namespace (Strauss, `vendor-prefixed/`), so a different Dompdf version from another plugin can't break PDF export, or the other way round. The plugin zip needs no Composer on the server.
* Settings access: Buddyleads now edit the Follow-ups and Defender checklists (and can restore their defaults) as well as the Plugin list; Branding and Review stay administrator-only, and Writers have no Settings page.
* "Every … cleanup includes" in the report uses the brand name from Settings instead of a fixed company name.
* PDF export redesigned as a client-ready report (templates/report-pdf.php): full-bleed cover, "At a glance" summary page with contents, numbered sections in the order of the support team's report (visual identification, infection summary, vulnerable components, actions taken, verification, Defender, next steps, important to know), running header and footer with page numbers, screenshots scaled to fit.
* Cover redesigned in the wpmudev.com style: near-black page, white type, WPMU DEV blue accent and soft glow, stats in a rounded dark card. The client name shrinks to fit and long names and URLs are shortened, so the cover never overflows. Inner pages use the same palette (default brand colour is now #0059FF).
* Light (day) report theme by default: a soft cover tinted from the primary colour, white card, light panels and table headers. The previous dark look is still available (Settings › Branding › Report theme). Separate logos for light and dark covers.
* Logos are trimmed of empty (transparent or white) borders automatically, so they line up with the cover text and show at full size; the cropped copy is kept in uploads/crg-cache/.
* Pills and tags (cover label, status chip, table statuses, screenshot badges) have their text centred: capitals sat low with the normal line height.
* Status colours in Settings › Branding (success, warning, high, danger, neutral) next to the primary colour; text on each colour is chosen automatically (white or near-black) for contrast.
* Access: give everyone who works on reports the Editor role (WordPress needs it to save someone else's report). Writers edit their own reports and can preview any; Buddyleads open and edit any report and manage the Follow-ups, Defender checklist and Plugin list settings; administrators have full access: every report, approving, and every setting, and only they see the Branding and Review tabs. Writers have no Settings page. Approved reports are read-only for Writers except marking them Completed. A who-can-do-what guide is on the Review tab.
* Solid colours throughout the report: red, amber, green and blue tiles, status pills and badges with white or black text, black table headers, a dark "Key finding" panel, and darker body text for contrast.
* "Preview in browser" now shows the PDF's own layout, page by page, with buttons to download the PDF or go back to editing (it replaces the old separate web template, which had drifted from the PDF and loaded a chart from quickchart.io). Without Dompdf, "Download PDF" opens this view with the print dialog.
* Removed the Accent colour setting; only the old web template used it.
* The PDF is set in Roboto, wpmudev.com's typeface (bundled in assets/fonts, SIL Open Font License 1.1). Characters Roboto doesn't have fall back to DejaVu Sans.
* Long words with no spaces (payloads, slugs, URLs) wrap inside tables and text instead of pushing past the margin; table columns keep their set widths.
* Page breaks are controlled: headings always stay with their content, short tables never split, long tables repeat their header row, images never split.
* New report fields: vulnerable/outdated components table, suggested improvements, additional sites and delist status, and follow-ups for PHP update, Automate and reviewing posts/comments.
* Security: view/export now check the user can edit the report (previously any logged-in user with a valid link could download it).
* Security: the PDF renderer only fetches images from this site's uploads, the site itself and the brand logo host (filter: cleanup_report_pdf_allowed_hosts). Previously any screenshot URL was fetched, including internal addresses.
* Security: Dompdf updated 3.1.2 → 3.1.6 (fixes CVE-2026-56722 local file read, CVE-2026-55554 chroot bypass and four others).
* Review workflow: authors can no longer approve their own reports or write reviewer notes; the reviewed date is now recorded.
* Severity counts can no longer be negative; URLs are sanitised on save.
* Follow-ups and the Defender checklist are now editable under Reports › Settings: add, rename, reorder, turn off or delete items, and set the text the report shows for each status (with {site}, {host} and {wp_admin} filled in from the report). Each report keeps the wording it was saved with; reports from 2.0 are read as before and upgraded when next saved.
* Settings moved from Settings › Cleanup Reports to Reports › Settings, with tabs, a colour picker and a logo picker.
* Edit screen reorganised in the order of the report (Client & website, Findings, Work done & verification, Defender, Client next steps, Client guidance) with section links under the title. Checklists use one-click status buttons, show what the report will say, and Defender has "Mark all applied". Components are entered in a table (or as text). Screenshots show thumbnails, can be reordered, and take their caption from the media library.
* Autofill: the website URL is tidied (https:// added, wp-admin paths removed), the title is suggested from the client or site, the SEO text uses the site's domain, and "Vulnerable components" can be counted from the table.
* Follow-ups now have three statuses: Already configured (in place before the cleanup), Done (we did it during the cleanup) and Member to do. Clicking the selected status again (or pressing Esc) leaves the item out. Each follow-up has its own report text for Done; the PDF shows In place / Done / Required / Recommended.
* Search engines (SEO) and Staying protected use rich-text editors, so links can be added. The standard text is shown under each editor and can be copied in to adapt; notes written as plain text in older reports keep their lists and headings.
* The cover's headline is now the website's domain (sized to fit on one line), with "Prepared for …" under it; the old "Client name" field is now "Prepared for" and optional. The running header shows the website, the PDF file is named after the domain, and staff-facing text says "member" instead of "client".
* Check plugins & themes (report form, Findings): paste the output of `wp plugin list` / `wp theme list` from the client's site and see which items are closed on WordPress.org, no longer maintained (no release for 2 years, or tested 3+ WordPress versions behind), vulnerable in the installed version, or out of date. One click adds them to the components table and the Replace or Reinstall list. Uses the free WordPress.org and WPVulnerability.net APIs (only folder names are sent; results cached 12 hours).
* Plugin list (Reports › Settings): builds itself. Every plugin and theme checked on a report is added, with how many reports it was seen in. WordPress.org items get their status automatically (maintained, not maintained, closed) and are re-checked weekly by a daily WP-Cron job; unfixed vulnerabilities are flagged. Premium and custom items land in "Needs a decision" and are marked Maintained or Not maintained once; every later report uses that answer. Filters, search, "Re-check WordPress.org now" and a CSV download. Comes with common premium products already marked.
* A Report box on the edit screen downloads the PDF or opens the preview, and warns about unsaved changes. The Reports list shows client, website, review status and report date (sortable).
* Components: new "High" severity (Critical, High, Medium, Low) in the table, the text box and the PDF. Typed severities accept common spellings ("crit", "moderate", "minor"); anything else is flagged instead of silently becoming Medium. The plugin check rates CVSS 9+ as critical and 7+ as high.
* Review workflow: Draft → Submitted for review → Needs revision / Approved → Completed. Anyone can draft and submit; only Buddyleads (chosen in Reports › Settings › Review) and administrators can approve or send a report back; once approved, anyone can mark it Completed. Who approved and who completed it, and when, is recorded. The old "Reviewed" status reads as Submitted.
* "Items for you to action" (cover and summary page) counts everything the member has to do: follow-ups and Defender settings marked for the member, each line in Pending actions, and each plugin or theme to replace or reinstall.
* Defender checklist statuses: Applied, Pending (not configured yet) and Member to do; the report shows Applied, Pending and Your action. Like follow-ups, there's no "Leave out" button: click the selected status again (or press Esc) to leave a setting out. "Pending" in reports saved earlier reads as Member to do / Your action, which is what it meant then.
* Defender checklist and follow-ups link to the exact screen on the member's site ("Visit to configure" in the report), built from the report's website URL: Defender pages and views as linked in the team's report template (e.g. wdf-ip-lockout&view=local-firewall), plus Users and Comments for the follow-ups. Links are editable per item in Settings ({wp_admin} placeholder); reports saved earlier get them too. Four Defender items added from the template: login protection, malicious bot detector, catch fake bots, blocklist monitor.
* Task URL field (internal): links the report to our task. It's never shown in the report; the Reports list gets an "Open task" link.
* Review workflow: notices now follow the review status (Submitted, Needs revision with the reviewer's notes); the submitted date is recorded by the server.
* Removed unused PDF code paths (TCPDF, mPDF, FPDF, hand-built PDF) and duplicate class files.
* Plugin Check: passes with no errors (output escaping, wp_date()/wp_parse_url(), unslashed input, setting sanitisation, direct-access guard). Saving keeps backslashes in text.
* Dev files (tests, install script, markdown docs) are listed in .distignore and left out of the plugin zip.
* A GitHub workflow builds the installable zip (Dompdf included) on every pull request and attaches it to each published release.
* Known limitation: Chinese/Japanese/Korean text and emoji show as empty boxes in the PDF (neither Roboto nor the fallback DejaVu font has glyphs for them).

== Credits ==
* Roboto font: Copyright 2011 The Roboto Project Authors, SIL Open Font License 1.1 (assets/fonts/OFL.txt). https://github.com/googlefonts/roboto-3-classic
* Dompdf: LGPL-2.1, via Composer.
