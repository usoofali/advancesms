<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, Palette, Server, Shield, User } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSchool } from '@/routes/school-settings';
import { edit as editSecurity } from '@/routes/security';
import { index as indexSystemInfo } from '@/routes/system-info';
import type { NavItem } from '@/types';

const page = usePage();

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Profile',
            href: editProfile(),
            icon: User,
        },
        {
            title: 'Security',
            href: editSecurity(),
            icon: Shield,
        },
        {
            title: 'Appearance',
            href: editAppearance(),
            icon: Palette,
        },
    ];

    const permissions = page.props.auth?.permissions as string[] | undefined;
    const roles = page.props.auth?.roles as string[] | undefined;
    const isSuperAdmin = permissions?.includes('*') || roles?.includes('superadmin');

    if (isSuperAdmin || permissions?.includes('school_settings.view')) {
        items.push({
            title: 'School Profile',
            href: editSchool(),
            icon: Building2,
        });
    }

    if (isSuperAdmin) {
        items.push({
            title: 'System Info',
            href: indexSystemInfo(),
            icon: Server,
        });
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your profile, institutional branding, and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start gap-2',
                            { 'bg-muted font-medium': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-4xl">
                <section class="max-w-4xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
