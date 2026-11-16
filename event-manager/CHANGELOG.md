# Event Manager changelog

This changelog covers **v1.0.0 through v1.1.1**. Entries are based on the release notes included in the v1.1.1 plugin archive.

## 1.1.1: Shortcode display fix

- Updated the Overview and Speakers admin pages to show `[event_speakers year="2026"]` instead of the legacy shortcode.
- Retained `[caes_speakers]` for existing website pages.
- Restored the Copy shortcode click handler on the renamed `event-manager-*` admin pages.
- Did not change database identifiers, post types, or existing content.

## 1.1.0: Public naming and compatibility

- Renamed primary admin page slugs to `event-manager-*`.
- Added redirects from older admin page URLs, preserving scalar query parameters.
- Added `[event_speakers]` and retained `[caes_speakers]` as a compatibility alias.
- Continued support for `[event_agenda]`, `[event_sponsors]`, `[event_archive_index]`, and `[event_archive_detail]`.
- Changed the plugin text domain to `event-manager`.
- Removed Canadian Email Summit-specific default years and venue names from fresh installations; existing saved archives remain intact.
- Preserved legacy internal post types, metadata keys, options, PHP functions, and CSS classes to avoid breaking the existing site.

**Known limitation:** Newly created records still use legacy `caes_` storage identifiers. A generic data model has not yet been implemented.

## 1.0.2: Configurable archive page assignments and slugs

- Added the ability to assign an existing WordPress page to each event year.
- Added controls for changing that page's slug without changing its parent.
- Made archive rendering, archive links, and year-specific sponsors resolve the configured page ID first, with legacy year-based slug detection as fallback.
- Added validation against duplicate page assignments and conflicting sibling slugs.

**Upgrade note:** Changing a page slug changes its public URL. Check redirects.

## 1.0.1: Legacy archive URL support

- Recognized historical slugs such as `2019-canadian-email-summit` in archive details and year-specific sponsors.
- Preferred existing published year-page URLs in archive indexes and year navigation.
- Retained numeric and `summit-YYYY` slug support.
- Required no data migration or theme changes.

## 1.0.0: Production baseline

- Promoted the approved v0.1.27 codebase to v1.0.0.
- Updated WordPress plugin version metadata.
- Made no functional or data-model changes in this release.

## Release verification

The above changes are documented by the included release notes. This documentation update does not constitute a live WordPress regression test. Back up and test on staging before production deployment.
