import type { XelqunPlugin } from '@xelqun/forms';
import {
    Bell,
    ChartColumn,
    ClipboardList,
    Copy,
    Download,
    Eye,
    FileText,
    FolderTree,
    House,
    Layers,
    LayoutDashboard,
    Link,
    Mail,
    MapPin,
    Package,
    Palette,
    RotateCcw,
    Settings,
    Shield,
    ShoppingCart,
    Tag,
    Truck,
    Unlink,
    Upload,
    Users,
    Zap,
} from '@lucide/vue';
import XqPanelEntry from './components/XqPanelEntry.vue';

/**
 * Goes in the `plugins` of `createXelqunForms()`. The icons are the ones a
 * sidebar usually wants, on top of forms' set. Add others with its `icons` option.
 */
export function panelsPlugin(): XelqunPlugin {
    return {
        id: 'xelqun/panels',
        components: { 'xelqun-panels/entry': XqPanelEntry },
        icons: {
            bell: Bell,
            'chart-column': ChartColumn,
            'clipboard-list': ClipboardList,
            copy: Copy,
            download: Download,
            eye: Eye,
            'file-text': FileText,
            'folder-tree': FolderTree,
            house: House,
            layers: Layers,
            'layout-dashboard': LayoutDashboard,
            link: Link,
            mail: Mail,
            'map-pin': MapPin,
            package: Package,
            palette: Palette,
            'rotate-ccw': RotateCcw,
            settings: Settings,
            shield: Shield,
            'shopping-cart': ShoppingCart,
            tag: Tag,
            truck: Truck,
            unlink: Unlink,
            upload: Upload,
            users: Users,
            zap: Zap,
        },
    };
}
