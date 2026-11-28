=== Handover – Service Reports for Agencies ===
Contributors: aditya
Tags: reports, pdf, client reports, malware cleanup, website optimization
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Branded service reports for your clients: malware cleanup and website optimization reports, with a review workflow and PDF export.

== Description ==

Handover turns the work you do on a client's website into a branded report they can keep. Your team fills the report in from wp-admin, a lead reviews and approves it, and the client gets a PDF. Handover was called Cleanup Report Generator before 2.2.0.

Two report types come with it, each with its own fields and sections, sharing the branding, the review workflow and the PDF layout:

* **Malware cleanup:** what was found and removed, screenshots, vulnerable components, the work done and how the site was verified, the security settings applied, and the client's next steps.
* **Website optimization:** PageSpeed scores of the tested pages before and after the work (averages worked out for you), the pre-work and post-work checklists, the optimization steps, whether the mobile and desktop targets were reached, and any code added to the site.

More report types can be added with a definition (see the FAQ); the edit screen, saving and the report follow from it.

Also included:

* A review workflow: Draft, Submitted, Needs revision, Approved, Completed. Only reviewers you choose ("Buddyleads") and administrators approve. Until a report is approved it carries a watermark and, by default, can't be downloaded.
* Editable checklists (follow-ups, security settings, blocklists, optimization tests) whose wording each report keeps as it was saved.
* A plugin and theme check that flags closed, abandoned and vulnerable items, and a plugin list that builds itself.
* Branding: name, logos, primary and status colours with presets, a light or dark theme, footer text.
* PDF export with the bundled Dompdf; without it, a print view the browser can save as PDF.
* History of every report: who edited, submitted, approved or downloaded it, and when.
* Data retention: reports nobody has edited for a set number of days are deleted automatically (30 by default; 0 keeps them).

Everything the plugin defines is prefixed (classes `Cleanup_Report_*`, constants `CLEANUP_REPORT_*`, hooks `cleanup_report_*`). Stored settings and report fields use `crg_` names.

== Installation ==

1. Upload the zip in Plugins › Add New › Upload Plugin and activate it. Use the release zip (it includes Dompdf), not a copy of the source repository.
2. Choose your defaults and branding in Reports › Settings › Branding, and your reviewers in Reports › Settings › Review.
3. Add a report from Reports › Add cleanup report or Add optimization report. Preview it or download the PDF from the report's screen.

== Frequently Asked Questions ==

= Who can do what? =

Give everyone who writes reports the Editor role. Writers edit their own reports and submit them; reviewers (picked in Settings › Review) and administrators approve or send back any report and manage the checklists; only administrators see Branding and Review. Settings › Review has the full table.

= Can I add my own report type? =

Yes. Add a definition with the `cleanup_report_types` filter: a label and a list of sections, each a list of fields (text, url, date, number, email, textarea, code, rich text, note, select, questions, table, screenshots, checklist). The edit screen, saving, the history and a generic report layout follow from it. See `includes/class-cleanup-report-types.php` for the two built-in definitions.

= Does deleting the plugin delete my reports? =

Not unless you ask it to: tick "Delete all reports and settings when the plugin is deleted" in Settings › Review first. Deactivating never deletes anything.

= Why do Chinese, Japanese or Korean characters show as boxes in the PDF? =

The bundled fonts (Roboto with DejaVu Sans as fallback) have no glyphs for them, nor for emoji.

== Screenshots ==

1. Choosing the kind of report to add.
2. The Website optimization edit screen: scores with live averages and the pre-work checklist.
3. The cover and summary page of an optimization report.
4. The review box: submit, approve or send back with notes.

== External services ==

The plugin can contact these services. It sends nothing about the site, its users or the report's client other than what is listed.

**WordPress.org Plugins and Themes API** (api.wordpress.org)
Used by "Check plugins & themes" on a report and by the plugin list, to see whether a plugin or theme is closed, when it was last released and which WordPress version it's tested up to. Sent: the plugin or theme folder names, when someone clicks Check or "Re-check WordPress.org now", and once a day for items not checked for 7 days if "Re-check the plugin list every day" is on (Settings › Review; off on new installs). Privacy policy: https://wordpress.org/about/privacy/

**WPVulnerability** (www.wpvulnerability.net)
Used at the same moments, to see whether the installed version of a plugin or theme has known vulnerabilities. Sent: the folder names only. Privacy: https://www.wpvulnerability.com/privacy/ · Licence and terms of the data: https://www.wpvulnerability.com/license/

**VirusTotal** (www.virustotal.com)
Only a link: the edit screen offers "check this site on VirusTotal", which opens VirusTotal's report for the website in your browser. The plugin sends nothing itself. Terms: https://docs.virustotal.com/docs/terms-of-service · Privacy: https://docs.virustotal.com/docs/privacy-policy

Reports also link to documentation pages (for example the Defender documentation on wpmudev.com, or a video playlist with the WPMU DEV defaults); those are ordinary links the client may open.

== Dompdf ==

Dompdf ships inside the plugin in `vendor-prefixed/`, moved into the plugin's own namespace (`CleanupReportGenerator\Vendor\`), so another plugin that bundles a different Dompdf version can't clash with it. Nothing needs installing on the server.

To build from source, run `composer install` (needs PHP 8 and Composer on the build machine). It installs Dompdf, then Strauss (in `tools/strauss`, build-only) copies it into `vendor-prefixed/` and re-namespaces it. Then zip the plugin without the files listed in `.distignore`, for example with `wp dist-archive .`. The GitHub workflow in `.github/workflows/build-zip.yml` does the same on every pull request and attaches the zip to each published release.

== Changelog ==

= 2.2.0 =
* Renamed to Handover – Service Reports for Agencies. Only the name changes in this release: the plugin folder, its settings and every report stay as they are.
* Report types. Each report is a Malware cleanup or a Website optimization report, picked when it's added (Reports › Add cleanup report / Add optimization report, or the "Add report" button, which asks). The type can't change afterwards. The Reports list has a Type column and a type filter. Existing reports are Malware cleanup reports (marked once on update).
* Website optimization reports, following the support team's template: PageSpeed scores of the tested pages before and after the work (a table or text, with live averages), an optional link to the shared Lighthouse JSON, pre-work and post-work checklists (Yes / No / N/A with notes, and "Same answers as pre-work"), screenshots for each, the optimization steps, code added to the site, the summary, and standard member texts. The report shows the averages coloured by PageSpeed's bands, whether the mobile and desktop targets were reached (worked out from the scores), and the code in full at the end. PDF name: <site>-optimization-report-<date>.pdf.
* Settings › Website optimization › Checklist & targets: the targets (mobile 80, desktop 95 by default) and the tests, each with its good answer (green; the other answer red) and default notes for before and after the work. Buddyleads can edit it, like the other checklists. A report keeps the wording and targets it was last saved with.
* Report types are definitions: a type lists its sections and fields, and the edit screen, saving, sanitising and the history follow from that. New types can be added with the cleanup_report_types filter, using the field types text, url, date, number, email, textarea, code, rich text, note with a standard text, select, questions, table, screenshots and checklist; a type without a template of its own gets a generic report layout.
* Defaults preset (Settings › Branding): WPMU DEV (the support team's brand, Defender checklist, Hub check and WPMU DEV products in the standard texts) or Generic (this site's name, neutral texts, Defender and Automate items off). Existing installs keep the WPMU DEV defaults; new installs start Generic. Saved settings always win.
* Outside services: the plugin list's daily re-check (WordPress.org and WPVulnerability.net) now has its own setting in Settings › Review. Existing installs keep it on; new installs start with it off. Checking plugins on a report still works either way. Scheduled jobs are set up from wp-admin only, so front-end pages do no extra work.
* Deleting the plugin can remove all its data: Settings › Review › "Delete all reports and settings when the plugin is deleted" (off by default; deactivating never deletes anything).
* Every text in wp-admin and in the report can be translated.
* Security: only Editors and up (Writers, Buddyleads) can open, preview or download other people's reports; Authors and Contributors see only their own. Data retention deletes only screenshots uploaded to the report, never an image picked from the Media Library that belongs to something else. "Check plugins & themes" needs edit rights on the report and is limited to 10 checks per person in 10 minutes. Uninstalling deletes only this plugin's own options.
* New installs keep reports until a retention period is set (existing installs keep their setting, 30 days by default). Settings only wp-admin needs are no longer loaded on every page of the site.
* On phones, the scores, components and table fields scroll sideways inside their box instead of squeezing their inputs. The theme's page Template box no longer shows on reports.
* Settings tabs are grouped: shared tabs first, then each report type's own.
* Data retention also removes the screenshots of optimization reports.
* The malware cleanup report and its PDF are unchanged (checked page by page against 2.1.0).

= 2.1.0 =
* Checklist names follow the team's report template, which also matches Defender's screens: "Hide Login URL" (was Mask login area), "Login & Access - Session Security" (Session protection), "Login & Access - Bot Protection" (Google reCAPTCHA), "Defender Hardening", "Enabling Audit Log", "Firewalls - Global Firewall", "Local Firewall - …", "Tools > Issues Scan" and so on, and the follow-ups "Update PHP Version", "Check any suspect admins", "Login & Access - Password Rules", "Review Post/Comment Section". Saved lists and reports not yet approved that still use an old default name are renamed once; names you changed yourself are kept. New Defender item: "Reports & Alerts - Scheduled Reports".
* Defender is in two parts, as in the template: "To be done by agent" and "To be done by member", on the edit screen and in the report ("Configured by our team" / "To be done by you"). Each setting's part is set in Settings › Defender checklist; by default Hide Login URL, Two-Factor Auth, Session Security, Bot Protection and the user-agent Scripts preset are the member's. Each row also links its section of the Defender docs ("Public doc").
* Pending actions is a rich-text editor (bulleted or numbered lists, bold, links). Plain-text lines from earlier reports are converted when the report is opened.
* Key finding is its own one-line field under Findings instead of the infection summary's first sentence. Reports saved before keep that sentence until it's changed.
* Security blocklists (delist request): tick the vendors still flagging the site (the list VirusTotal shows, with a filter and a link to the site's VirusTotal report); the report lists them under Your next steps with where to request delisting, and counts them as items to action. The vendors and their contact details are edited in the new Settings › Blocklists tab; the notes field stays for anything else.
* Hosting & access, from the template: Hosting Type, "Are backups created before and after the cleanup?", "Any sFTP/SSH accounts present?" and HUB team member, each an answer plus a note (links in notes are clickable). Shown in the report's Verification table when answered.
* Report texts checked against the template: "Every … cleanup includes" no longer claims more than the template does (e.g. "and its entry point" removed), the next-steps intro mentions content familiarity, and the standard SEO and "Staying protected" texts follow the template (no fixed 1–4 week timeline; Snapshot backups, shared hosting and uptime monitoring added). The Resources links to 2FA and Hide Login URL point at the Defender docs' current sections (the old anchors no longer existed).
* Footer text accepts {year} for the current year (the default now uses it), and a saved footer starting "© 2026" is switched to {year} once, so it no longer stays on the year it was saved.
* PDF export checks for everything it needs (the bundled Dompdf and the PHP mbstring, dom and gd extensions) and names what's missing on the Reports screens and in the print view it falls back to, instead of falling back silently.
* Dompdf's prepared fonts are kept in uploads/crg-cache/fonts (or the system temp folder) instead of inside the plugin, so read-only plugin folders keep Roboto and updates don't clear them.
* Buddyleads must be Editors or administrators (approving saves someone else's report, which WordPress only allows from Editor up); only they are listed in Settings › Review.
* Data retention (Settings › Review, administrators only): a daily cleanup permanently deletes reports nobody has edited for the set number of days (30 by default; 0 keeps them forever), including their history and their screenshots in the Media Library unless another report uses the same file. The tab shows how many reports the next cleanup will delete and what the last one did. Note when updating: reports untouched for 30+ days are deleted at the first daily run unless you change the setting first.
* Security: report fields (crg_*) are protected meta, so WordPress's generic custom-field paths (hidden custom-field inputs on save, Quick Edit, add-meta, XML-RPC) can't change them; previously a writer could mark their own report approved that way. Only Buddyleads and administrators can change a report's author. Approved and completed reports can't be deleted by writers. The plugin list CSV neutralises cells that would run as spreadsheet formulas. The PDF renderer no longer follows redirects when fetching images.
* Performance: one-time upgrades run from a single autoloaded version number (no extra queries on every admin page) with caches primed and a lock; the checklist settings aren't autoloaded on the front end; the plugin check sends requests in batches of 20 and caches only the fields it uses (e.g. WooCommerce's vulnerability list: ~21 KB instead of the full response); a logo with nothing to trim isn't rescanned on every PDF; PDF rendering raises the memory limit for large screenshots.
* Compatibility: explicit flags for html_entity_decode() and CSV functions, so behaviour is the same on PHP 7.4 to 8.5.
* Reinstall and Replace lists ignore leftover tag text from a mistyped tag in the editor's Code tab (e.g. "/li>" after a name), instead of printing it in the report.
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
* Status colours in Settings › Branding (success, warning, high, danger, neutral) next to the primary colour; text on each colour is chosen automatically (white or near-black) for contrast. A Cover gradient setting (0–100%) sets how strong the blue glow on the cover is; 0 removes it. Colour presets (Subtle, Balanced, Bold) fill all the colours and the cover gradient in one click; Bold is the original look.
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

== Upgrade Notice ==

= 2.2.0 =
Adds Website optimization reports and report types. Existing reports become Malware cleanup reports and keep working; your branding and checklists are kept.
