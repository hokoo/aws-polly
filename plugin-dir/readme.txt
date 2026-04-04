=== AI Text-to-Speech from AWS Polly ===
Contributors: awslabs, wpengine, stevenkword, itron
Tags: text-to-speech, audio, aws polly, speech synthesis, podcast
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.6
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generate WordPress post audio with AI text-to-speech powered by AWS Polly.

== Description ==

AI Text-to-Speech from AWS Polly creates audio versions of WordPress posts with AWS Polly voices.

Key features:

* Generate audio for supported post types.
* Queue audio generation in the background with WP-Cron.
* Store audio locally or in Amazon S3.
* Optionally use Amazon CloudFront for delivery.
* Control voice, sample rate, autoplay, player label, and download availability.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-text-to-speech/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open the plugin settings and enter valid AWS credentials and region details.
4. Configure the Polly voice and player settings that fit your site.

== Frequently Asked Questions ==

= Do I need an AWS account? =

Yes. You need AWS credentials with access to the AWS Polly APIs that the plugin uses.

= Can I store audio outside the local server? =

Yes. The plugin supports storing generated audio in Amazon S3 and serving it through Amazon CloudFront.

= Does it work per post? =

Yes. You can enable audio generation for individual posts and the plugin will keep track of queued, running, and ready states.

== Changelog ==

= 0.6 =

* Prepared the plugin for WordPress.org review.
* Added stricter sanitization for settings.
* Tightened escaping and request handling in admin flows.
* Removed disallowed offloaded assets from bundled functionality.
