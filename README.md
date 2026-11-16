# Event Manager

**Version:** 1.1.1  
**Platform:** WordPress  
**Minimum PHP version:** 7.4 (declared in plugin header)

Event Manager provides administration and website displays for event years, venues, speakers, agenda sessions, tracks, recordings, sponsors, and historical event archives. It includes CSV import and export tools.

## Installation and upgrade

1. Back up your WordPress database and files.
2. Test the ZIP on a staging site before updating production.
3. In WordPress, go to Plugins > Add New Plugin > Upload Plugin, select the ZIP, and replace the installed version when prompted.
4. Verify the existing pages, shortcodes, event years, speakers, agenda, sponsors, recordings, archives, and import/export workflows.
5. If archive page slugs change, review public URLs and redirects.

**Compatibility:** The v1.1.x releases retain legacy `caes_` database identifiers and existing shortcode support. Do not rename stored post types, taxonomy names, metadata, or options manually. Newly created records still use legacy internal storage identifiers in v1.1.1. The public naming cleanup is not a full data-schema migration.

## Website shortcodes

Insert a WordPress Shortcode block on the relevant page.

| Purpose | Current shortcode | Notes |
| --- | --- | --- |
| Speaker directory | `[event_speakers year="2026"]` | Replace the year as needed. Legacy `[caes_speakers]` remains supported. |
| Agenda | `[event_agenda year="2026"]` | Replace the year as needed. |
| Sponsors | `[event_sponsors]` | Year-specific rendering depends on event context/configuration. |
| Archive index | `[event_archive_index]` | Lists historical event years. |
| Archive detail | `[event_archive_detail]` | The Past Event Detail template already includes the archive shortcode. Do not add it twice. |

The admin Overview and Speakers screens display the current `[event_speakers]` name as of v1.1.1.

## Managing content

- **Event years and venues:** Maintain event-year records and shared venue information. Archive pages can be assigned to specific years.
- **Speakers:** Maintain profiles, biographies, headshots, company/job details, and event-year associations.
- **Agenda:** Manage sessions, speakers, moderators, tracks, and session background colours.
- **Recordings:** Link recordings to speakers and event years, with video URLs and related metadata.
- **Sponsors:** Maintain sponsor profiles, logos, tiers, and assignments by year. Display order follows tier order, then alphabetical order within each tier.
- **Archives:** Assign an existing WordPress page and its slug to an event year. The page's parent is managed separately in WordPress. Check URL changes and redirects before publishing.

## CSV import and export

The Import / Export admin screen is intended for administrators. It supports sample CSV downloads, upload previews, validation, and confirmation. Recommended import order: Venues, Event Archives, Speakers, Tracks, Agenda, Recordings, Sponsorship Tiers, Sponsors, Sponsor Assignments, and Agenda Colours.

CSV uploads are limited to **2 MB** and **500 rows**. Preview sessions expire after **20 minutes**. Existing published posts may require explicit approval for staged updates. Imports do not automatically delete records or download external images. See `IMPORT-EXPORT-README.md` in the plugin for detailed constraints and key conventions.

## Legacy compatibility and current limitations

Version 1.1.0 introduced generic admin page slugs (`event-manager-*`) and the `[event_speakers]` shortcode. Old `caes_` shortcodes and stored identifiers remain supported for existing content. Older bookmarked admin URLs redirect to the current pages.

**Important:** This does not yet meet the longer-term goal of generic `event_*` storage for newly created records. Internal post types, metadata, option names, PHP functions, and some CSS identifiers remain legacy for compatibility. A future version would require a tested dual-schema strategy or migration.

## Troubleshooting checklist

- If speakers or agenda items do not appear, confirm they are published and assigned to the intended event year.
- If an archive does not render, verify the assigned WordPress page, parent, slug, and Past Event Detail template.
- If a shortcode appears as text, confirm it is in a Shortcode block and uses the correct spelling.
- If sponsor or speaker data is missing after an update, do not rename `caes_` records manually. Restore from backup if needed and investigate on staging.
