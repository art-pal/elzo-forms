=== Elzo Forms ===
Contributors: elzoforms
Tags: contact form, form builder, file upload, conditional logic, multi step form
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build serious WordPress forms for free — multiple file uploads, stored entries, conditional logic, multi-step forms, and spam protection included.

== Description ==

Elzo Forms is a WordPress form builder for people who need more than a basic contact form without immediately running into paid-only essentials.

**Multiple file uploads, stored submissions, conditional logic, multi-step forms, email notifications, spam protection, styling controls, and Gutenberg support are included for free.**

Build contact forms, quote and request forms, lead forms, job applications, feedback forms, file upload forms, and other custom forms directly inside WordPress.

Submissions are processed and stored on your own site. No Elzo account, page builder, or external form service is required.

= More than a basic free form builder =

Many forms eventually need more than a few text fields. Elzo Forms includes the features needed to build more practical workflows without requiring an upgrade just to use them.

* Upload one or multiple files.
* Set file size, file count, and allowed file type limits.
* Upload large files using chunked uploads.
* Save submissions in the WordPress admin.
* Build multi-step forms.
* Show or hide fields with conditional logic.
* Send configurable email notifications.
* Redirect visitors or display a custom success message.
* Protect forms from spam without requiring CAPTCHA.
* Add forms with a native Gutenberg block or shortcode.

= Building forms =

* Visual form builder inside the WordPress admin.
* Searchable field picker for quickly finding and adding fields.
* Keyboard-friendly field selection with search, arrow navigation, and Enter to add.
* Field types: text, textarea, select, checkbox, radio, range, file upload, hidden, content, and button.
* Text fields also cover email, telephone, URL, number, date, time, and password input, with matching validation.
* Responsive field widths (full, 1/2, 1/3, 1/4) for multi-column layouts.
* Multi-step forms with configurable step navigation text.
* Conditional field visibility based on field values, visitor login state, or page context.
* Native Elzo Form block with an editor preview and width and alignment settings; the shortcode supports the same layout options.

= Multiple file uploads — free =

File uploads are included in Elzo Forms without requiring a paid upgrade.

* Single or multiple file uploads.
* Minimum and maximum file count controls.
* Maximum upload size.
* Allowed file type restrictions.
* Chunked uploads for large files.
* Executable file types such as `.php` and `.phtml` are rejected.

Use file fields for job applications, document requests, support forms, project briefs, image submissions, and other workflows where visitors need to send more than text.

= Store submissions in WordPress — free =

Every successful submission can be stored on your own WordPress site and reviewed from Forms > Submissions.

Stored submissions include:

* Submitted field values.
* Uploaded files.
* Submission time.
* Visitor information.
* The page the form was submitted from.

You do not need a separate dashboard or external account to access your form entries.

= Conditional logic — free =

Forms do not have to show every field to every visitor.

Elzo Forms can show or hide fields based on:

* Values entered in other fields.
* Whether the visitor is logged in or logged out.
* The page or post where the form appears.

This makes it possible to build quote forms, applications, qualification forms, request forms, and other adaptive flows without custom JavaScript.

Additional condition types are available in Elzo Forms PRO.

= Multi-step forms — free =

Split longer forms into multiple steps instead of presenting every field at once.

Configure step labels and navigation text directly in the form builder. Multi-step forms can be combined with file uploads and conditional logic.

= Submissions and notifications =

* Submissions are stored in the WordPress admin with submitted values, uploaded files, and visitor details.
* Email notifications with configurable recipients, subject, and message text.
* AJAX or standard form submissions.
* Custom success message or redirect after a successful submission.
* File uploads with size limits, file count limits, allowed file type checks, and chunked uploads for large files.

= Gutenberg block =

Add a form directly from the WordPress block editor using the native Elzo Form block.

Choose a form from the block settings and see a non-interactive preview inside the editor. Layout controls let you configure maximum width, form alignment, and text alignment.

Shortcodes remain available for classic editor content, widgets, templates, and other shortcode-enabled locations.

= Spam protection =

Elzo Forms includes built-in spam controls that work without requiring a third-party CAPTCHA service:

* Minimum time before a form can be submitted.
* Minimum interval between submissions.
* Blocked IP addresses.
* Blocked words.
* Blocked submissions can be discarded or kept and marked as spam for review.

Optional Google reCAPTCHA v3 and Akismet modules are also available. Both remain disabled until you enable them and provide your own credentials.

= Styling and customization =

* Global and per-form settings for form behavior, texts, and styling.
* Template overrides from an `elzo-forms` directory in your theme.
* Hooks and filters for custom field types, validation, and submission handling.
* Optional read-only JSON form sources, so developers can ship forms with a theme or plugin and keep them under version control.

= Free and PRO =

Elzo Forms free is designed to be useful on its own rather than functioning as a limited demo.

The free plugin includes the core form-building features described above, including multiple file uploads, stored submissions, multi-step forms, core conditional logic, email notifications, styling, spam protection, and Gutenberg integration.

Elzo Forms PRO adds workflow and automation features beyond the core form builder, including post-submission automations, webhooks, conditional automation actions, cookie actions, and additional conditional logic types.

Elzo Forms PRO is a separate commercial plugin available at https://elzoforms.com. It replaces the free runtime instead of extending it: while PRO is active, Elzo Forms free stops loading and can be deactivated.

== Installation ==

1. Install Elzo Forms from the WordPress.org plugin directory, or upload the `elzo-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Forms > Add New in the WordPress admin.
4. Add fields, configure form settings, and publish the form.
5. Add the Elzo Form block and select your form. You can also use a shortcode such as `[elzo_form id="123"]`.

== Frequently Asked Questions ==

= What is included in the free version? =

The free plugin includes built-in field types, multiple file uploads, stored submissions, multi-step forms, core conditional logic, email notifications, styling controls, spam protection, the native Gutenberg block, developer hooks, template overrides, and JSON form sources.

PRO adds post-submission automations and additional conditional logic types.

= How do I display a form on my site? =

Add the Elzo Form block in the block editor and choose a form from the selector.

In the classic editor or a shortcode-enabled widget area, use:

`[elzo_form id="123"]`

You can also pass layout options:

`[elzo_form id="123" max_width="600px" form_align="center" text_align="left"]`

= Does it work with the block editor? =

Yes. Add the native Elzo Form block, then choose a form in the block.

The editor displays a non-interactive preview using the same rendering engine as the frontend. The block's layout controls can set a maximum width and form and text alignment.

Shortcodes remain supported in the classic editor, widget areas, and theme templates.

= Can I place the same form more than once on a page? =

Yes. Each rendered instance receives unique HTML IDs, while field names and the submitted data structure stay unchanged.

= Where are submissions stored? =

Submissions are stored on your site as Elzo Forms submission records and can be opened from Forms > Submissions in the WordPress admin.

= Are stored submissions available in the free version? =

Yes. Stored submissions are included in Elzo Forms free.

= Can I receive email notifications? =

Yes. Enable email notifications in the form settings and configure the recipient address, subject, and message text.

= Can visitors upload multiple files? =

Yes. Multiple file uploads are included in the free plugin.

The file field supports minimum and maximum file counts, upload size limits, allowed file types, and chunked uploads for large files. Executable file types such as `.php` and `.phtml` are rejected.

= Does the free plugin include conditional logic? =

Yes. Field visibility can depend on the values of other fields, whether the visitor is logged in, and the page the form is displayed on.

Additional condition types are available in Elzo Forms PRO.

= Are multi-step forms available in the free version? =

Yes. Multi-step forms and configurable step navigation are included in Elzo Forms free.

= Is Google reCAPTCHA required? =

No. Elzo Forms includes built-in spam controls that do not require Google reCAPTCHA.

Google reCAPTCHA v3 is optional. Elzo Forms does not load the Google reCAPTCHA API or verify submissions with Google unless the reCAPTCHA module is enabled and configured with API keys.

= Does the plugin send data to third-party services? =

No, not by default. Submissions are processed on your WordPress site.

The optional anti-spam modules connect to their third-party services only after you enable and configure them. See the Third-party Services section for details.

= Where does Elzo Forms store files? =

All generated files are stored below the site-specific directory returned by `wp_upload_dir()`.

Files uploaded through a form go to `elzo-forms/user-uploads`, and generated CSS goes to `elzo-forms/assets`.

= What happens when I delete the plugin? =

Your forms, submissions, uploaded files, and settings are kept.

Deleting Elzo Forms does not remove them, so you can reinstall the plugin without losing data. Remove the forms and submissions yourself first if you want the data gone.

= Can developers customize the output? =

Yes. Themes can override plugin templates from a theme `elzo-forms` directory, and WordPress hooks and filters cover custom field types, validation, and submission handling.

A filter can also register read-only JSON form sources. Elzo Forms never creates or modifies those JSON files; it only reads forms from the paths a developer configures.

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

1. Visual form builder with a searchable field picker, drag-and-drop field arrangement, and individual field settings.
2. Form style settings for colors, input appearance, borders, typography, and other visual options.
3. Conditional field visibility based on form values, visitor login state, or page context.
4. Multi-step forms with step labels, progress information, and configurable navigation text.
5. Stored submissions with submitted field values, visitor information, and uploaded files.
6. Email notifications, custom success messages, and optional redirects after form submission.
7. Custom field type example showing how developers can extend Elzo Forms with their own field UI and behavior.

== Support ==

For support, use the WordPress.org support forum for this plugin or visit https://elzoforms.com.

== Changelog ==

= 1.1.0 =

* Added the native Elzo Form block with form selection and an editor preview.
* Added width and alignment settings to the Elzo Form block.
* Added a searchable, keyboard-friendly field picker.
* Added conditions on how many files were uploaded or how many options were selected.
* Page and post type conditions now pick content from your site instead of asking for IDs or slugs.
* Date, Time, Number, URL, Email, and Phone fields are now validated on the server as well as in the browser.
* Improved field type selection and switching.
* Improved the conditional logic type interface.
* Improved form builder usability, reliability, and compatibility.
* Fixed uploaded files staying on the server after their submission was deleted.
* Fixed file upload security: uploads are accepted only in order, and removing a file deletes it right away.
* Fixed conditions based on a file field not reacting to uploads until the form was submitted.
* Fixed conditions seeing only the first choice of a multiple select.
* Fixed a form cleared after submission keeping uploaded files, slider values, and its last step.
* Fixed file uploads when previewing an unpublished form.
* Fixed password fields changing some characters of the password on save.
* Fixed saving a form removing conditions whose type is temporarily unavailable.
* Fixed the max_width and text_align shortcode attributes accepting extra CSS.
* Fixed the Forms menu showing "Add New Post" on WordPress 6.5 and 6.6.
* Fixed form and submission meta box titles not being translatable.

= 1.0.0 =

* Initial public release for WordPress.org.
* Added the form builder, core field types, multi-step forms, AJAX and standard submissions, submissions storage, email notifications, redirects, file uploads, conditional field visibility, spam controls, optional Google reCAPTCHA v3 and Akismet protection, template overrides, and read-only JSON form sources.
