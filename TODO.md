# Sumsub KYC Plugin Improvements TODO

## 1. Create New Files
- [x] includes/class-settings.php: Admin settings page for API token and webhook secret
- [x] includes/class-webhook.php: REST API endpoint for Sumsub webhooks

## 2. Edit Existing Files
- [x] sumsub-kyc.php: Include new classes, update init, add multisite support
- [x] includes/class-api.php: Use options for token, add error handling, set user metas on creation
- [x] includes/class-cron.php: Use 'kyc_initiated' meta, add logging, improve actions
- [x] includes/class-hooks.php: Add KYC initiation on registration, improve login block

## 3. Testing
- [ ] Critical-path: API config save, cron reminder/block, login check, webhook update
- [ ] Thorough: Edge cases (invalid token, multisite, errors), full user flows

## 4. Admin Enhancements
- [x] Enhance class-settings.php: Add reminder/block/deactivate days settings, email templates, dashboard submenu with modern UI
- [x] Update class-cron.php: Use dynamic settings and email templates
- [x] Enqueue modern CSS/JS for admin pages

## 5. User Flow Implementation
- [x] Enhance class-api.php: Add access token generation and verification URL return
- [x] Update class-hooks.php: Add auto-login and redirect to Sumsub after registration
- [x] Ensure access granted post-verification

## 6. Manual Admin Actions
- [x] Update class-settings.php: Dashboard for all users with action buttons for send reminder and initiate KYC
- [x] Add AJAX handlers for manual actions
- [x] Update admin.js: Handle button clicks and AJAX
- [x] Add pagination to dashboard for large user lists
- [x] Support HTML email templates with verification links in reminders
- [x] Add manual block/unblock user functionality in dashboard
- [x] Add temp email checker: Auto-block new users with temp emails, scan existing users

## 7. Finalize
- [ ] Code review, linting
- [x] Documentation updates
- [x] Improve dashboard UI/UX
