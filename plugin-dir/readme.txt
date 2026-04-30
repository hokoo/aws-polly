=== AI Text-to-Speech using AWS Polly ===
Contributors: igortron, hokku
Tags: text-to-speech, audio, aws polly, speech synthesis, podcast
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.4
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

    define( 'AWS_POLLY_S3_ACCESS_KEY', 'your-access-key' );
    define( 'AWS_POLLY_S3_SECRET_KEY', 'your-secret-key' );

You can also lock the bucket and region in PHP the same way:

    define( 'AWS_POLLY_S3_BUCKET_NAME', 'your-s3-bucket' );
    define( 'AWS_POLLY_S3_REGION', 'us-east-1' );

When these constants are present, the plugin uses them instead of saved options and shows the related admin fields as defined by PHP constant. Do not commit real secrets into version control.

== External services ==

This plugin connects to AWS services to generate, store, and optionally deliver audio files.

= AWS Polly =

AWS Polly is used to convert post content into audio files.

Data sent when audio is generated: the post title, post excerpt, post content prepared for speech synthesis, selected voice and playback settings, selected AWS region, and the AWS credentials you configure for the plugin.

Terms of service: https://aws.amazon.com/service-terms/ (AWS Service Terms, including the AWS Machine Learning and Artificial Intelligence Services section that covers Amazon Polly)
Privacy policy: https://aws.amazon.com/privacy/
Additional AWS data privacy information: https://aws.amazon.com/compliance/data-privacy-faq/

= Amazon S3 =

Amazon S3 is used only when you enable S3 storage for generated audio files.

Data sent when audio is generated: the resulting audio files, file names/object keys, selected bucket and region, and the AWS credentials you configure for the plugin.

When S3 storage is enabled and CloudFront is not configured, visitors also download the generated audio files directly from your Amazon S3 bucket when they load a page with audio. Those requests include the audio file URL and standard browser request data such as the visitor IP address and user agent.

Terms of service: https://aws.amazon.com/service-terms/ (AWS Service Terms for AWS services)
Privacy policy: https://aws.amazon.com/privacy/
Additional AWS data privacy information: https://aws.amazon.com/compliance/data-privacy-faq/

= Amazon CloudFront =

Amazon CloudFront is used only when you configure a CloudFront domain for audio delivery.

Data sent when visitors load a page with audio: requests for the generated audio files are served through your configured CloudFront distribution. Those requests include the audio file URL and standard browser request data such as the visitor IP address and user agent.

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
