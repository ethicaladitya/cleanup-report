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

Administrators can make their own report types in Reports › Report types, without code: a name, the sections and their fields (text, dates, choices, questions, checklists, tables, rich text, screenshots and more). The edit screen, saving, the review workflow and the PDF follow from it. Developers can also add types with a definition (see the FAQ).

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

Yes. In Reports › Report types (administrators), click "Add report type", or "Duplicate" on an existing type to start from its fields. Give it a name, add sections and fields, and save: "Add … report" appears in the Reports menu. A type can be turned off (it can't be picked for new reports; its reports still open and print) and deleted once no report uses it.

Editing a type is safe for reports already written: renamed and reordered fields keep their values; a removed field keeps its value in each report, listed on the report's edit screen, where it can be removed; an approved report keeps printing as it was approved.

Developers can also add a definition with the `cleanup_report_types` filter: a label and a list of sections, each a list of fields (text, url, date, number, email, textarea, code, rich text, note, select, questions, table, screenshots, checklist). The edit screen, saving, the history and a generic report layout follow from it. See `includes/class-cleanup-report-types.php` for the two built-in definitions.

= Does deleting the plugin delete my reports? =

Not unless you ask it to: tick "Delete all reports and settings when the plugin is deleted" in Settings › Review first. Deactivating never deletes anything.

= Why do Chinese, Japanese or Korean characters show as boxes in the PDF? =

The bundled fonts (Roboto with DejaVu Sans as fallback) have no glyphs for them, nor for emoji.

= Does PDF export need anything installed on the server? =

No. Dompdf ships inside the plugin (`vendor-prefixed/`), moved into the plugin's own namespace (`CleanupReportGenerator\Vendor\`), so another plugin that bundles a different Dompdf version can't clash with it. The server needs the PHP extensions mbstring, dom and gd; without them "Download PDF" opens a print view your browser can save as PDF, and the Reports screens say what's missing.

= How is the plugin built from source? =

Run `composer install` (PHP 8 and Composer on the build machine). It installs Dompdf, then Strauss (in `tools/strauss`, build-only) copies it into `vendor-prefixed/` and re-namespaces it. Zip the plugin without the files listed in `.distignore`, for example with `wp dist-archive .`. The GitHub workflow in `.github/workflows/build-zip.yml` does the same on every pull request and attaches the zip to each published release.

== Screenshots ==

1. Choosing the kind of report to add.
2. The Website optimization edit screen: scores with live averages and the pre-work checklist.
3. The cover and summary page of an optimization report.
4. The review box: submit, approve or send back with notes.

== External services ==

The plugin can contact these services. It sends nothing about the site, its users or the report's client other than what is listed.

**WordPress.org Plugins and Themes API** (api.wordpress.org)
Used by "Check plugins & themes" on a report and by the plugin list, to see whether a plugin or theme is closed, when it was last released and which WordPress version it's tested up to. Sent: the plugin or theme folder names, when someone clicks Check or "Re-check WordPress.org now", and once a day for items not checked for 7 days if "Re-check the plugin list every day" is on (Settings › Review; off on new installs). Privacy policy: https://wordpress.org/about/privacy/ · API documentation: https://codex.wordpress.org/WordPress.org_API (WordPress.org publishes no separate terms for its API)

**WPVulnerability** (www.wpvulnerability.net)
Used at the same moments, to see whether the installed version of a plugin or theme has known vulnerabilities. Sent: the folder names only. Privacy: https://www.wpvulnerability.com/privacy/ · Licence and terms of the data: https://www.wpvulnerability.com/license/

**VirusTotal** (www.virustotal.com)
Only a link: the edit screen offers "check this site on VirusTotal", which opens VirusTotal's report for the website in your browser. The plugin sends nothing itself. Terms: https://docs.virustotal.com/docs/terms-of-service · Privacy: https://docs.virustotal.com/docs/privacy-policy

**Links in reports** (nothing is sent until someone clicks them)
* Website optimization reports link to the Lighthouse viewer (https://googlechrome.github.io/lighthouse/viewer/) when a shared Lighthouse JSON link is given. Google privacy policy: https://policies.google.com/privacy
* With the WPMU DEV defaults: the Defender documentation on wpmudev.com, and a video playlist on YouTube (https://www.youtube.com/) in the optimization report's standard text. WPMU DEV privacy policy: https://wpmudev.com/privacy-policy/ · YouTube terms: https://www.youtube.com/t/terms

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
* Tested on PHP 8.2, 8.3, 8.4 and 8.5 with WordPress 7.1; checked for PHP 7.4+ compatibility with PHPCompatibilityWP.
* Switching the Defaults preset no longer affects saved answers: "our hosting" is stored the same way in both presets, only its label changes.
* Security: only Editors and up (Writers, Buddyleads) can open, preview or download other people's reports; Authors and Contributors see only their own. Data retention deletes only screenshots uploaded to the report, never an image picked from the Media Library that belongs to something else. "Check plugins & themes" needs edit rights on the report and is limited to 10 checks per person in 10 minutes. Uninstalling deletes only this plugin's own options.
* New installs keep reports until a retention period is set (existing installs keep their setting, 30 days by default). Settings only wp-admin needs are no longer loaded on every page of the site.
* On phones, the scores, components and table fields scroll sideways inside their box instead of squeezing their inputs. The theme's page Template box no longer shows on reports.
* Settings tabs are grouped: shared tabs first, then each report type's own.
* Data retention also removes the screenshots of optimization reports.
* The malware cleanup report and its PDF are unchanged (checked page by page against 2.1.0).

* Performance: the update runs in batches of 100 reports (continuing in the background on large sites) and marks report types with one query; the plugin's classes load only when needed, and front-end pages load almost none of them; report types build their fields only on screens that use them; the plugin check gives up on slow answers after a time budget (checking again continues) and saving the plugin list looks up only the item added.
* Edit screens: the heading names the report type; scores are checked as you type (0–100, full page addresses) and a new optimization report starts with a row to fill in.
* Report type builder (Reports › Report types, administrators only): make, edit, duplicate, turn off and delete report types without code. Fields: short text, plain and rich text, rich text with a standard text, web address, date, number, choice from a list, questions with answers, checklist, table, screenshots and code. Drawn with the standard report layout; "Preview the report" shows it with sample values.
* Duplicating a built-in type copies its fields that the builder offers and names the special ones it can't copy.
* Reports keep their values when their type changes; approved reports keep printing as they were approved, and fields removed from a type are listed on each report's edit screen until removed from it.

Changes before 2.2.0 are in changelog.txt.

== Credits ==
* Roboto font: Copyright 2011 The Roboto Project Authors, SIL Open Font License 1.1 (assets/fonts/OFL.txt). https://github.com/googlefonts/roboto-3-classic
* Dompdf (LGPL-2.1) and the libraries it bundles: php-font-lib (LGPL-2.1), php-svg-lib (LGPL-3.0), masterminds/html5 (MIT), sabberworm/php-css-parser (MIT). DejaVu fonts (Bitstream Vera license) come with Dompdf.

== Upgrade Notice ==

= 2.2.0 =
Adds Website optimization reports, report types, and Reports › Report types for making your own without code. Existing reports become Malware cleanup reports and keep working; your branding and checklists are kept.
