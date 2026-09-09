=== Elzo Forms ===
Contributors: elzoforms
Tags: contact form, form builder, custom forms, file upload, spam protection
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build contact and custom forms with a visual builder: multi-step, file uploads, conditional logic, email alerts, and spam protection.

== Description ==

Elzo Forms is a form builder for WordPress. You build a form in the admin, place one shortcode where the form should appear, and every submission is stored on your own site. No page builder, no account, and no external service is required to run a form.

Use it for contact forms, request and quote forms, lead forms, application forms, feedback forms, file upload forms, and other everyday site forms.

= Building forms =

* Visual form builder inside the WordPress admin.
* Field types: text, textarea, select, checkbox, radio, range, file upload, hidden, content, and button.
* Text fields also cover email, telephone, URL, number, date, time, and password input, with matching validation.
* Responsive field widths (full, 1/2, 1/3, 1/4) for multi-column layouts.
* Multi-step forms with configurable step navigation text.
* Conditional field visibility based on field values, visitor login state, or page context.
* Shortcode output with width and alignment options.

= Submissions and notifications =

* Submissions are stored in the WordPress admin with the submitted values, uploaded files, and visitor details.
* Email notifications with configurable recipients, subject, and message text.
* AJAX or standard form submissions.
* Custom success message, or a redirect after a successful submission.
* File uploads with size limits, allowed file type checks, and chunked uploads for large files.

= Spam protection =

* Minimum time before a form can be submitted, and a minimum interval between submissions.
* Blocked IP addresses and blocked words.
* Blocked submissions can be discarded or kept and marked as spam for review.
* Optional Google reCAPTCHA v3 and Akismet modules. Both stay disabled until you enable them and add your own keys.

= Styling and customization =

* Global and per-form settings for form behavior, texts, and styling.
* Template overrides from an `elzo-forms` directory in your theme.
* Hooks and filters for custom field types, validation, and submission handling.
* Optional read-only JSON form sources, so a developer can ship forms with a theme or plugin and keep them under version control.

= Free and paid versions =

Everything described above is included in this plugin. No field type, module, or setting is locked behind a purchase.

Post-submission automations, including webhooks, conditional automation actions, and cookie actions, are part of Elzo Forms PRO, a separate commercial plugin available at https://elzoforms.com. Elzo Forms PRO replaces this plugin instead of extending it: while it is active, Elzo Forms (free) stops loading and can be deactivated.

== Installation ==

1. Install Elzo Forms from the WordPress.org plugin directory, or upload the `elzo-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Forms > Add New in the WordPress admin.
4. Add fields, configure form settings, and publish the form.
5. Copy the shortcode, for example `[elzo_form id="123"]`, into any post, page, or widget area that supports shortcodes.

== Frequently Asked Questions ==

= How do I display a form on my site? =

Add the form shortcode to a post, page, or shortcode-enabled widget area:

`[elzo_form id="123"]`

You can also pass layout options:

`[elzo_form id="123" max_width="600px" form_align="center" text_align="left"]`

= Does it work with the block editor? =

Yes. Elzo Forms does not register its own block in this release, so add the form with the core Shortcode block, or paste `[elzo_form id="123"]` into any block that renders shortcodes. The same shortcode works in the classic editor, in widget areas, and in theme templates.

= Where are submissions stored? =

Submissions are stored on your site as Elzo Forms submission records and can be opened from Forms > Submissions in the WordPress admin.

= Can I receive email notifications? =

Yes. Enable email notifications in the form settings and configure the recipient address, subject, and message text.

= Can visitors upload files? =

Yes. The file field supports upload size limits and a list of allowed file types, and it splits large uploads into chunks. Executable file types such as `.php` and `.phtml` are always rejected.

= Does the plugin include conditional logic? =

Yes. Field visibility can depend on the values of other fields, on whether the visitor is logged in, and on the page the form is displayed on.

= Is Google reCAPTCHA required? =

No. Google reCAPTCHA v3 is optional. Elzo Forms does not load the Google reCAPTCHA API or verify submissions with Google unless the reCAPTCHA module is enabled and configured with API keys.

= Does the plugin send data to third-party services? =

No, not by default. Submissions are processed on your WordPress site. The optional anti-spam modules connect to their third-party services only after you enable and configure them. See the Third-party Services section for details.

= Where does Elzo Forms store files? =

All generated files are stored below the site-specific directory returned by `wp_upload_dir()`. Files uploaded through a form go to `elzo-forms/user-uploads`, and generated CSS goes to `elzo-forms/assets`.

= What happens when I delete the plugin? =

Your forms, submissions, uploaded files, and settings are kept. Deleting Elzo Forms does not remove them, so you can reinstall the plugin without losing data. Remove the forms and submissions yourself first if you want the data gone.

= Can developers customize the output? =

Yes. Themes can override plugin templates from a theme `elzo-forms` directory, and WordPress hooks and filters cover custom field types, validation, and submission handling. A filter can also register read-only JSON form sources: Elzo Forms never creates or modifies those JSON files, it only reads forms from the paths a developer configures.

== Privacy ==

Elzo Forms stores form submissions on your own site, in your WordPress database.

For each submission the plugin stores the submitted field values, the time of submission, the visitor IP address and browser user agent, the WordPress user ID if the visitor is logged in, and the page the form was submitted from. Files uploaded through a file field are stored in your uploads directory under `elzo-forms/user-uploads`.

None of this data leaves your site by default. It is shared with a third party only if you enable one of the optional anti-spam modules described below, and only in the way those sections describe.

Elzo Forms does not add submission data to the WordPress personal data export and erasure tools. Submissions are managed from Forms > Submissions in the WordPress admin.

== Third-party Services ==

= Google reCAPTCHA v3 =

Elzo Forms includes an optional Google reCAPTCHA v3 module for spam protection. The module is disabled until a site administrator enables it and configures Google reCAPTCHA v3 site and secret keys.

Service provider: Google LLC.

When the module is enabled and a protected form with a configured site key is rendered, Elzo Forms loads the Google reCAPTCHA JavaScript API from:

`https://www.google.com/recaptcha/api.js`

Before a protected form is submitted, the browser asks Google reCAPTCHA to generate a token for the form action. During submission, Elzo Forms sends the token, the configured secret key, and the visitor IP address to Google's verification endpoint:

`https://www.google.com/recaptcha/api/siteverify`

Google may receive information required for reCAPTCHA risk analysis and verification, such as the site key, reCAPTCHA token, action name, visitor IP address, browser/client information, and interaction signals. Elzo Forms uses the returned verification result, action, and score to decide whether the submission should be accepted or rejected as spam.

If reCAPTCHA logging is enabled in the module settings, Elzo Forms may store the returned reCAPTCHA score in submission metadata and write verification pass/fail messages to the site's PHP error log for debugging.

Google reCAPTCHA is governed by Google's terms and privacy policy:

* Terms: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy
* reCAPTCHA documentation: https://developers.google.com/recaptcha/docs/v3

= Akismet =

Elzo Forms includes an optional Akismet module for spam protection. The module is disabled until a site administrator enables it and configures an Akismet API key.

Service provider: Automattic Inc.

Elzo Forms does not contact Akismet or send submission data to Automattic unless the Akismet module is enabled and configured with an API key. By enabling and configuring the Akismet module, the site administrator chooses to use Akismet as a third-party spam protection service.

During submission, Elzo Forms sends contact-form spam check data to Akismet's comment-check endpoint:

`https://rest.akismet.com/1.1/comment-check`

Akismet may receive submitted text content, likely contact identity fields such as name, email address, or website URL when present, visitor IP address, browser user agent, referrer, form page URL, site URL, site language, and site character set. Elzo Forms uses the returned spam/ham result to decide whether the submission should be accepted or marked as spam.

If Akismet logging is enabled in the module settings, Elzo Forms may store the Akismet result and reason in submission metadata and write verification pass/fail messages to the site's PHP error log for debugging.

Site administrators should review Automattic's terms and privacy policy and update their own privacy policy or visitor notices as needed before enabling Akismet.

Akismet is governed by Automattic's terms and privacy policy:

* Terms: https://automattic.com/terms/
* Privacy Policy: https://automattic.com/privacy/
* Akismet documentation: https://akismet.com/developers/detailed-docs/comment-check/

== Screenshots ==

1. Visual form builder with drag-and-drop field arrangement and individual field settings.
2. Form style settings for colors, input appearance, borders, typography, and other visual options.
3. Conditional field visibility based on form values, visitor login state, or page context.
4. Multi-step forms with step labels, progress information, and configurable navigation text.
5. Stored submissions with submitted field values, visitor information, and uploaded files.
6. Email notifications, custom success messages, and optional redirects after form submission.
7. Custom field type example showing how developers can extend Elzo Forms with their own field UI and behavior.

== Support ==

For support, use the WordPress.org support forum for this plugin or visit https://elzoforms.com.

== Changelog ==

= 1.0.0 =
* Initial public release for WordPress.org.
* Added the form builder, core field types, multi-step forms, AJAX and standard submissions, submissions storage, email notifications, redirects, file uploads, conditional field visibility, spam controls, optional Google reCAPTCHA v3 and Akismet protection, template overrides, and read-only JSON form sources.
