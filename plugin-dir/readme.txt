=== AI Text-to-Speech using AWS Polly ===
Contributors: igortron, hokku
Tags: text-to-speech, audio, aws polly, speech synthesis, podcast
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.8
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generate WordPress post audio with AI text-to-speech using AWS Polly.

== Description ==

AI Text-to-Speech using AWS Polly creates audio versions of WordPress posts with AWS Polly voices.

This is an independent plugin by iTRON. It is not affiliated with or endorsed by Amazon, AWS, or Amazon Polly.

Development and source code: https://github.com/hokoo/aws-polly

Key features:

* Generate audio for supported post types.
* Queue audio generation in the background with WP-Cron.
* Store audio locally or in Amazon S3.
* Optionally use Amazon CloudFront for delivery.
* Control voice, sample rate, autoplay, player label, and download availability.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-text-to-speech-using-aws-polly/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open the plugin settings and enter valid AWS credentials and region details.
4. Configure the Polly voice and player settings that fit your site.

== Configuration ==

To keep AWS credentials out of the WordPress database, define them in `wp-config.php` or another PHP config file loaded before WordPress finishes bootstrapping:

    define( 'ITRON_POLLY_TTS_S3_ACCESS_KEY', 'your-access-key' );
    define( 'ITRON_POLLY_TTS_S3_SECRET_KEY', 'your-secret-key' );

You can also lock the bucket and region in PHP the same way:

    define( 'ITRON_POLLY_TTS_S3_BUCKET_NAME', 'your-s3-bucket' );
    define( 'ITRON_POLLY_TTS_S3_REGION', 'us-east-1' );

When these constants are present, the plugin uses them instead of saved options and shows the related admin fields as defined by PHP constant. Do not commit real secrets into version control.

= Audio access and removal =

Generated audio uses public delivery URLs, including audio stored locally. A post password does not protect its audio file. Before generating audio for a password-protected post, the editor asks for explicit confirmation that the audio will be public. Bulk generation asks once for the selected password-protected posts; declining skips their audio without cancelling ordinary post saves or generation for other selected posts.

For direct S3 delivery, configure a bucket policy that allows public reads of the audio objects. Alternatively, use a publicly accessible CloudFront distribution with access to a private S3 origin. The plugin does not set object ACLs or change your bucket policies.

Turning text-to-speech off stops generation. Existing audio is not deleted simply by opening or saving an unchanged post. A later save that changes the speech text or synthesis settings removes stale audio without generating a replacement while the plugin is off.

Removal uses the bucket, region, and key or local path recorded when the audio was saved, even after storage settings change. Current AWS credentials must still allow deletion at the original location. Failed cleanup produces an administrator notice with the affected location and possible causes. S3 object versions, CDN caches, and previously downloaded copies are not purged by the plugin.

== External services ==

This plugin connects to services provided by Amazon Web Services, Inc. (AWS) to generate, store, and optionally deliver audio files after an administrator configures the corresponding functionality. AWS charges may apply. Previously generated S3 objects may also require deletion requests after generation is disabled or storage settings change.

= Amazon Polly =

Amazon Polly is used to convert post content into audio files.

Data sent when audio is generated: the enabled portions of the post title, excerpt, and content prepared as text or SSML for speech synthesis, plus the selected voice, engine, sample rate, output format, configured lexicon names, and AWS region. The configured AWS credentials are used on the server to authenticate and sign requests; the secret access key is not sent as content.

Generation can be queued by saving an audio-enabled post or by the Generate Audio bulk action, and runs through WP-Cron. While generation is enabled and credentials are configured, settings, editor, and generation flows may request the available voice catalog from Polly. Catalog requests include the region and pagination parameters, not post content; results are cached.

Service information: https://aws.amazon.com/polly/
Terms of service: https://aws.amazon.com/service-terms/ (AWS Service Terms, including the AWS Machine Learning and Artificial Intelligence Services section that covers Amazon Polly)
Privacy policy: https://aws.amazon.com/privacy/
Additional AWS data privacy information: https://aws.amazon.com/compliance/data-privacy-faq/

= Amazon S3 =

Amazon Simple Storage Service (Amazon S3) stores generated audio when S3 storage is enabled. Existing S3 audio can still need cleanup after switching to another storage location or disabling generation.

Data sent on upload: the resulting MP3 audio file, its object key, content type, selected bucket, and AWS region. Access validation may check the configured bucket. Replacing or deleting audio sends its recorded bucket, region, and object key in a deletion request. The configured AWS credentials are used on the server to authenticate and sign requests; the secret access key is not sent as content. Requests are sent to Amazon S3 endpoints for the relevant region, such as `s3.us-east-1.amazonaws.com`.

When S3 storage is enabled and CloudFront is not configured, visitors also download the generated audio files directly from your Amazon S3 bucket when they load a page with audio. Those requests include the audio file URL and standard browser request data such as the visitor IP address and user agent.

Before displaying a remote audio player, the WordPress server may send a cached availability check (HTTP HEAD) to the recorded audio URL. This discloses the audio URL and server request information, not visitor IP addresses or post text.

Service information: https://aws.amazon.com/s3/
Terms of service: https://aws.amazon.com/service-terms/ (AWS Service Terms for AWS services)
Privacy policy: https://aws.amazon.com/privacy/
Additional AWS data privacy information: https://aws.amazon.com/compliance/data-privacy-faq/

= Amazon CloudFront =

Amazon CloudFront is used only when you configure a CloudFront domain for audio delivery.

Data sent when visitors load a page with audio: requests for the generated audio files are served through your configured CloudFront distribution. Those requests include the audio file URL and standard browser request data such as the visitor IP address and user agent.

WordPress may also check that the audio URL is available using a cached HTTP HEAD request from the server, as described above for S3.

Service information: https://aws.amazon.com/cloudfront/
Terms of service: https://aws.amazon.com/service-terms/ (AWS Service Terms, including the Amazon CloudFront section)
Privacy policy: https://aws.amazon.com/privacy/
Additional AWS data privacy information: https://aws.amazon.com/compliance/data-privacy-faq/

== Frequently Asked Questions ==

= Do I need an AWS account? =

Yes. You need AWS credentials with access to the AWS Polly APIs that the plugin uses.

= Can I store audio outside the local server? =

Yes. The plugin supports storing generated audio in Amazon S3 and serving it through Amazon CloudFront.

= Does it work per post? =

Yes. You can enable audio generation for individual posts and the plugin will keep track of queued, running, and ready states.

== Changelog ==

= 1.0.8 =

* Removed the obsolete disabled bulk-update interface and unused legacy capability gates and assets.
* Added public-audio confirmation for password-protected posts and strengthened metadata, bulk-action, and player security.
* Fixed speech-change detection, normal-speed generation, and stale-audio cleanup while generation is disabled.
* Preserved original audio storage locations for safe cleanup, added failure notices, and removed forced S3 object ACLs.
* Fixed logging opt-out and settings availability; updated bundled runtime dependencies and AWS service disclosures.

= 1.0.7 =

* Removed unreachable legacy translation code left over from an earlier plugin version.
* Updated the bundled AWS SDK for PHP to the latest stable release.
* Clarified the existing Amazon Polly, Amazon S3, and Amazon CloudFront service disclosures.

= 1.0.6 =

* Confirmed compatibility with WordPress 7.1.
* Included `composer.json` in the release package for dependency transparency.
* Added nonce and capability validation for admin audio filter and bulk action notice requests.
* Removed direct request input reads from Polly voice option sanitization.

= 1.0.5 =

* Synchronized the plugin bootstrap version with the namespace refactor.
* Moved the plugin name and version to global bootstrap constants.
* Added `@uses` documentation for registered action callbacks in the main plugin loader.

= 1.0.4 =

* Updated the bundled AWS SDK for PHP to the latest stable 3.379.x release.
* Added dedicated AWS secret key sanitization that preserves valid secret key characters.
* Removed an unused admin-post background task endpoint in favor of the WordPress cron queue.
* Added distribution exclusions for non-runtime vendor development files.

= 1.0.3 =

* Updated the bundled psr/log dependency to the latest stable 3.x release.
* Removed an unused settings helper that was not part of the plugin runtime.

= 1.0.2 =

* Improved settings registration to use explicit sanitize_callback handling for registered options.
* Added PHP code style checks for local development and GitHub Actions pull request validation.
* Applied code style cleanup to the plugin codebase.

= 1.0.1 =

* Renamed the plugin for WordPress.org review compliance.
* Removed trademark-branded bundled assets and legacy AWS project metadata.
* Documented external AWS services in the readme.
* Switched admin inline markup to WordPress enqueue APIs.
* Replaced bundled getID3 usage with the WordPress core media metadata API.
* Resolved Plugin Check warnings for the review package.

= 1.0.0 =

* Prepared the plugin for WordPress.org review.
* Added stricter sanitization for settings.
* Tightened escaping and request handling in admin flows.
* Removed disallowed offloaded assets from bundled functionality.
