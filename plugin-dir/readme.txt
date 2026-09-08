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

Follow the steps below in order. They use the English names shown in the AWS and WordPress interfaces; translated interfaces place the same controls in the same sections.

You must sign in to AWS with an account that is allowed to create IAM policies, IAM users, access keys, and, when S3 storage is used, S3 buckets. In a company AWS account, these operations may be restricted; if AWS shows an authorization error, ask the AWS account administrator to complete the corresponding step.

The AWS terms used in this guide mean:

* **S3 bucket**: the container that holds generated MP3 files when S3 storage is enabled.
* **IAM policy**: the list of AWS operations the plugin is allowed to perform.
* **IAM user**: the dedicated technical identity to which that policy is attached.
* **Access key**: the Access key ID and Secret access key that let WordPress use the IAM user's permissions.

The plugin needs two credentials from AWS:

* **Access key ID**: usually starts with `AKIA`.
* **Secret access key**: a longer private value that AWS shows only when the key is created.

These values are not the email address and password used to sign in to AWS. Never use an access key belonging to the AWS account root user. The instructions below create a separate user that can only call the AWS operations required by this plugin.

= 1. Choose where the MP3 files will be stored =

Choose one option before creating the AWS policy:

* **Local WordPress storage** is the simplest first-time setup. The generated MP3 files are stored in the WordPress uploads directory. You do not need an S3 bucket or any S3 permissions.
* **Amazon S3 storage** keeps the generated MP3 files in an AWS bucket. Complete the bucket steps below and use the policy marked **Amazon S3 storage**.

Amazon Polly is required in both cases and AWS usage may incur charges.

= 2. Sign in to AWS and choose a Region =

1. If you do not have an AWS account, create one at https://aws.amazon.com/. AWS may ask for billing and identity information during registration.
2. Sign in to the AWS Management Console at https://console.aws.amazon.com/.
3. In the search box at the top, enter `Polly` and open **Amazon Polly**.
4. Use the Region selector in the upper-right corner of the console to select the Region where Polly will run. Remember both its name and code. For example, **US East (N. Virginia)** has the code `us-east-1`.
5. Confirm that the Polly page opens in this Region. The available voices and engines vary by Region, so choose a Region that provides the voice you intend to use.

Use this same Region for Polly, the optional S3 bucket, and the **AWS Region** setting in WordPress. A bucket's Region cannot be changed after the bucket is created.

= 3. Create an S3 bucket (skip for local storage) =

If you selected local WordPress storage in step 1, skip directly to step 4.

1. Open the S3 console at https://console.aws.amazon.com/s3/ or search for `S3` at the top of the AWS Console.
2. In the Region selector, select the same Region chosen for Amazon Polly.
3. In the left menu, choose **General purpose buckets**, then choose **Create bucket**.
4. If AWS asks for a bucket type or namespace, select a **General purpose** bucket in the standard shared global namespace.
5. In **Bucket name**, enter a unique name such as `example-com-polly-audio-1234`. Use only lowercase letters, numbers, and hyphens. The name must be unique across AWS, must not contain private information, and cannot be changed later. If AWS reports that the name already exists, add another random number and try again.
6. Check **AWS Region** and make sure it is the Region selected for Polly.
7. Keep **Object Ownership** set to **Bucket owner enforced** and keep ACLs disabled.
8. Leave **Block all public access** enabled for now. Step 8 explains how to make the audio playable with direct S3 delivery. Keeping this option enabled while creating the bucket avoids exposing an unfinished bucket.
9. Leave **Bucket Versioning** disabled and **Object Lock** disabled unless your organization has a specific retention requirement. Object Lock can prevent the plugin from replacing or deleting old audio.
10. Under **Default encryption**, keep the default **Server-side encryption with Amazon S3 managed keys (SSE-S3)**. This option works without additional KMS permissions.
11. Choose **Create bucket**.
12. When the bucket appears in the bucket list, copy its exact name. WordPress needs the name only, such as `example-com-polly-audio-1234`; do not enter `s3://`, an ARN, or a web address in the bucket-name field.

For separate development, staging, and production environments, or for multiple WordPress sites, create a different bucket for each installation and enter only that installation's bucket name in its plugin settings. Use names that identify the site and environment, such as `example-com-polly-production` and `example-com-polly-staging`. S3 bucket names must be globally unique.

Official S3 bucket instructions: https://docs.aws.amazon.com/AmazonS3/latest/userguide/create-bucket-overview.html

= 4. Create the plugin permissions policy =

An IAM policy is a document that tells AWS exactly what the plugin is allowed to do. Create one as follows:

1. Open the IAM console at https://console.aws.amazon.com/iam/ or search for `IAM` at the top of the AWS Console.
2. In the left menu, choose **Policies**.
3. Choose **Create policy**.
4. In **Policy editor**, choose **JSON**.
5. Delete the sample JSON already in the editor.
6. Copy and paste one of the complete policies below. Use the local policy if WordPress will store the MP3 files, or the S3 policy if the bucket will store them. Do not combine the two examples.

For **local WordPress storage**, paste this policy exactly as shown:

    {
      "Version": "2012-10-17",
      "Statement": [
        {
          "Sid": "UseAmazonPolly",
          "Effect": "Allow",
          "Action": [
            "polly:DescribeVoices",
            "polly:SynthesizeSpeech"
          ],
          "Resource": "*"
        }
      ]
    }

For **Amazon S3 storage**, first replace both occurrences of `YOUR-BUCKET-NAME` with the exact bucket name copied in step 3, then paste the complete policy. For example, `arn:aws:s3:::YOUR-BUCKET-NAME` becomes `arn:aws:s3:::example-com-polly-audio-1234`. Keep the `arn:aws:s3:::` prefix and keep the `/*` at the end of the second bucket resource.

    {
      "Version": "2012-10-17",
      "Statement": [
        {
          "Sid": "UseAmazonPolly",
          "Effect": "Allow",
          "Action": [
            "polly:DescribeVoices",
            "polly:SynthesizeSpeech"
          ],
          "Resource": "*"
        },
        {
          "Sid": "CheckAudioBucket",
          "Effect": "Allow",
          "Action": "s3:ListBucket",
          "Resource": "arn:aws:s3:::YOUR-BUCKET-NAME"
        },
        {
          "Sid": "ManageGeneratedAudio",
          "Effect": "Allow",
          "Action": [
            "s3:PutObject",
            "s3:DeleteObject"
          ],
          "Resource": "arn:aws:s3:::YOUR-BUCKET-NAME/*"
        }
      ]
    }

7. Choose **Next**. AWS checks the JSON and shows the permissions that will be granted. The `Resource: "*"` value for the two Polly operations is expected; those operations require it and it does not grant access to other AWS services.
8. On **Review and create**, enter `WordPressPollyPlugin` in **Policy name**. You can enter `Permissions required by the AI Text-to-Speech WordPress plugin` in **Description**.
9. Choose **Create policy**. A green success message should appear.

The S3 policy has three separate jobs: `s3:ListBucket` lets the plugin verify the bucket, `s3:PutObject` lets it upload MP3 files, and `s3:DeleteObject` lets it remove obsolete MP3 files. Do not use broad policies such as `AdministratorAccess`, `AmazonPollyFullAccess`, or `AmazonS3FullAccess` in place of the policy above.

For a simpler setup, related installations such as development and staging can share one IAM user, access key, and policy while still using separate buckets. The shared IAM policy must include every installation's bucket in both S3 statements. In `CheckAudioBucket`, replace the single `Resource` value with an array of bucket ARNs:

    "Resource": [
      "arn:aws:s3:::FIRST-BUCKET-NAME",
      "arn:aws:s3:::SECOND-BUCKET-NAME"
    ]

In `ManageGeneratedAudio`, use a matching array of object ARNs, each ending in `/*`:

    "Resource": [
      "arn:aws:s3:::FIRST-BUCKET-NAME/*",
      "arn:aws:s3:::SECOND-BUCKET-NAME/*"
    ]

Add both ARN forms for every additional site or environment bucket. Every installation using these shared credentials can access every bucket listed in the policy. For stronger isolation, especially for production or independently administered sites, create a separate IAM policy, IAM user, and access key containing only that installation's bucket. If you change the bucket configured in one installation, retain access to its previous bucket until all audio stored there has been removed; cleanup uses the bucket recorded when each audio file was generated.

Official policy instructions and permission references: https://docs.aws.amazon.com/IAM/latest/UserGuide/access_policies_create-console.html, https://docs.aws.amazon.com/polly/latest/dg/api-permissions-reference.html, and https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-with-s3-policy-actions.html

= 5. Create a dedicated IAM user =

This is a technical user for the plugin. It does not need a password and nobody should use it to sign in to the AWS Console.

1. Stay in the IAM console and choose **Users** in the left menu.
2. Choose **Create user**.
3. In **User name**, enter a recognizable name such as `wordpress-polly-plugin` or `wordpress-polly-example-com`.
4. Leave **Provide user access to the AWS Management Console** unchecked. The plugin needs an access key, not console access.
5. Choose **Next**.
6. On **Set permissions**, select **Attach policies directly**.
7. In the permissions-policy search box, enter `WordPressPollyPlugin`.
8. Select the checkbox next to the policy created in step 4. Make sure the policy name appears in the permissions summary.
9. Choose **Next**, review the user, then choose **Create user**.
10. Open the new user from the user list and check the **Permissions** tab. `WordPressPollyPlugin` must be listed under **Permissions policies**. If it is not listed, choose **Add permissions**, choose **Attach policies directly**, select it, and save.

Official IAM user instructions: https://docs.aws.amazon.com/IAM/latest/UserGuide/id_users_create.html

= 6. Create the access key =

1. On the page for the `wordpress-polly-plugin` user, open the **Security credentials** tab.
2. Scroll to **Access keys** and choose **Create access key**.
3. On **Access key best practices & alternatives**, select **Other**, confirm that you understand the recommendation if AWS asks, and choose **Next**.
4. In **Description tag value**, enter the WordPress site hostname, for example `www.example.com`, so the key can be identified later.
5. Choose **Create access key**.
6. Keep this page open. It shows two different values: **Access key ID** and **Secret access key**. Choose **Show** if the secret is hidden.
7. Copy both values to the WordPress settings in step 7, or choose **Download .csv file** and temporarily save the file in a secure location. AWS will not show this secret access key again after you leave the page.

If the secret is lost, do not try to recover it. Create a new access key, update WordPress, verify that it works, and then deactivate and delete the old key. An IAM user can have at most two access keys at the same time.

Official access-key instructions: https://docs.aws.amazon.com/IAM/latest/UserGuide/access-key-self-managed.html

= 7. Enter the AWS settings in WordPress =

1. Sign in to WordPress as an administrator.
2. In the left WordPress menu, choose **AI TTS -> General**.
3. Paste the AWS **Access key ID** into **AWS access key**. Do not paste the IAM user name here.
4. Paste the AWS **Secret access key** into **AWS secret key**. Take care not to add a space before or after the value.
5. In **AWS Region**, select the same Region used in steps 2 and 3.
6. For local storage, leave **Amazon S3 bucket name** empty. For S3 storage, enter only the exact bucket name copied in step 3.
7. Choose **Save Changes**.
8. Open **AI TTS -> Text-To-Speech**.
9. Enable **Enable text-to-speech support**. If you created an S3 bucket, also enable **Store audio in Amazon S3**; leave it disabled for local WordPress storage.
10. Choose **Save Changes** again. The **Voice name** list should load voices available in the selected Region.

The Access key ID and Secret access key must come from the same access key. Mixing an ID from one key with a secret from another key will always fail.

= 8. Allow visitors to play files stored in S3 (skip for local storage) =

Complete this step only when **Store audio in Amazon S3** is enabled. The plugin uploads MP3 files but does not change the bucket's public-access settings.

The easiest direct-delivery setup uses a dedicated public bucket:

1. Open **S3 -> General purpose buckets** and choose the bucket created in step 3.
2. Open the **Permissions** tab.
3. Find **Block public access (bucket settings)** and choose **Edit**.
4. Clear **Block all public access**, acknowledge the warning, and choose **Save changes**. AWS may ask you to type `confirm`.
5. On the same **Permissions** tab, find **Bucket policy** and choose **Edit**.
6. Replace `YOUR-BUCKET-NAME` in the policy below with the exact bucket name. Do not remove the `/*` from the end of the resource.
7. Paste the complete policy into the editor and choose **Save changes**.

        {
          "Version": "2012-10-17",
          "Statement": [
            {
              "Sid": "PublicReadGeneratedAudio",
              "Effect": "Allow",
              "Principal": "*",
              "Action": "s3:GetObject",
              "Resource": "arn:aws:s3:::YOUR-BUCKET-NAME/*"
            }
          ]
        }

A bucket policy is attached to one bucket only. For separate environment or site buckets, repeat steps 1-7 for every bucket and save a separate policy containing that bucket's own ARN. Do not add another bucket's ARN to a policy attached to a different bucket.

Use a bucket dedicated to plugin audio because this policy makes every object in that bucket downloadable by anyone who knows its URL. If AWS refuses to save the policy, the account-level **Block Public Access** setting may still prohibit public buckets. Do not change an account-wide security setting unless you understand how it affects the account's other buckets. In that situation, ask the AWS account administrator for help or use Amazon CloudFront with a private S3 origin and Origin Access Control, then enter the CloudFront distribution domain in the plugin settings.

Official public-read instructions: https://docs.aws.amazon.com/AmazonS3/latest/userguide/WebsiteAccessPermissionsReqd.html

= 9. Verify the setup =

1. In WordPress, open **AI TTS -> Text-To-Speech**. If the voice list loads, the access key, Region, IAM user, and Polly permissions are working.
2. Select a language and voice, keep text-to-speech enabled, and save the settings.
3. Create or edit a test post, enable text-to-speech for that post, and update or publish it.
4. Audio generation runs in the background through WP-Cron, so it may not appear immediately. Wait for the job to finish, then open the post and play the audio.
5. With S3 storage, open the bucket's **Objects** tab. A generated file has a name such as `itron_polly_tts_123.mp3`, optionally inside year/month folders.

Common problems:

* **The voice list does not load:** verify that both credential fields are filled, the access key is **Active**, `WordPressPollyPlugin` is attached to the IAM user, and the selected Region supports Amazon Polly.
* **AWS reports an invalid access key or signature:** copy the Access key ID and Secret access key again from the same downloaded CSV. Remove accidental spaces. Create a new key if the secret is no longer available.
* **The S3 bucket is not accessible:** verify the exact bucket name and Region. Check that both S3 ARNs in `WordPressPollyPlugin` contain that exact name and that **Store audio in Amazon S3** is enabled only when a bucket is configured.
* **Audio is generated but the browser returns 403 Access Denied:** direct S3 delivery is still blocked, the public bucket policy has the wrong bucket name, or CloudFront cannot access the private bucket.
* **The policy editor reports an invalid resource:** a bucket ARN must look like `arn:aws:s3:::example-com-polly-audio-1234`; an object ARN must have `/*` at the end.
* **Nothing is generated after saving a post:** confirm that WordPress WP-Cron works, text-to-speech is enabled globally and on the post, and the post type is enabled in the plugin settings.

= Optional: store credentials outside the WordPress database =

The steps above save the credentials in WordPress so that the setup is straightforward. For a production site, an administrator can instead define them in `wp-config.php` or another PHP config file loaded before WordPress finishes bootstrapping. This keeps them out of the WordPress options table. The configuration file must be outside version control and readable only by the server account that needs it:

    define( 'ITRON_POLLY_TTS_S3_ACCESS_KEY', 'your-access-key' );
    define( 'ITRON_POLLY_TTS_S3_SECRET_KEY', 'your-secret-key' );

You can also lock the bucket and region in PHP the same way:

    define( 'ITRON_POLLY_TTS_S3_BUCKET_NAME', 'your-s3-bucket' );
    define( 'ITRON_POLLY_TTS_S3_REGION', 'us-east-1' );

When these constants are present, the plugin uses them instead of saved options and shows the related admin fields as defined by PHP constant. Do not commit real secrets into version control.

Use the IAM user and access key only for this plugin. Review the key's last-used date, rotate it periodically, and deactivate or delete it immediately if it is exposed or no longer needed. Do not email credentials, paste them into support tickets, or commit them to Git.

= Audio access and removal =

Generated audio uses public delivery URLs, including audio stored locally. Post passwords, private visibility, and unpublished status do not protect the audio file. Before generating audio for a password-protected, private, or unpublished post (including drafts, pending review, and scheduled posts), the editor asks for explicit confirmation that the audio will be public. Bulk generation asks once for all selected posts that need this confirmation; declining skips their audio without cancelling ordinary post saves or generation for other selected posts.

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
* Updated supported Polly regions and languages, and removed obsolete Conversational SSML and inherited AWS project request identifiers.
* Added public-audio confirmation for password-protected, private, and unpublished posts and strengthened metadata, bulk-action, and player security.
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
