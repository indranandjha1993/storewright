# Contributing

Thanks for taking the time. Storewright is young, so the most useful contributions right now
are bug reports against what exists and small, focused pull requests.

## Before you start

Open an issue first for anything larger than a fix. It saves both of us from work that does not
land. For a fix, a pull request with a short description is enough.

## Development

Docker is the only requirement. `make up` starts the stack, `make install` sets up the site,
`make test` and `make lint` run what CI runs. Commands are listed in the Makefile.

## Code

- PHP follows the WordPress Coding Standards as configured in `phpcs.xml`, with PHPStan at
  the level set in `phpstan.neon`. JavaScript and CSS follow the `@wordpress/scripts` defaults.
- Keep functions small and names clear. Comments are for the why, and they are rare.
- New behaviour comes with a test. Payments and fulfillment changes come with a test that
  exercises the gateway or carrier through its sandbox.
- Do not add dependencies without discussing it in the issue. Third-party plugins in the base
  image are limited to WooCommerce and the official Stripe and Razorpay gateways.

## Commits and pull requests

- One concern per commit. Subject in lowercase imperative, under 60 characters, no trailing
  period, for example `reject a refund larger than the captured amount`.
- Body only when the why is not obvious from the diff.
- A pull request describes what changed, why, and how to check it.

## Licence

By contributing you agree that your contributions are licensed under GPL-2.0-or-later.
