<p align="center">
    <img src="https://raw.githubusercontent.com/199ocero/xelqun-panels/main/.github/art/banner.png" alt="Xelqun Panels: PHP-defined admin panels for Laravel, Inertia and Vue" width="100%">
</p>

<p align="center">
    <a href="https://packagist.org/packages/xelqun/panels"><img src="https://img.shields.io/packagist/v/xelqun/panels?label=packagist&color=414fd2" alt="Packagist version"></a>
    <a href="https://www.npmjs.com/package/@xelqun/panels"><img src="https://img.shields.io/npm/v/@xelqun/panels?label=npm&color=414fd2" alt="npm version"></a>
    <a href="https://packagist.org/packages/xelqun/panels/stats"><img src="https://img.shields.io/packagist/dt/xelqun/panels?color=414fd2" alt="Total downloads"></a>
</p>

Describe an admin panel in PHP and generic Vue components render it, with navigation, authentication, CRUD pages and dashboards. Normal panels need no Vue at all.

> **Alpha:** the API will change. Requires Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, [`xelqun/forms`](https://github.com/199ocero/xelqun-forms) and [`xelqun/tables`](https://github.com/199ocero/xelqun-tables).

## Installation

```bash
composer require xelqun/panels
php artisan xelqun-panels:install
php artisan make:xelqun-panel Admin
```

Then add `PanelUser` to your user model, as the [installation guide](https://xelqun.com/panels/installation) shows. Nobody can sign in until you do.

## Documentation

Everything else is in the docs at **[xelqun.com/panels](https://xelqun.com/panels)**. You can also try the [demo](https://demo.xelqun.com).

## License

MIT
