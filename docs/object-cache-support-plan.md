# Object Cache Support and Cache Invalidation Plan

## Goal

Add persistent object cache support only where it is actually useful, and introduce an explicit invalidation strategy for derived state and external checks.

Core principle: do not add a second cache layer for `options` and `post meta`, because WordPress already stores those in object cache. Only cache:

- external audio file availability checks;
- derived state built from multiple sources;
- the Amazon Polly voices catalog, with explicit invalidation when the AWS region changes.

## What Should Not Be Cached Separately

Do not add custom `wp_cache_*` usage for:

- `get_option()`;
- `get_post_meta()`;
- `get_post_field()`;
- most ordinary `WP_Query` calls unless the result is reused as derived state.

Reason: WordPress already stores options and meta in object cache and invalidates them on `update_option()`, `delete_option()`, `update_post_meta()`, and `delete_post_meta()`.

## Main Problems in the Current Implementation

### 1. Incomplete Audio State Cleanup

Audio deletion currently does not clear all related meta state. That creates stale state:

- the file is already deleted;
- the attachment is already deleted;
- but `amazon_polly_audio_link_location` and other meta keys still remain.

This affects:

- `plugin-dir/admin/AmazonAI-Common.php`
- `plugin-dir/admin/AmazonAI-LocalFileHandler.php`
- `plugin-dir/admin/AmazonAI-S3FileHandler.php`
- `plugin-dir/admin/AmazonAI-PollyService.php`

### 2. Frontend Audio File Checks Are Not Cached

`wp_remote_head()` in `plugin-dir/public/class-amazonpolly-public.php` runs on every post render.

That is a direct candidate for object cache with a short TTL and explicit invalidation.

### 3. There Is Write-on-Read Behavior

Before adding custom object cache, all database writes must be removed from read and render paths.

Required cleanup points:

- `plugin-dir/admin/AmazonAI-Common.php::get_sample_rate()`
- `plugin-dir/admin/AmazonAI-Common.php::get_voice_id()`
- `plugin-dir/admin/AmazonAI-Common.php::get_posttypes()`
- `plugin-dir/admin/AmazonAI-Common.php::get_audio_speed()`
- `plugin-dir/admin/AmazonAI-Common.php::sync_polly_voice_option()` when called from UI paths
- `plugin-dir/admin/AmazonAI-PostMetaBox.php::display_polly_gui()`

As long as these paths write to the database during reads, invalidation will remain fragile and hard to verify.

## Scope of the First Iteration

Include only the work that provides immediate value without adding unnecessary complexity:

1. Centralized post audio state cleanup.
2. Object cache for frontend audio availability checks.
3. Explicit invalidation for the Polly voices transient/object cache.
4. Invalidation hooks for meta and options that actually affect audio state and voices.

Do not include in the first iteration:

- caching `queued/running` state from lock or cron;
- caching rendered player HTML;
- caching bulk-screen `WP_Query` results;
- adding a custom cache layer on top of ordinary `options` and `post meta`.

## Target Design

### New Class

Add a new class:

- `plugin-dir/includes/class-amazonpolly-object-cache.php`

Responsibilities:

- build cache keys;
- read and write custom object cache entries;
- clear per-post cache entries;
- clear voices cache entries;
- be the only place that uses `wp_cache_get()`, `wp_cache_set()`, and `wp_cache_delete()`.

### Recommended Cache Group and Keys

Group:

- `amazon_polly`

Keys:

- `audio_head:{post_id}`
  - value: array `['url' => string, 'exists' => bool, 'checked_at' => int]`
  - TTL: `300` seconds
- `voices:{md5(region)}`
  - either use this as an object-cache mirror around the existing transient or keep only the transient with explicit invalidation

Important: for the HEAD check, use a key based only on `post_id`, and store the URL inside the payload. That makes invalidation straightforward when the URL changes.

## Phase 1. Remove Write-on-Read Behavior

### Changes

1. In `plugin-dir/admin/AmazonAI-Common.php`:
   - stop calling `update_option()` from `get_sample_rate()`;
   - stop calling `update_option()` from `get_voice_id()`;
   - stop calling `update_option()` from `get_posttypes()`;
   - stop calling `update_option()` from `get_audio_speed()`.

2. In `plugin-dir/admin/AmazonAI-PostMetaBox.php`:
   - do not call `update_post_meta()` or `delete_post_meta()` inside `display_polly_gui()`;
   - the UI should only compute and display the effective value.

3. In `plugin-dir/admin/AmazonAI-PollyConfiguration.php`:
   - do not call synchronization methods that write to the database from render methods;
   - if a value needs auto-correction, do it in sanitize/save logic, not in GUI rendering.

### Completion Criteria

- admin screens and metabox rendering no longer trigger `update_option()` or `update_post_meta()`;
- reading settings becomes side-effect free.

## Phase 2. Introduce Centralized Audio State Cleanup

### New Methods

Add to `plugin-dir/admin/AmazonAI-Common.php`:

- `clear_post_audio_state_meta( int $post_id ): void`
- `clear_post_audio_runtime_cache( int $post_id ): void`
- `clear_post_audio_state( int $post_id ): void`

### What `clear_post_audio_state_meta()` Must Remove

At minimum:

- `amazon_polly_audio_link_location`
- `amazon_polly_audio_location`
- `amazon_polly_generated_voice_id`
- `amazon_polly_audio_playtime`
- `amazon_polly_audio_hash`
- `amazon_polly_media_library_attachment_id`
- `amazon_polly_settings_hash`
- `amazon_polly_transcript_source_lan`

For legacy translation state:

- all `amazon_polly_translation_{lang}`
- all `amazon_polly_transcript_{lang}`

After batch deletion, call:

- `clean_post_cache( $post_id )`

### Where `clear_post_audio_state()` Must Be Called

1. In `plugin-dir/admin/AmazonAI-Common.php::delete_post_audio()`
   - after the file and attachment have been deleted.

2. In `plugin-dir/admin/AmazonAI-PollyService.php`
   - in the branch where Polly is disabled for the post;
   - in error branches where the file is already gone or the audio state is no longer valid;
   - before regeneration when storage or URL-critical parameters require a full state reset.

3. In `plugin-dir/admin/AmazonAI-LocalFileHandler.php::delete()`
   - do not duplicate meta cleanup inside the file handler;
   - the file handler should only remove the physical file and attachment.

4. In `plugin-dir/admin/AmazonAI-S3FileHandler.php::delete()`
   - same rule: only remove physical objects.

### Completion Criteria

After audio deletion:

- the post no longer contains stale audio meta;
- the admin column no longer shows "Audio available";
- the frontend does not try to use an old audio URL.

## Phase 3. Add Object Cache for Frontend HEAD Checks

### Changes

1. In the new `plugin-dir/includes/class-amazonpolly-object-cache.php`, implement:
   - `get_audio_head_status( int $post_id ): ?array`
   - `set_audio_head_status( int $post_id, string $url, bool $exists ): void`
   - `delete_audio_head_status( int $post_id ): void`

2. In `plugin-dir/public/class-amazonpolly-public.php`:
   - read the cache entry before calling `wp_remote_head()`;
   - if a cached entry exists and its `url` matches the current `amazon_polly_audio_link_location`, use it;
   - if there is no entry or the URL changed, perform `wp_remote_head()` and store the result in object cache.

3. HEAD cache TTL:
   - `300` seconds.

### HEAD Cache Invalidation

Clear it on:

- any change to `amazon_polly_audio_link_location`;
- any change to `amazon_polly_enable`;
- new audio generation;
- audio deletion;
- post deletion;
- changes to `amazon_polly_s3`;
- changes to `amazon_polly_cloudfront`;
- changes to `aws_polly_s3_region`;
- changes to `aws_polly_s3_bucket_name`.

### Completion Criteria

- repeated post views do not trigger `wp_remote_head()` every time;
- after URL or storage changes, the frontend does not use stale HEAD results.

## Phase 4. Add Explicit Voices Cache Invalidation

### Current State

The voices list is already cached via a transient in:

- `plugin-dir/admin/AmazonAI-Common.php::get_polly_voices()`

If persistent object cache is enabled, that transient will already live in object cache. There is no need to add a separate second cache layer here. Only explicit invalidation is needed.

### Changes

Add methods:

- `delete_polly_voices_cache( ?string $region = null ): void`
- `delete_all_known_polly_voices_cache(): void` if region changes must be handled without knowing the previous region

### When to Clear Voices Cache

On changes to:

- `aws_polly_s3_region`
- `aws_polly_s3_access_key`
- `aws_polly_s3_secret_key`

Optional:

- after failed AWS access validation, if stale voices data must be forcibly removed.

### Completion Criteria

- when the AWS region changes, the voices list updates immediately without waiting for the transient TTL;
- the UI does not show voices from the previous region.

## Phase 5. Connect Invalidation Hooks

### Hook Registration

Register hooks through `plugin-dir/includes/class-amazonpolly.php`:

- `updated_post_meta`
- `added_post_meta`
- `deleted_post_meta`
- `before_delete_post`
- `update_option_*` for selected options

### Meta Hooks

Treat these as invalidation triggers:

- any meta key with prefix `amazon_polly_`
- `amazon_ai_source_language`

Actions:

- delete the post HEAD cache entry;
- delete any future per-post derived cache entry if one exists;
- call `clean_post_cache( $post_id )` only where appropriate after grouped updates, not blindly on every hook invocation.

### Option Hooks

Use separate callbacks for two categories.

Options that affect voices cache:

- `aws_polly_s3_region`
- `aws_polly_s3_access_key`
- `aws_polly_s3_secret_key`

Options that affect audio generation state or URL:

- `amazon_ai_source_language`
- `amazon_polly_voice_id`
- `amazon_polly_sample_rate`
- `amazon_polly_s3`
- `amazon_polly_cloudfront`
- `amazon_polly_ssml`
- `amazon_polly_auto_breaths`
- `amazon_polly_speed`
- `amazon_polly_lexicons`
- `amazon_polly_neural`
- `amazon_polly_speaking_style`
- `amazon_polly_add_post_title`
- `amazon_polly_add_post_excerpt`
- `amazon_ai_skip_tags`
- `amazon_polly_disable_post_voice_override`
- `amazon_polly_posttypes`

### What to Do on Option Changes

1. For voices-related settings:
   - clear the voices transient/object cache.

2. For audio-generation settings:
   - do not immediately loop through and clear state for all posts;
   - use lazy invalidation instead:
     - new generation runs rebuild state;
     - HEAD cache is cleared per post when the relevant post meta actually changes;
   - if a full reset is required, expose it as a dedicated admin action or WP-CLI command, not as an automatic hook side effect.

## Phase 6. Do Not Cache Dynamic Queue and Lock State

In `plugin-dir/admin/AmazonAI-AudioAdmin.php`:

- do not add long-lived cache entries for `WP_Lock` results or `has_queued_audio()`;
- `queued/running` status should remain live-computed.

Reason:

- queue and lock invalidation is harder to keep correct;
- the risk of stale admin state is higher than the performance benefit.

## Exact File Change List

Create:

- `plugin-dir/includes/class-amazonpolly-object-cache.php`

Modify:

- `plugin-dir/includes/class-amazonpolly.php`
- `plugin-dir/admin/AmazonAI-Common.php`
- `plugin-dir/admin/AmazonAI-PollyService.php`
- `plugin-dir/public/class-amazonpolly-public.php`
- `plugin-dir/admin/AmazonAI-LocalFileHandler.php`
- `plugin-dir/admin/AmazonAI-S3FileHandler.php`
- `plugin-dir/admin/AmazonAI-PostMetaBox.php`
- `plugin-dir/admin/AmazonAI-PollyConfiguration.php`

Optional in the second iteration:

- `plugin-dir/admin/AmazonAI-AudioAdmin.php`

## Recommended Commit Order

### Commit 1

- remove write-on-read behavior;
- make read and render paths side-effect free.

### Commit 2

- add `class-amazonpolly-object-cache.php`;
- load the class in bootstrap;
- implement the HEAD cache API.

### Commit 3

- implement `clear_post_audio_state()`;
- wire cleanup into `delete_post_audio()`, `generate_audio()`, and deletion paths.

### Commit 4

- add meta and option invalidation hooks;
- add voices transient/object cache invalidation.

### Commit 5

- run manual QA;
- if needed, add a WP-CLI path for manually resetting stale audio state across posts.

## Manual QA Checklist

### Local Storage

1. Enable Polly for a post.
2. Generate audio.
3. Open the post twice.
4. Verify that after the first request, the HEAD result is served from object cache.
5. Delete the audio.
6. Verify that:
   - the player is gone;
   - the metabox shows no audio;
   - the attachment is deleted;
   - no stale audio link remains in meta.

### S3 and CloudFront

1. Generate audio into S3.
2. Open the post multiple times.
3. Change `amazon_polly_cloudfront` or the storage mode.
4. Verify that the frontend does not use an old HEAD result or stale URL.

### Voices

1. Open the TTS settings screen.
2. Note the voices list.
3. Change `AWS Region`.
4. Save settings.
5. Verify that the voices list updates immediately without waiting for TTL expiration.

### Post Cleanup

1. Generate audio.
2. Disable Polly for the post.
3. Update the post.
4. Verify that these values are removed:
   - link;
   - audio location;
   - generated voice;
   - playtime;
   - media library attachment id;
   - audio hash.

## Acceptance Criteria

- The frontend does not perform `wp_remote_head()` on every repeated view of the same post.
- After audio deletion or regeneration, users do not see stale audio URLs.
- When the AWS region changes, voices cache is invalidated immediately.
- Audio deletion clears both the file and the related state.
- Read and render paths no longer write to the database.
- The first iteration does not add unnecessary custom cache over `options` and `post meta`.

## Risks

- If write-on-read remains, invalidation will continue to happen in unpredictable places.
- If `queued/running` state is cached, the admin UI can become stale.
- If cache keys are based on URL instead of `post_id`, stale entry removal becomes harder when the URL format changes.

## Recommended Starting Point

Start with Phase 1 and Phase 2. Without that, object cache support will only hide the existing stale-state problems instead of fixing them.
