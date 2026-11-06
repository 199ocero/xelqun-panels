<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { HttpError, cn, postJson } from '@xelqun/forms';
import { Bell } from 'lucide-vue-next';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { PanelNotification, PanelNotificationList } from '../types';
import { iconButtonClass } from '../ui/classes';
import { textToneClasses } from './widgets/tones';

const SIGNED_OUT_STATUSES = [401, 403, 419];

const props = defineProps<{ url: string; pollSeconds: number | null }>();

const open = ref(false);
const unread = ref(0);
const notifications = ref<PanelNotification[]>([]);

let timer: ReturnType<typeof setInterval> | undefined;

function stopPolling(): void {
    clearInterval(timer);
}

/**
 * A failed check stays quiet, because it runs in the background and the next
 * one may work. Only a session that ended stops it.
 */
async function send(path: string = ''): Promise<void> {
    try {
        const list = await postJson<PanelNotificationList>(`${props.url}${path}`, {});

        unread.value = list.unread;
        notifications.value = list.notifications;
    } catch (error) {
        if (error instanceof HttpError && SIGNED_OUT_STATUSES.includes(error.status)) {
            stopPolling();
        }
    }
}

async function openNotification(notification: PanelNotification): Promise<void> {
    if (!notification.read) {
        await send(`/${notification.id}/read`);
    }

    if (notification.url) {
        open.value = false;
        router.visit(notification.url);
    }
}

onMounted(() => {
    void send();

    if (props.pollSeconds !== null) {
        timer = setInterval(() => {
            if (!document.hidden) {
                void send();
            }
        }, props.pollSeconds * 1000);
    }
});

onBeforeUnmount(stopPolling);
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            :class="[iconButtonClass, 'relative']"
            :aria-label="
                unread > 0
                    ? t('Notifications, :count unread', { count: unread })
                    : t('Notifications')
            "
            data-xq-bell
        >
            <Bell class="size-4.5" aria-hidden="true" />
            <span
                v-if="unread > 0"
                aria-hidden="true"
                class="-top-0.5 -right-0.5 min-w-4 px-1 text-xs font-medium bg-xq-primary text-xq-primary-foreground h-4 leading-4 absolute rounded-full"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                align="end"
                :side-offset="8"
                class="w-80 rounded-xq-lg border-xq-border bg-xq-popover text-xq-popover-foreground shadow-lg z-50 max-w-[calc(100vw-2rem)] overflow-hidden border"
                data-xq-notifications
            >
                <div class="px-3 py-2 border-xq-border flex items-center justify-between border-b">
                    <p class="text-sm font-medium">{{ t('Notifications') }}</p>
                    <div v-if="notifications.length > 0" class="gap-3 text-xs flex">
                        <button
                            type="button"
                            class="text-xq-primary cursor-pointer hover:underline"
                            @click="send('/read-all')"
                        >
                            {{ t('Mark all read') }}
                        </button>
                        <button
                            type="button"
                            class="text-xq-muted-foreground cursor-pointer hover:underline"
                            @click="send('/clear')"
                        >
                            {{ t('Clear') }}
                        </button>
                    </div>
                </div>
                <ul v-if="notifications.length > 0" class="max-h-96 overflow-y-auto">
                    <li
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="border-xq-border border-b last:border-b-0"
                    >
                        <button
                            type="button"
                            class="gap-2.5 px-3 py-2.5 hover:bg-xq-accent flex w-full cursor-pointer text-left"
                            @click="openNotification(notification)"
                        >
                            <span
                                aria-hidden="true"
                                :class="
                                    cn(
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        notification.read ? 'bg-transparent' : 'bg-current',
                                        textToneClasses[notification.status ?? 'primary'],
                                    )
                                "
                            ></span>
                            <span class="min-w-0">
                                <span class="text-sm font-medium block">
                                    {{ notification.title }}
                                </span>
                                <span
                                    v-if="notification.body"
                                    class="text-sm text-xq-muted-foreground block"
                                >
                                    {{ notification.body }}
                                </span>
                                <span class="mt-0.5 text-xs text-xq-muted-foreground block">
                                    {{ notification.time }}
                                </span>
                                <span v-if="!notification.read" class="sr-only">{{
                                    t('Unread')
                                }}</span>
                            </span>
                        </button>
                    </li>
                </ul>
                <p v-else class="px-3 py-8 text-sm text-xq-muted-foreground text-center">
                    {{ t('Nothing yet.') }}
                </p>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
