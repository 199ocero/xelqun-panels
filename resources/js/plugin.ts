import type { XelqunPlugin } from '@xelqun/forms';
import {
    Bell,
    ChartColumn,
    ClipboardList,
    Copy,
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
    Shield,
    ShoppingCart,
    Tag,
    Truck,
    Unlink,
    Users,
    Zap,
} from 'lucide-vue-next';
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
            shield: Shield,
            'shopping-cart': ShoppingCart,
            tag: Tag,
            truck: Truck,
            unlink: Unlink,
            users: Users,
            zap: Zap,
        },
    };
}
