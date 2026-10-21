<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const openGroups = ref<Record<string, boolean>>({});

function isGroupActive(item: NavItem): boolean {
    if (item.href && isCurrentUrl(item.href)) {
        return true;
    }
    if (item.items && item.items.length > 0) {
        return item.items.some((subItem) => isCurrentUrl(subItem.href));
    }
    return false;
}

watch(
    () => page.url,
    () => {
        for (const item of props.items) {
            if (isGroupActive(item)) {
                openGroups.value[item.title] = true;
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Collapsible Group Dropdown -->
                <Collapsible
                    v-if="item.items && item.items.length > 0"
                    v-model:open="openGroups[item.title]"
                    as-child
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="item.title"
                                :is-active="isGroupActive(item)"
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    :class="item.color"
                                />
                                <span class="font-medium">{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 group-data-[collapsible=icon]:hidden text-muted-foreground"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub class="mr-0 pr-0">
                                <SidebarMenuSubItem
                                    v-for="subItem in item.items"
                                    :key="subItem.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isCurrentUrl(subItem.href)"
                                        size="sm"
                                    >
                                        <Link :href="subItem.href" class="flex items-center gap-2">
                                            <component
                                                :is="subItem.icon"
                                                v-if="subItem.icon"
                                                :class="[subItem.color, 'size-3.5 shrink-0']"
                                            />
                                            <span class="truncate">{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- Single Standalone Item -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="item.href ? isCurrentUrl(item.href) : false"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href || '#'">
                            <component
                                :is="item.icon"
                                v-if="item.icon"
                                :class="item.color"
                            />
                            <span class="font-medium">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
