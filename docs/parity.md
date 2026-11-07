# Xelqun and Filament

Xelqun Panels follows [Filament](https://filamentphp.com), for Inertia and Vue. This list goes through Filament's 5.x documentation page by page. Filament 5 only added Livewire 4 support over version 4, so its 5.x docs are the whole feature set.

**Yes** means it's here. **Partly** says what's missing. **No** means it isn't here yet. **Not needed** means it only makes sense in Livewire.

## Resources

| Filament                  | Xelqun     | Notes                                                                                                 |
| ------------------------- | ---------- | ----------------------------------------------------------------------------------------------------- |
| List, create, edit, view  | Yes        | One class, with no page files.                                                                        |
| Deleting and soft deletes | Yes        |                                                                                                       |
| Managing relationships    | Yes        | Has-many, many-to-many, attach, detach, associate and dissociate, on tabs when there are several.     |
| Nested resources          | Yes        |                                                                                                       |
| Singular resources        | Yes        | `ManageSingleRecord`.                                                                                 |
| Simple resources          | Yes        | `ManageRecords`.                                                                                      |
| Global search             | Yes        |                                                                                                       |
| Widgets on resource pages | Partly     | On the list page. Not on record pages.                                                                |
| Custom resource pages     | Yes        |                                                                                                       |

## Tables

| Filament              | Xelqun | Notes                                                                 |
| --------------------- | ------ | --------------------------------------------------------------------- |
| Text, icon, image     | Yes    |                                                                       |
| Color column          | Yes    |                                                                       |
| Select, toggle, text input and checkbox columns | Yes | Read-only until `authorize()` says otherwise. |
| Custom columns        | Yes    |                                                                       |
| Select, ternary and custom filters | Yes |                                                                 |
| Query builder         | Partly | Rules match all or any. No nested groups.                             |
| Filter layout         | Partly | Filters open in one panel. No layout above the table.                 |
| Actions and bulk actions | Yes |                                                                       |
| Summaries             | Partly | Sum, average, count and range. No custom scopes or per-group lines.   |
| Grouping rows         | Partly | A default group in code. The viewer can't choose one.                 |
| Empty state           | Yes    |                                                                       |
| Layout                | Partly | `contentGrid()`, `striped()`. No `Split` or `Stack` inside a row.     |
| Reordering rows       | Partly | Up and down buttons, not drag and drop.                               |
| Column toggles        | Yes    |                                                                       |
| Polling               | Yes    |                                                                       |
| Custom data           | No     | A table needs an Eloquent query.                                      |

## Forms and layouts

| Filament                                              | Xelqun | Notes                         |
| ----------------------------------------------------- | ------ | ----------------------------- |
| Every field in Filament's list                        | Yes    | See `xelqun/forms`.           |
| Sections, tabs, wizards, callouts, empty states, grid | Yes    |                               |
| Prime components                                      | Yes    | `Text`, `Icon`, `Image`, and more. |
| Validation                                            | Yes    |                               |
| Unsaved changes alerts                                | Yes    | `unsavedChangesAlerts()`.     |

## Infolists

| Filament                                      | Xelqun | Notes                        |
| --------------------------------------------- | ------ | ---------------------------- |
| Text, icon, image, color, code, key-value and repeatable entries | Yes |                 |
| Custom entries                                | Yes    | Extend `Entry`.              |

## Actions

| Filament                              | Xelqun | Notes                                                                 |
| ------------------------------------- | ------ | --------------------------------------------------------------------- |
| Modals and grouping                   | Yes    |                                                                       |
| Create, edit, view and delete         | Yes    |                                                                       |
| Replicate, force delete and restore   | Yes    |                                                                       |
| Import                                | Partly | CSV, up to 1,000 rows in the request. No queue or column mapping.     |
| Export                                | Partly | CSV of the list and tab. No queue, XLSX, or table filters.            |

## Notifications and widgets

| Filament                 | Xelqun | Notes                                             |
| ------------------------ | ------ | ------------------------------------------------- |
| Flash notifications      | Yes    |                                                   |
| Database notifications   | Yes    |                                                   |
| Broadcast notifications  | Yes    | Needs Laravel Echo in the app.                    |
| Stats overview           | Yes    |                                                   |
| Chart widgets            | Partly | Line, bar and doughnut. No filter on a chart, and no radar, scatter or bubble. |
| Table and custom widgets | Yes    |                                                   |

## Panels

| Filament                            | Xelqun     | Notes                                                                 |
| ----------------------------------- | ---------- | --------------------------------------------------------------------- |
| Panel configuration                 | Yes        | Brand, logo, favicon, colors, font, width, dark mode.                 |
| Navigation, groups and badges       | Yes        |                                                                       |
| Top navigation                      | Yes        |                                                                       |
| User menu                           | Yes        |                                                                       |
| Clusters                            | Partly     | No URL prefix, and members don't show the cluster in breadcrumbs.     |
| Sub navigation                      | Yes        |                                                                       |
| Authentication flows                | Yes        |                                                                       |
| Multi-factor authentication         | Partly     | App codes, email codes and recovery codes. App setup has no QR code, because that needs a new dependency. |
| Multi-tenancy                       | Partly     | Everything but tenant billing.                                        |
| Colors                              | Yes        | The same names and defaults, apart from a blue primary.               |
| Icons                               | Yes        | Lucide, by name, not Heroicons.                                       |
| CSS hooks                           | Partly     | `data-xq-*` attributes and `--xq-*` tokens.                           |
| Render hooks                        | Partly     | Eight places, for Vue components.                                     |
| Plugins                             | Yes        |                                                                       |
| SPA mode                            | Not needed | Inertia always works this way.                                        |
| Blade components                    | Not needed | Vue components, exported from `@xelqun/forms`.                        |
| Testing helpers                     | Partly     | `InteractsWithPanels`, with forms' and tables' own helpers.           |

## Where Xelqun goes further

- Access is denied by default. With no policy, nobody gets in.
- Resources need no generated page files.
- Charts need no JavaScript library.
- The admin area keeps its layout between pages, with no reloads.
