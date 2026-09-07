# PlesConnect catalog API

The catalog supports music releases (single, EP, album) and music videos.
PlesConnect accepts and reviews submissions; admins manually deliver them to a
distribution partner and record progress. No distributor API is called.

## Access and response format

All endpoints require `Authorization: Bearer <platform JWT>`.
Customer endpoints require an active `users.is_plesconnect_user` account, regardless
of `is_organizer`. Admin endpoints require an active `SUPER_ADMIN` account.
Customer queries are owner-scoped, including files and historical submissions.
Another customer's release/file returns 404. Inactive accounts receive 403.

Normal responses use `{status, message, data}`. Lists also include
`meta: {total, page, limit, pages}`. Validation failures return 422 with an
`errors` object keyed by field path. Locked edits, stale submission versions,
and invalid status transitions return 409. Rate limiting returns 429.

## Customer endpoints

Paths below are relative to `/api/catalog`.

| Method | Path | Purpose |
|---|---|---|
| GET | `/options` | Types, statuses, genres, languages, destinations, upload constraints |
| GET | `/releases` | Own catalog; filter by `type`, `status`, `search`, `has_change_request` (0/1), `page`, `limit` (1–100) |
| POST | `/releases` | Create a draft; requires `type` and `title` |
| GET | `/releases/{id}` | Draft metadata, current assets, submission versions, visible history, store statuses |
| PATCH | `/releases/{id}` | Save title and/or metadata while editable |
| DELETE | `/releases/{id}` | Delete an unsubmitted draft and its files |
| POST | `/releases/{id}/assets` | Multipart upload: `kind`, `file`, and `track_id` for audio |
| GET | `/releases/{id}/assets/{assetId}` | Authenticated original file download |
| DELETE | `/releases/{id}/assets/{assetId}` | Remove a current draft asset; retain historical originals |
| POST | `/releases/{id}/submit` | Validate completeness and save an immutable submission version |
| POST | `/releases/{id}/change-requests` | `{ "message": "Please let me correct the title." }` |
| GET | `/releases/{id}/submissions/{version}` | Exact metadata and asset manifest for that version |

Create returns 201. Asset uploads return 201 and the updated release including
the new asset ID. Other successful mutations return 200.

## Music example

```json
{
  "type": "music",
  "title": "Langit Baru",
  "metadata": {
    "release_type": "single",
    "artists": [{"name": "Nama Artis", "role": "primary"}],
    "primary_genre": "pop",
    "language": "id",
    "label": "Ples Music",
    "copyright_year": 2026,
    "copyright_owner": "Nama Pemilik",
    "recording_year": 2026,
    "recording_owner": "Nama Pemilik",
    "release_date": "2026-10-15",
    "territories": ["WORLD"],
    "stores": ["spotify", "apple_music"],
    "rights_confirmed": true,
    "tracks": [{
      "id": "5698fdb4-783a-4d67-80f6-90b4076f5a32",
      "title": "Langit Baru",
      "artists": [{"name": "Nama Artis", "role": "primary"}],
      "contributors": [{"name": "Nama Penulis", "role": "songwriter"}],
      "language": "id",
      "explicit": "no",
      "ai_usage": "none",
      "instrumental": false
    }]
  }
}
```

An incomplete draft may start with only `type` and `title`. When included,
`metadata` **replaces the entire metadata object**; it is not a recursive patch.
Send the full current metadata when saving a wizard step. `type` is immutable.
Array order is the track order. Generate and keep a UUID for each track; upload
its audio using the same `track_id`. Removing a track retires its current audio.

Submission requires all fields shown above except `label`. Each artist list needs
a primary artist; each track needs a songwriter or composer. Artwork and audio
for every track must be uploaded. Rights confirmation must be accepted.
All selected assets must still exist in storage.

## Music video differences

Use `type: "music_video"`. Omit `release_type` and `tracks`. Supply
`contributors`, `explicit`, `ai_usage`, and `instrumental` directly in metadata.
At least one songwriter or composer and a video file are required.
Select destinations from `video_stores` in `/options`; music destinations are
not accepted for video releases. Artwork is optional for video submissions.

## Additional metadata

Optional release fields: `various_artists` (boolean), `secondary_genre`,
`contributors`, `upc` (12 or 13 digits), `version` (version subtitle),
`original_release_date`, `release_time` (`HH:mm`), `timezone` (required when time
is provided), `preorder`, `preorder_date` (required for preorder and before release),
`no_preorder_preview`, and `pricing_tier` (free text for the admin).
Original release date cannot be after the requested release date.

Territories use `['WORLD']` alone or uppercase two-letter country codes.
Genres and languages use active master-data codes (see below). Pricing remains
optional free text for the reviewer. Territory code validation currently checks
shape, not an ISO registry.

Optional track fields: `version`, `lyrics`, `isrc`, `preview_start_seconds`.
Video metadata also accepts `lyrics` and `isrc`.
ISRC format is 12 uppercase characters without hyphens, e.g. `IDABC2600001`.
Use strings for UPC/ISRC identifiers to preserve leading zeros.
Explicit values: `no`, `yes`, `clean`. AI declaration: `none`, `assisted`, `generated`.
These are PlesConnect declarations for admin review, not a promise of identical
partner terminology.

Artist roles: `primary`, `featured`. Contributor roles: `songwriter`, `composer`,
`lyricist`, `producer`, `performer`; video release contributors also support `director`.
People may include `spotify_url` and `apple_music_url` (HTTPS).
Up to 100 tracks and 50 people per credits list are supported.

## Uploads and storage

Upload **one original file per multipart request**:

```text
POST /api/catalog/releases/{id}/assets
kind=audio
track_id=5698fdb4-783a-4d67-80f6-90b4076f5a32
file=<binary WAV or FLAC>
```

Audio: WAV/FLAC, maximum 500 MiB by default. Video: MP4/MOV, maximum 2 GiB.
Artwork: JPG/PNG, square, at least 3000×3000, maximum 35 MiB.
The API checks detected MIME, extension, size, and artwork dimensions. Codec,
bitrate, frame rate, duration, and creative/rights suitability are **manual review
checks**, not automatically certified by accepting an upload.

Uploading another file of the same kind/track replaces the current draft asset
but retains the original for historical submissions. Asset paths and storage
credentials are never returned. Files use the private `catalog` disk by default;
do not expose `storage/app/catalog` through `storage:link` or a public bucket.
`CATALOG_DISK` can select another configured **private** disk.
Downloads require authentication and return attachment responses; fetch them as
blobs when displaying a player in a frontend that authenticates with bearer headers.

## Review and distribution

```text
draft -> waiting_for_review -> under_review -> approved
approved -> processing_distribution -> scheduled -> live
```

`waiting_for_review`/`under_review` can move to `changes_requested` or `rejected`.
Approved, processing, and scheduled releases can also be returned for corrections
or rejected. Live releases can be returned for corrections.
`changes_requested -> waiting_for_review` happens when the owner resubmits.
Rejected releases are terminal in this version of the API.

Only `draft` and `changes_requested` allow metadata or file changes. A user
change request adds a visible history entry; it does not unlock the release.
The admin must return it to `changes_requested`. Each resubmission increments
`version`, preserves earlier snapshots/files and internal audit events, and creates
a fresh set of per-store delivery records. The previous distribution record remains
in the admin audit; the current distribution record is cleared for the new version.

Change requests set `has_change_request=true` and appear in the admin queue using
`?has_change_request=1`. Duplicate requests return 409. While a request is open,
the admin must return the release for corrections or reject it before advancing
distribution; this clears the flag and preserves the request in history.

## Admin endpoints

Paths below are relative to `/api/admin/catalog/releases`.

| Method | Path | Body / purpose |
|---|---|---|
| GET | `/` | Queue with customer filters plus `assigned_admin_id` |
| GET | `/{id}` | Full detail, owner ID, assignment, internal distribution, audit, allowed transitions |
| PATCH | `/{id}/assignment` | `assigned_admin_id`: active super-admin ID or null |
| POST | `/{id}/status` | `status`, `version`, optional public `message`, `fields` |
| POST | `/{id}/notes` | `message`: internal-only note |
| PUT | `/{id}/distribution` | Save manual partner submission details |
| PUT | `/{id}/stores/{store}` | `version`, `status`, `url` (required for live) |
| GET | `/{id}/assets/{assetId}` | Download original asset |
| GET | `/{id}/submissions/{version}` | Saved submission |
| GET | `/{id}/submissions/{version}/export` | ZIP of metadata JSON/CSV and original files |

Status, distribution, and store updates require the current submission `version`;
a stale version returns 409. Invalid transitions also return 409.
Assignment is for team coordination; any active super admin can operate a release.
Customers cannot filter by internal assignment.

Correction example:

```json
{
  "version": 1,
  "status": "changes_requested",
  "message": "Please correct the artwork and title.",
  "fields": [{"field": "title", "message": "Match the title on the cover."}]
}
```

A public `message` is required for corrections and rejection. Keep partner names
and operational details in `/notes`, not public messages.

After manually submitting the approved package, record:

```json
{
  "version": 1,
  "provider": "onerpm",
  "reference": "partner-release-id",
  "url": "https://dashboard.onerpm.com/",
  "submitted_at": "2026-09-07T10:00:00+07:00",
  "notes": "Entered and uploaded by our team.",
  "upc": "012345678901",
  "isrcs": [{"track_id": "5698fdb4-783a-4d67-80f6-90b4076f5a32", "isrc": "IDABC2600001"}]
}
```

Then transition to `processing_distribution`. The partner reference is required
before this transition. This endpoint replaces the internal distribution object.
For videos use optional `video_isrc`; music `isrcs` must reference existing tracks.
Only generated identifiers (`upc`, `isrcs`, `video_isrc`) are exposed to customers
in `identifiers`; partner references, links, notes, assignments, and internal audit
never appear in customer responses.

Store statuses: `pending`, `processing`, `scheduled`, `live`, `failed`, `removed`.
A release can become `live` only after every selected store is confirmed live.
Before then the customer still sees individual live stores and their links.
Removing/failing a store after a release is live returns the aggregate release
status to `processing_distribution`. Dates do not automatically mark anything live.

Exports always use the selected immutable submission version, verify file SHA-256
hashes, and include metadata.csv, metadata.json, README.txt, and original assets.
They require PHP's zip extension and temporary disk space for staged assets plus
the archive. Temporary export files are deleted after the response is sent.

## Installation and operational boundaries

Run `php artisan migrate`, `php artisan db:seed --class=CatalogMasterDataSeeder`,
and `php artisan config:clear` using PHP compatible with
the installed Laravel dependencies (this implementation was tested on PHP 8.5).
The catalog migrations add seven `catalog_*` tables and do not change existing
users/events. The master-data seeder adds starter entries without overwriting
admin edits or reactivating disabled entries.

For native PHP hosting, configure `upload_max_filesize`, `post_max_size`, web-server
body limits, and request timeouts to accommodate the configured catalog limits.
The repository includes `docker/catalog.ini` and an Nginx catalog upload limit;
rebuild the container to apply those settings. Ensure the Docker PHP version also
meets the installed Composer platform requirements before deploying.

The initial API uses direct uploads and synchronous exports. It does not yet offer
resumable uploads, automatic transcoding/technical media inspection, external status
sync, royalty accounting, payouts, or takedown execution. Plan temporary storage and
proxy timeouts for large video exports. Media is retained for submission history;
retention and storage quotas should be defined operationally.

Review feedback is available in the release's visible `history`; the frontend should
refresh it to display corrections and progress. No outbound email notifications or
automated partner submissions are sent by this module.


## Genre and language master data

Catalog masters are separate from event categories. Tables: `catalog_genres` and
`catalog_languages`. Each entry has an immutable `code`, editable `name`,
`is_active`, and `sort_order`. Names are unique within each master type.

Customer lookups (active PlesConnect account):

- `GET /api/catalog/genres`
- `GET /api/catalog/languages`
- `GET /api/catalog/options` also includes both lists.

Lists return active entries ordered by `sort_order`, then `name`. Example:

```json
{"status":"success","message":"Catalog master data retrieved.","data":[{"code":"pop","name":"Pop","is_active":true,"sort_order":0}]}
```

Admin management (active SUPER_ADMIN):

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/admin/catalog/genres` | All genres, including inactive entries |
| POST | `/api/admin/catalog/genres` | Create genre |
| PATCH | `/api/admin/catalog/genres/{code}` | Rename, reorder, activate/deactivate |
| GET | `/api/admin/catalog/languages` | All languages, including inactive entries |
| POST | `/api/admin/catalog/languages` | Create language |
| PATCH | `/api/admin/catalog/languages/{code}` | Rename, reorder, activate/deactivate |

Create body: `{"code":"shoegaze","name":"Shoegaze","is_active":true,"sort_order":10}`.
Deactivate body: `{"is_active":false}`. Codes use lowercase letters/digits with
optional hyphen/underscore separators and cannot change after creation. There is
no hard-delete endpoint, so saved catalog references remain meaningful.

Submit master codes in `metadata.primary_genre`, `metadata.secondary_genre`,
`metadata.language`, and `metadata.tracks[].language`. For example, `pop` and `id`.
Known active display names from earlier clients (e.g. `Pop`, `Indonesian`) are
accepted case-insensitively and normalized to codes when saving/submitting. Unknown
or inactive choices return 422 with the corresponding metadata field path.
Existing stored drafts and submission snapshots are not rewritten by migration.
A draft referencing a newly inactive entry must choose an active value before
resubmission; title-only edits remain possible.

New submission snapshots and ZIP exports include a `master_data` array of
`{field, code, name}` records. Those labels are frozen at submission time, so later
admin renames do not alter historical distribution packages. Existing snapshots
created before master data was added retain their original free-text metadata.

The starter list contains 22 genres and 16 languages. This is PlesConnect's initial
vocabulary, not a complete copy of ONErpm's dropdowns; admins can extend it after
confirming the required choices with the distribution team.
