import type { FormSchema } from '@xelqun/forms';
import type { TableSchema } from '@xelqun/tables';

export interface PanelNavigationItem {
    label: string;
    icon: string | null;
    url: string | null;
    active: boolean;
    badge: string | null;
    badgeColor?: string;
    badgeTooltip?: string;
    /** Items that sit under this one, shown while one of them, or this, is open. */
    children?: PanelNavigationItem[];
}

export interface PanelNavigationGroup {
    label: string | null;
    icon?: string;
    collapsible?: boolean;
    collapsed?: boolean;
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
    /** Links under the result, from getGlobalSearchResultActions(). */
    actions?: Array<{ label: string; url: string }>;
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
    icon?: string | null;
    /** Links to follow from the bell. Actions that run code stay in the toast. */
    actions?: Array<{ label: string; url: string; openInNewTab: boolean }>;
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

export interface PanelTenantOption {
    name: string;
    url: string;
    active: boolean;
}

/** What every page of a panel shares. Hosts read it as `xelqunPanel`. */
export interface PanelShell {
    id: string;
    url: string;
    brand: { name: string; logo: string | null; darkLogo: string | null; logoHeight: string };
    tenant: {
        name: string;
        profileUrl: string | null;
        registrationUrl: string | null;
        /** More links from tenantMenuItems(). */
        menuItems?: PanelNavigationItem[];
        tenants: PanelTenantOption[];
        /** Set when the user has more tenants than are listed. */
        searchUrl?: string | null;
    } | null;
    theme: {
        colors: Record<string, string>;
        font: string;
        default: 'light' | 'dark' | 'system';
        forced: 'light' | 'dark' | null;
        animations: boolean;
    };
    breadcrumbs: boolean;
    userMenuItems: PanelNavigationItem[];
    globalSearch: boolean;
    topNavigation: boolean;
    hooks: Record<string, Array<{ component: string; props: Record<string, unknown> }>>;
    messages: Record<string, string>;
    navigation: PanelNavigationGroup[];
    quickCreate: PanelQuickCreateItem[];
    searchUrl: string | null;
    notifications: {
        url: string;
        pollSeconds: number | null;
        broadcastChannel: string | null;
    } | null;
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
    /** Seconds between reloads, from $pollingInterval. */
    pollSeconds?: number;
}

interface PanelWidgetCard {
    heading: string | null;
    seeAllUrl: string | null;
}

export interface PanelChart {
    type: 'line' | 'bar' | 'doughnut';
    labels: string[];
    datasets: Array<{ label: string; data: number[] }>;
}

export type PanelWidget = PanelWidgetBase &
    (
        | { type: 'stats'; data: PanelWidgetCard & { stats: PanelStat[] } }
        | { type: 'list' | 'table'; data: PanelWidgetCard & { table: TableSchema } }
        | { type: 'tiles'; data: PanelWidgetCard & { tiles: PanelTile[] } }
        | { type: string; data: PanelWidgetCard & Record<string, unknown> }
    );

export type PanelContent =
    | {
          type: 'table';
          schema: TableSchema;
          heading: string | null;
          /** The tab a RelationGroup gives it, shared with the rest of the group. */
          group?: string;
          badge?: string;
          badgeColor?: string;
      }
    | { type: 'actions'; schema: FormSchema }
    | {
          type: 'form';
          schema: FormSchema;
          action: string;
          method: 'post' | 'put' | 'patch';
          submitLabel: string;
          /** A second button that saves and starts a new one. */
          createAnotherLabel?: string;
          revision: string;
      };

export interface PanelAuthPageData {
    title: string;
    description: string | null;
    descriptionLink: { label: string; url: string } | null;
    form: FormSchema;
    action: string;
    submitLabel: string;
    links: Array<{ label: string; url: string; method: 'get' | 'post' }>;
    quote: string;
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
    url: string | null;
    active: boolean;
}

export interface PanelPageData {
    title: string;
    eyebrow: string | null;
    subheading: string | null;
    breadcrumbs: PanelBreadcrumb[];
    hasWidgets: boolean;
    hasFooterWidgets?: boolean;
    /** Fields above the widgets, whose values widgets read. */
    filters?: FormSchema;
    tabs: PanelTab[];
    subNavigation: PanelTab[];
    notice: { title: string; description: string; lines: string[] } | null;
    headerActions: FormSchema | null;
    content: PanelContent[];
    /** stacked shows every relation table, one after another. Tabs otherwise. */
    relationsLayout?: 'stacked';
}
