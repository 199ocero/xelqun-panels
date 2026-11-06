# Xelqun Panels

Describe an admin panel in PHP and get navigation, authentication, CRUD pages and dashboards, rendered in Vue.

> **Pre-alpha, and not usable yet.** Requires Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, [`xelqun/forms`](https://github.com/199ocero/xelqun-forms) and [`xelqun/tables`](https://github.com/199ocero/xelqun-tables).

## Developing

Until a sibling's next release is on Packagist, install against the checkouts beside this one:

```bash
COMPOSER=composer.local.json composer install
```

`composer.local.json` is gitignored. Copy `composer.json`, add `path` repositories for `../forms` and `../tables`, require both as `*@dev`, and set `minimum-stability` to `dev`.

## License

MIT
