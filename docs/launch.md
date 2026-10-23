# Launch

The last step before pointing a real domain at the site:

```bash
php artisan launch:check
```

It changes nothing. It exits non-zero and lists every problem while any of
these remain:

- `APP_ENV` is not `production`, `APP_DEBUG` is true, or `APP_NAME` is still `Laravel`
- `MAIL_MAILER` is `log`, `array` or `null` (nothing would be delivered), or
  `MAIL_FROM_ADDRESS` is empty or an example.com address
- `TRUSTED_PROXIES` is empty. If the app serves HTTPS itself with no proxy in
  front, run `php artisan launch:check --direct-tls` to say so
- any site setting still contains a placeholder or an example.com address, or
  "Send new enquiries to" is empty
- the Privacy Policy or Terms of Service page is not published
- an active service or core value, or a published page, still contains
  `[PLACEHOLDER]` text
- `failed_jobs` is not empty (contact-form emails may not have been sent)

It only warns (exit code stays 0) about blank public contact details and
meta description, no active services, and an unpublished Home page.

Run it after the config is final and the content is in, not before: run
`php artisan config:cache` first, since it checks the values the app will use.

## What a production seed publishes

`php artisan db:seed --force` in production publishes **nothing** with
placeholder text:

- the 7 services and 6 core values are created **inactive**, with placeholder
  copy. Edit each in the admin, then tick Active.
- `contact.email`, `contact.phone`, `contact.address`,
  `seo.default_description`, `email.from_address` and `email.enquiry_recipient`
  are created **blank**. The public site leaves out blank fields (footer,
  contact page, structured data).
- Home, About, Privacy Policy and Terms of Service are empty drafts. About and
  the legal pages 404, and their links stay hidden, until published.

Fill in Admin > Settings (notably "Send new enquiries to"), then run the check.
Re-running the seed never overwrites values already entered in the admin.
Other environments (local, testing) still get the demo content.

## Related

- Proxies and HTTPS: `docs/admin-auth.md` "Trusted proxies"
- Queue worker, scheduler, failed jobs: `docs/admin-auth.md` sections 3
