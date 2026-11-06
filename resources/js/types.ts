import type { FormSchema } from '@xelqun/forms';
import type { TableSchema } from '@xelqun/tables';

export interface PanelNavigationItem {
    label: string;
    icon: string | null;
    url: string | null;
    active: boolean;
    badge: string | null;
}

export interface PanelNavigationGroup {
    label: string | null;
    items: PanelNavigationItem[];
}

export interface PanelQuickCreateItem {
    label: string;
    icon: string | null;
    url: string;
}

export interface PanelSearchResult {
    title: string;
    url: string;
    details: string;
    /** Falls back to the group's. */
    icon?: string | null;
}

/** One resource's matches, as the panel's search route returns them. */
export interface PanelSearchGroup {
    label: string;
    icon: string | null;
    results: PanelSearchResult[];
}

export interface PanelNotification {
    id: string;
    title: string | null;
    body: string | null;
    status: string | null;
    url: string | null;
    read: boolean;
    time: string | null;
}

export interface PanelNotificationList {
    unread: number;
    notifications: PanelNotification[];
}

export interface PanelSidebarCard {
    title: string;
    icon: string | null;
    badge: string | null;
    value: string | null;
    note: string | null;
    progress: number | null;
    footer: string | null;
    link: { label: string; url: string } | null;
}

/** What every page of a panel shares. Hosts read it as `xelqunPanel`. */
export interface PanelShell {
    id: string;
    url: string;
    brand: { name: string };
    tenant: {
        name: string;
        tenants: Array<{ name: string; url: string; active: boolean }>;
    } | null;
    theme: { colors: Record<string, string>; font: string };
    navigation: PanelNavigationGroup[];
    quickCreate: PanelQuickCreateItem[];
    searchUrl: string | null;
    notifications: { url: string; pollSeconds: number | null } | null;
    sidebarFooter: PanelSidebarCard | null;
    user: { name: string; email: string } | null;
    logoutUrl: string | null;
    profileUrl: string | null;
}

export interface PanelStat {
    label: string;
    value: string;
    description: string | null;
    icon: string | null;
    chart: number[];
}

export interface PanelTile {
    label: string;
    icon: string | null;
    status: string | null;
    color: string;
    url: string | null;
}

interface PanelWidgetBase {
    id: string;
    columnSpan: number;
}

interface PanelWidgetCard {
    heading: string | null;
    seeAllUrl: string | null;
}

export type PanelWidget = PanelWidgetBase &
    (
        | { type: 'stats'; data: PanelWidgetCard & { stats: PanelStat[] } }
        | { type: 'list' | 'table'; data: PanelWidgetCard & { table: TableSchema } }
        | { type: 'tiles'; data: PanelWidgetCard & { tiles: PanelTile[] } }
        | { type: string; data: PanelWidgetCard & Record<string, unknown> }
    );

export type PanelContent =
    | { type: 'table'; schema: TableSchema; heading: string | null }
    | { type: 'actions'; schema: FormSchema }
    | {
          type: 'form';
          schema: FormSchema;
          action: string;
          method: 'post' | 'put' | 'patch';
          submitLabel: string;
          revision: string;
      };

export interface PanelAuthPageData {
    title: string;
    description: string | null;
    form: FormSchema;
    action: string;
    submitLabel: string;
    links: Array<{ label: string; url: string; method: 'get' | 'post' }>;
}

export interface PanelBreadcrumb {
    label: string;
    url?: string;
}

export interface PanelTab {
    key: string;
    label: string;
    icon: string | null;
    badge: string | null;
    url: string;
    active: boolean;
}

export interface PanelPageData {
    title: string;
    eyebrow: string | null;
    subheading: string | null;
    breadcrumbs: PanelBreadcrumb[];
    hasWidgets: boolean;
    tabs: PanelTab[];
    notice: { title: string; description: string; lines: string[] } | null;
    headerActions: FormSchema | null;
    content: PanelContent[];
}
