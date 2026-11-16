# Event Manager CSV import/export candidate

The Import / Export submenu is available to WordPress Administrators. Choose an optional event year, a dataset, and a mode. Download a sample CSV (header plus one example), prepare a UTF-8 CSV, upload it, review the preview, and confirm.

Recommended order: Venues, Event Archives, Speakers, Tracks, Agenda, Recordings, Sponsorship Tiers, Sponsors, Sponsor Assignments, Agenda Colours. Each step is optional.

Import modes: Create or update, New only, and Duplicate where supported. Event years cannot be duplicated. New posts are drafts, event archives are hidden, sponsor assignments are hidden, and new sponsorship tiers are inactive. Existing published posts receive staged updates in the Import / Export administration screen, requiring explicit approval. Importing does not delete records.

Stable keys are used instead of WordPress numeric IDs. Agenda and recording speaker references accept existing speaker full names or exact portable keys. Separate multiple people with the pipe character (|), with one row per session or recording. Moderator names or keys go in moderator_key. Names must match one and only one existing speaker, otherwise validation blocks the row. The preview shows resolved names. Sample CSVs include multi-speaker examples; sample people are illustrative, not automatically created. Existing records without keys receive a portable key on export. Import shared profiles before annual sessions and recordings.

Limits: 2 MB and 500 rows per CSV. Uploading is restricted to Administrators, and requires WordPress nonce validation. Each preview expires after 20 minutes. Only valid rows are applied; invalid rows are blocked. The CSV import does not automatically download external media: sponsor logos and speaker photos retain existing attachments or supported URL references. Review and assign Media Library images separately after migration.

Important: This is a development candidate. Test with a staging database backup, especially for imported shared records and the staged approval workflow. A complete multi-file ZIP export, automatic remote image sideloading, and rollback of an entire batch are not included in this candidate.

## Agenda sample key convention

Session keys are unique across the entire site, not per event year. Use `session_2019_001`, `session_2019_002`, `session_2020_001`, etc. Put all speakers on one session row, separated with a literal pipe (`|`). Full names or existing speaker keys are accepted. Use the separate `moderator_key` field for the moderator.
