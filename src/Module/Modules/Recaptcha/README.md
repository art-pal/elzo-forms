# reCAPTCHA Module for Elzo Forms

Protect your forms from spam using Google reCAPTCHA v3 (invisible, score-based protection).

## Features

- **Invisible Protection** - Uses reCAPTCHA v3 which requires no user interaction
- **Score-Based** - Assigns a score (0.0 to 1.0) to each submission and blocks low scores
- **Configurable Threshold** - Adjust sensitivity to match your needs
- **Per-Form Control** - Enable/disable per form or globally
- **Logging** - Debug mode to track scores and failures
- **Badge Customization** - Position or hide the reCAPTCHA badge

## Setup

### 1. Get reCAPTCHA Keys

1. Visit [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Create a new site with **reCAPTCHA v3**
3. Copy your Site Key (public) and Secret Key (private)

### 2. Configure Module

1. Go to `Forms → Settings → Modules`
2. Enable **reCAPTCHA Protection**
3. Enter your Site Key and Secret Key
4. Set your Score Threshold (recommended: 0.5)
5. Save settings

### 3. Enable for Forms

**Option A: Enable globally (all forms)**
- Already enabled if you enabled the module in step 2

**Option B: Enable for specific forms**
1. Edit a form
2. Go to the **Modules** tab
3. Set reCAPTCHA to "Enabled for this form"
4. Optionally override settings for this form
5. Save

## Settings

### Site Key (Required)
Your reCAPTCHA v3 site key. This is the public key shown to users.

### Secret Key (Required)
Your reCAPTCHA v3 secret key. This is private and used for server-side verification.

### Score Threshold
Minimum acceptable score (0.0 = bot, 1.0 = human):
- **0.1** - Very lenient (blocks only obvious bots)
- **0.3** - Lenient
- **0.5** - Balanced (recommended)
- **0.7** - Strict
- **0.9** - Very strict (may block real users)

### Action Name
Identifier for analytics tracking. Default: `submit`

### Badge Position
Where to display the reCAPTCHA badge:
- **Bottom Right** (default)
- **Bottom Left**
- **Inline** (within form)

### Hide Badge
Hide the reCAPTCHA badge entirely. 

**Important:** If you hide the badge, you must include this text in your privacy policy:
> "This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply."

### Enable Logging
Log reCAPTCHA verification attempts to PHP error log for debugging.

## How It Works

1. **Form Load** - reCAPTCHA script is loaded automatically
2. **Form Submit** - Before submission, reCAPTCHA generates a token
3. **Server Validation** - Token is verified with Google
4. **Score Check** - If score is below threshold, submission is rejected
5. **Success** - Form processes normally if score passes

## Troubleshooting

### "reCAPTCHA verification failed"
- Check that your Site Key and Secret Key are correct
- Ensure the keys are for reCAPTCHA v3 (not v2)
- Verify your domain is registered in reCAPTCHA admin

### "Your submission appears to be spam"
- Score is below threshold
- Lower the threshold or check if user behavior is suspicious
- Enable logging to see actual scores

### Badge not showing
- Badge is hidden by default if `hide_badge` is enabled
- Check badge position setting
- Verify reCAPTCHA script is loading (check browser console)

### Enable Debug Logging
1. Enable "Enable Logging" in module settings
2. Submit a form
3. Check PHP error log for entries like:
   ```
   Elzo Forms reCAPTCHA Response: {...}
   Elzo Forms reCAPTCHA Success: score=0.85
   ```

## Privacy & GDPR

reCAPTCHA collects user data to assess risk. You should:
1. Update your privacy policy to mention reCAPTCHA
2. Include link to [Google Privacy Policy](https://policies.google.com/privacy)
3. Inform users about invisible reCAPTCHA

Example privacy text:
> "This site uses reCAPTCHA for spam protection. Your use of reCAPTCHA is subject to Google's Privacy Policy and Terms of Service."

## Developer Info

### Filters

**Modify validation result:**
```php
add_filter('elzo_forms_validate_submission', function($result, $form_id) {
    // Your custom validation
    return $result;
}, 10, 2);
```

**Add form data attributes:**
```php
add_filter('elzo_forms_form_data_attributes', function($attrs, $form_id) {
    $attrs['data-custom'] = 'value';
    return $attrs;
}, 10, 2);
```

### JavaScript Hook

The frontend script registers a WordPress JavaScript action on `elzoForms.form.submit.beforeSend`. The action receives `(form, beforeSendPromises, submitContext)`. reCAPTCHA pushes its execution Promise into `beforeSendPromises`, so Elzo Forms waits for the generated `g-recaptcha-response` token before building `FormData` and sending the AJAX request.

### Score Storage

When logging is enabled, reCAPTCHA scores are stored in submission meta as `_recaptcha_score`.

Access it:
```php
$score = get_post_meta($submission_id, '_recaptcha_score', true);
```

## Support

For issues or questions:
- Check [reCAPTCHA documentation](https://developers.google.com/recaptcha/docs/v3)
- Enable logging to debug
- Contact support with error log details
