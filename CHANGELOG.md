# Changelog

All notable changes are listed here. Versions follow semantic versioning once there is a release.

## Unreleased

- Project layout, licence and contribution guidelines.
- WordPress, WooCommerce and the Stripe and Razorpay gateways managed by Composer.
- Application image and local stack with nginx, MariaDB, Redis and Mailpit.
- One-command install and a demo catalogue seed.
- Platform layer: health and version endpoints, hardening, local mail capture, gateway keys from the environment.
- Storewright block theme.
- CI: coding standards, static analysis, unit tests and a full stack smoke test.
- Payment webhook log: each verified Stripe event is recorded once and repeat deliveries are not reprocessed.
- Stripe stays in test mode on every store that is not production.
- `wp storewright setup` applies store defaults and a domestic shipping zone.
- Browser tests for the bag and checkout on phone and desktop viewports.
- Gateways switch on when their keys are in the environment; Stripe uses the optimized checkout.
- Product page on the Interactivity API gallery and add to bag form, without the jQuery gallery scripts.
- Header fits a phone screen; the closed bag drawer is out of the tab order.
- Home page built from reusable patterns: hero, category grid, new in, trust strip.
