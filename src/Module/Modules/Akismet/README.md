# Akismet Module for Elzo Forms

Protect forms from spam using Akismet's server-side content analysis.

## Features

- **Contact Form Spam Check** - Sends submissions to Akismet with `comment_type=contact-form`
- **No Frontend Script** - Runs fully server-side during form submission
- **Global or Per-Form Control** - Enable globally or override on individual forms
- **Fail-Open by Default** - Service errors do not block valid submissions unless configured
- **Logging** - Optional PHP error log entries and submission meta for debugging

## Setup

### 1. Get an Akismet API Key

1. Visit [Akismet Account](https://akismet.com/account/)
2. Copy your API key

### 2. Configure Module

1. Go to `Forms -> Settings -> Modules`
2. Enable **Akismet Protection**
3. Enter your Akismet API key
4. Save settings

### 3. Enable for Forms

**Option A: Enable globally**
- Once the module is enabled globally, forms inherit Akismet protection.

**Option B: Enable for a specific form**
1. Edit a form
2. Open the **Modules** tab
3. Set Akismet to **Enabled for this form**
4. Optionally override settings for that form
5. Save

## Settings

### API Key
Your Akismet API key. This is required.

### Treat API Errors as Spam
When enabled, submissions are marked as spam if Akismet cannot be reached or returns an unexpected response. By default this is disabled so temporary service outages do not block legitimate visitors.

### Test Mode
Adds Akismet's `is_test` flag to API requests. Use only during testing.

### Enable Logging
Logs Akismet pass/fail/error messages to the PHP error log and stores `_akismet_result` and `_akismet_reason` submission meta.

## How It Works

1. **Form Submit** - Elzo Forms receives the normal form submission.
2. **Payload Build** - The module collects text-like submitted input fields, skipping read-only fields, password fields, and file fields.
3. **Akismet Check** - Elzo Forms posts the payload to `https://rest.akismet.com/1.1/comment-check`.
4. **Spam Decision** - If Akismet returns `true`, the submission is marked as spam and the visitor receives the normal blocked submission response.
5. **Success** - If Akismet returns `false`, the form continues through the normal submission flow.

## Privacy

When enabled, Elzo Forms sends submitted text content, likely contact identity fields, visitor IP address, user agent, referrer, and site metadata to Akismet/Automattic for spam analysis. Update your privacy policy before enabling this module.

Akismet resources:

- API docs: https://akismet.com/developers/detailed-docs/comment-check/
- Terms: https://automattic.com/terms/
- Privacy Policy: https://automattic.com/privacy/

## Developer Info

### Filter Akismet Request Data

```php
add_filter('elzo_forms/akismet/request_data', function(array $data, $form, array $settings, array $submitted_fields): array {
    $data['comment_content'] .= "\nCustom context";
    return $data;
}, 10, 4);
```

### Filter Submitted Fields

```php
add_filter('elzo_forms/akismet/submitted_fields', function(array $fields, $form, array $posted_steps): array {
    return $fields;
}, 10, 3);
```
